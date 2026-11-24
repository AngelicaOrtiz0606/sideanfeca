<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use App\Services\BitacoraService;
use Database\Seeders\CatalogosInicialesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\Support\CreaInstituciones;
use Tests\TestCase;

/**
 * Mi actividad (la bitácora propia, para cualquier cuenta), los pendientes
 * del inicio ("Por atender") y el detalle de la bitácora: nombres en lugar de
 * "#id", el valor del grado y la pertenencia, y el aviso en los datos
 * personales (que no guardan su valor) con el enlace a la ficha.
 */
class MiActividadYPendientesTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosInicialesSeeder::class);
        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR, [], ['pers_nombre' => 'Luis', 'pers_primer_apellido' => 'Ramírez']);
    }

    public function test_mi_actividad_muestra_solo_lo_que_hizo_la_cuenta(): void
    {
        $otra = $this->crearCuenta(Rol::SUPERADMINISTRADOR, [], ['pers_nombre' => 'Marta', 'pers_primer_apellido' => 'Ruiz']);
        DB::select('select bitacora_registrar(?, ?, ?, ?, ?)', [$this->admin->usua_id_usuario, 'Registro', 'ZONA_REGIONAL', 1, null]);
        DB::select('select bitacora_registrar(?, ?, ?, ?, ?)', [$otra->usua_id_usuario, 'Eliminacion', 'PERSONA', 999999, 'Duplicado']);

        $this->actingAs($this->admin)->get('/mi-actividad')->assertOk()
            ->assertSee('Mi actividad')->assertSee('Mostrando 1–1 de 1')->assertDontSee('Marta Ruiz');
        $csv = $this->actingAs($this->admin)->get('/mi-actividad/csv')->assertOk()->streamedContent();
        $this->assertSame(2, substr_count($csv, "\n"));
        $this->assertStringNotContainsString('Marta', $csv);

        // La bitácora de todos sigue siendo solo del superadministrador
        $this->actingAs($this->admin)->get('/bitacora')->assertForbidden();
    }

    public function test_por_atender_dice_vacantes_instituciones_sin_titular_y_personas_sin_correo(): void
    {
        $cargo = (int) DB::table('cargo')->insertGetId(['carg_nombre_m' => 'Presidente', 'carg_nombre_f' => 'Presidenta', 'carg_nivel' => 'Nacional', 'carg_activo' => true], 'carg_id_cargo');
        $puesto = (int) DB::table('puesto')->insertGetId(['pues_id_cargo' => $cargo], 'pues_id_puesto');
        $cnd = (int) DB::table('tipo_directorio')->where('tidi_nombre', 'Consejo Nacional Directivo')->value('tidi_id_tipo_directorio');
        DB::table('puesto_directorio')->insert(['pudi_id_puesto' => $puesto, 'pudi_id_tipo_directorio' => $cnd, 'pudi_orden' => 1]);
        $afiliada = $this->crearInstitucion('Afiliada');
        DB::table('institucion_nombre')->insert(['instn_id_institucion' => $afiliada, 'instn_nombre' => 'Facultad sin titular', 'instn_fecha_inicio' => '2000-01-01']);

        $this->actingAs($this->admin)->get('/inicio')->assertOk()
            ->assertSee('Por atender')
            ->assertSee('puestos vacantes en los directorios')->assertSee('Consejo Nacional Directivo: 1')
            ->assertSee('instituciones afiliadas u observadoras sin titular')->assertSee('Facultad sin titular');

        // Alguien ocupa el puesto, pero no tiene correo visible
        $rosa = (int) DB::table('persona')->insertGetId(['pers_nombre' => 'Rosa', 'pers_primer_apellido' => 'Gómez', 'pers_genero' => 'F', 'pers_id_institucion_educativa' => $afiliada], 'pers_id_persona');
        DB::table('designacion')->insert(['desi_id_puesto' => $puesto, 'desi_id_persona' => $rosa, 'desi_fecha_inicio' => '2024-01-01']);

        $this->actingAs($this->admin)->get('/inicio')->assertOk()
            ->assertDontSee('Consejo Nacional Directivo: 1')
            ->assertSee('personas con designación vigente sin correo visible')->assertSee('Rosa Gómez');
    }

    public function test_la_bitacora_nombra_lo_borrado_y_explica_los_datos_personales(): void
    {
        $persona = (int) DB::table('persona')->insertGetId(['pers_nombre' => 'Rosa', 'pers_primer_apellido' => 'Gómez', 'pers_genero' => 'F'], 'pers_id_persona');
        $zona = (int) DB::selectOne('select bitacora_registrar(?, ?, ?, ?, ?) as id', [$this->admin->usua_id_usuario, 'Eliminacion', 'ZONA_REGIONAL', 999, 'Duplicado'])->id;
        DB::select('select bitacora_detalle_registrar(?, ?, ?, ?, ?)', [$zona, 999, 'Nombre de la zona', 'Ciudad de México', null]);
        $cambio = (int) DB::selectOne('select bitacora_registrar(?, ?, ?, ?, ?) as id', [$this->admin->usua_id_usuario, 'Modificacion', 'PERSONA', $persona, null])->id;
        DB::select('select bitacora_detalle_registrar(?, ?, ?, ?, ?)', [$cambio, $persona, 'Correo', null, null]);
        DB::select('select bitacora_detalle_registrar(?, ?, ?, ?, ?)', [$cambio, $persona, 'Pertenencia', 'FCA UNAM', 'CACECA']);

        $this->actingAs($this->admin);
        [$modificacion, $borrado] = app(BitacoraService::class)->recientes(2)->all();

        $this->assertSame('la zona regional Ciudad de México', $borrado['objeto']);
        $this->assertSame(['Nombre de la zona: Ciudad de México'], $borrado['detalles']);
        $this->assertSame(['Correo: cambió (el valor no se guarda, por privacidad)', 'Pertenencia: FCA UNAM → CACECA'], $modificacion['detalles']);

        // Los demás datos personales siguen sin poder guardar su valor
        $this->expectExceptionMessage('Los campos personales no guardan valores en la bitácora (campo: Correo).');
        DB::select('select bitacora_detalle_registrar(?, ?, ?, ?, ?)', [$cambio, $persona, 'Correo', 'a@b.mx', 'c@d.mx']);
    }
}

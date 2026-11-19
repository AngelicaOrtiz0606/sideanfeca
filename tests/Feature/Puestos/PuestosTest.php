<?php

namespace Tests\Feature\Puestos;

use App\Enums\Privilegio;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogosInicialesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\Support\CreaInstituciones;
use Tests\TestCase;

/**
 * Catálogo de puestos (M6, lote 1): un cargo en su ámbito.
 * - Institucional: institución y, si aplica, titular (uno por institución).
 * - Regional: zona obligatoria; coordinación opcional.
 * - Nacional: coordinación opcional.
 * - No se repite el mismo puesto; con designaciones, el ámbito queda fijo y no se borra.
 */
class PuestosTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    private Usuario $admin;

    /** @var array<string, int> */
    private array $cargos = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $this->seed(CatalogosInicialesSeeder::class);

        foreach ([['Director', 'Directora', 'Institucional'], ['Presidente', 'Presidenta', 'Nacional'], ['Coordinador Regional', 'Coordinadora Regional', 'Regional'], ['Coordinador Nacional', 'Coordinadora Nacional', 'Nacional']] as [$m, $f, $nivel]) {
            $this->cargos[$m] = (int) DB::table('cargo')->insertGetId(['carg_nombre_m' => $m, 'carg_nombre_f' => $f, 'carg_nivel' => $nivel, 'carg_activo' => true], 'carg_id_cargo');
        }
    }

    public function test_registrar_un_puesto_institucional_titular_con_su_bitacora(): void
    {
        $fca = $this->institucion();

        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargos['Director'], 'institucion' => $fca, 'titular' => '1'])
            ->assertRedirect('/puestos')
            ->assertSessionHasNoErrors();

        $puesto = DB::table('puesto')->where('pues_id_cargo', $this->cargos['Director'])->first();
        $this->assertSame([$fca, null, null, true, true], [(int) $puesto->pues_id_institucion_educativa, $puesto->pues_id_zona_regional, $puesto->pues_id_coordinacion_nacional, (bool) $puesto->pues_titularidad, (bool) $puesto->pues_activo]);
        $bitacora = DB::table('bitacora')->orderByDesc('bita_id_bitacora')->first();
        $this->assertSame(['Registro', 'PUESTO', (int) $puesto->pues_id_puesto], [$bitacora->bita_accion, $bitacora->bita_tabla, (int) $bitacora->bita_referencia]);
    }

    public function test_cada_nivel_pide_lo_suyo_y_lo_que_no_aplica_se_ignora(): void
    {
        // Institucional sin institución, regional sin zona
        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargos['Director']])
            ->assertSessionHasErrors(['institucion' => 'Elige la institución del puesto.']);
        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargos['Coordinador Regional']])
            ->assertSessionHasErrors(['zona' => 'Elige la zona regional del puesto.']);

        // Regional con zona y coordinación; la institución y el titular enviados se ignoran
        $coordinacion = (int) DB::table('coordinacion_nacional')->orderBy('cona_orden')->value('cona_id_coordinacion_nacional');
        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargos['Coordinador Regional'], 'zona' => $this->zona(3), 'coordinacion' => $coordinacion, 'institucion' => $this->institucion(), 'titular' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('puesto', ['pues_id_cargo' => $this->cargos['Coordinador Regional'], 'pues_id_zona_regional' => $this->zona(3), 'pues_id_coordinacion_nacional' => $coordinacion, 'pues_id_institucion_educativa' => null, 'pues_titularidad' => false]);

        // Nacional: sin zona aunque se envíe
        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargos['Presidente'], 'zona' => $this->zona(3)])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('puesto', ['pues_id_cargo' => $this->cargos['Presidente'], 'pues_id_zona_regional' => null]);
    }

    public function test_el_mismo_puesto_no_se_repite_tampoco_el_nacional_sin_ambito(): void
    {
        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargos['Presidente']])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargos['Presidente']])
            ->assertSessionHasErrors(['cargo' => 'Ese puesto ya existe: «Presidente · Nacional».']);

        $this->assertSame(1, DB::table('puesto')->count());
    }

    public function test_una_institucion_tiene_a_lo_mas_un_puesto_titular(): void
    {
        $fca = $this->institucion();
        $subdirector = (int) DB::table('cargo')->insertGetId(['carg_nombre_m' => 'Subdirector', 'carg_nombre_f' => 'Subdirectora', 'carg_nivel' => 'Institucional', 'carg_activo' => true], 'carg_id_cargo');
        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargos['Director'], 'institucion' => $fca, 'titular' => '1'])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $subdirector, 'institucion' => $fca, 'titular' => '1'])
            ->assertSessionHasErrors(['titular' => 'La institución ya tiene su puesto titular: «Director». Solo puede haber uno.']);

        // Sin titularidad sí se registra
        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $subdirector, 'institucion' => $fca])->assertSessionHasNoErrors();
    }

    public function test_cargos_coordinaciones_e_instituciones_inactivos_no_se_ofrecen_para_puestos_nuevos(): void
    {
        DB::table('cargo')->where('carg_id_cargo', $this->cargos['Presidente'])->update(['carg_activo' => false]);
        $coordinacion = (int) DB::table('coordinacion_nacional')->orderBy('cona_orden')->value('cona_id_coordinacion_nacional');
        DB::table('coordinacion_nacional')->where('cona_id_coordinacion_nacional', $coordinacion)->update(['cona_activo' => false]);
        $inactiva = $this->crearInstitucion('Observadora', false);

        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargos['Presidente']])
            ->assertSessionHasErrors(['cargo' => 'Elige un cargo activo de la lista.']);
        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargos['Coordinador Nacional'], 'coordinacion' => $coordinacion])
            ->assertSessionHasErrors(['coordinacion' => 'Elige una coordinación activa de la lista.']);
        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargos['Director'], 'institucion' => $inactiva])
            ->assertSessionHasErrors(['institucion' => 'Elige una institución activa de la lista.']);

        $this->assertSame(0, DB::table('puesto')->count());
    }

    public function test_editar_registra_cada_cambio(): void
    {
        $id = $this->puesto(['cargo' => $this->cargos['Coordinador Regional'], 'zona' => $this->zona(3)]);

        $this->actingAs($this->admin)->put("/puestos/{$id}", ['cargo' => $this->cargos['Coordinador Regional'], 'zona' => $this->zona(5)])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->zona(5), (int) DB::table('puesto')->where('pues_id_puesto', $id)->value('pues_id_zona_regional'));
        $detalle = DB::table('bitacora_detalle')->orderByDesc('bide_id_detalle')->first();
        $this->assertSame(['Zona regional', 'Zona 3 · Centro', 'Zona 5 · Centro Sur'], [$detalle->bide_campo, $detalle->bide_anterior, $detalle->bide_nuevo]);
    }

    public function test_con_designaciones_el_ambito_queda_fijo_y_no_se_borra(): void
    {
        $fca = $this->institucion();
        $id = $this->puesto(['cargo' => $this->cargos['Director'], 'institucion' => $fca]);
        $this->designar($id, $fca);

        $this->actingAs($this->admin)->put("/puestos/{$id}", ['cargo' => $this->cargos['Director'], 'institucion' => $this->institucion('1000002')])
            ->assertSessionHasErrors(['cargo' => 'Ya tiene designaciones, así que el cargo y el ámbito no cambian (sería cambiar su historia). Si el puesto cambió, desactívalo y registra otro.']);

        // Lo demás (la titularidad) sí cambia
        $this->actingAs($this->admin)->put("/puestos/{$id}", ['cargo' => $this->cargos['Director'], 'institucion' => $fca, 'titular' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue((bool) DB::table('puesto')->where('pues_id_puesto', $id)->value('pues_titularidad'));

        $this->actingAs($this->admin)->delete("/puestos/{$id}", ['motivo' => 'Error de captura'])
            ->assertSessionHas('aviso', 'No se puede borrar porque ya tuvo 1 designación. Si ya no se usa, desactívalo: su historia se conserva.');
        $this->assertDatabaseHas('puesto', ['pues_id_puesto' => $id]);
    }

    public function test_la_lista_muestra_quien_lo_ocupa_y_una_persona_oculta_aparece_como_vacante(): void
    {
        $fca = $this->institucion();
        $id = $this->puesto(['cargo' => $this->cargos['Director'], 'institucion' => $fca]);
        $persona = $this->designar($id, $fca);

        $this->actingAs($this->admin)->get('/puestos')->assertOk()->assertSee('Juan Pérez López')->assertDontSee('>Vacante<', false);

        DB::table('persona')->where('pers_id_persona', $persona)->update(['pers_oculta_admin' => true]);
        $this->actingAs($this->admin)->get('/puestos')->assertOk()->assertDontSee('Juan Pérez López')->assertSee('Vacante');
    }

    public function test_desactivar_activar_y_borrar_sin_designaciones(): void
    {
        $id = $this->puesto(['cargo' => $this->cargos['Presidente']]);
        DB::table('puesto_directorio')->insert(['pudi_id_puesto' => $id, 'pudi_id_tipo_directorio' => 1, 'pudi_orden' => 1]);

        $this->actingAs($this->admin)->patch("/puestos/{$id}/estado", ['activa' => '0'])
            ->assertSessionHas('estado', 'Se desactivó el puesto Presidente · Nacional. Conserva su historia y ya no recibe designaciones nuevas.');
        $this->assertFalse((bool) DB::table('puesto')->where('pues_id_puesto', $id)->value('pues_activo'));
        $this->actingAs($this->admin)->patch("/puestos/{$id}/estado", ['activa' => '1'])->assertSessionHas('estado', 'Se activó el puesto Presidente · Nacional.');

        $this->actingAs($this->admin)->delete("/puestos/{$id}", ['motivo' => 'Duplicado'])->assertSessionHas('estado', 'Se borró el puesto Presidente · Nacional.');
        $this->assertDatabaseMissing('puesto', ['pues_id_puesto' => $id]);
        $this->assertDatabaseMissing('puesto_directorio', ['pudi_id_puesto' => $id]);
        $bitacora = DB::table('bitacora')->orderByDesc('bita_id_bitacora')->first();
        $this->assertSame(['Eliminacion', 'Duplicado'], [$bitacora->bita_accion, $bitacora->bita_motivo]);
    }

    public function test_sin_el_privilegio_responde_403(): void
    {
        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $this->admin->usua_id_rol)
            ->where('ropr_id_privilegio', DB::table('privilegio')->where('priv_nombre', Privilegio::GestionarPuestos->value)->value('priv_id_privilegio'))
            ->update(['ropr_activo' => false]);

        $this->actingAs($this->admin)->get('/puestos')->assertForbidden();
        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargos['Presidente']])->assertForbidden();
        $this->assertSame(0, DB::table('puesto')->count());
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function puesto(array $datos): int
    {
        $this->actingAs($this->admin)->post('/puestos', $datos)->assertSessionHasNoErrors();

        return (int) DB::table('puesto')->orderByDesc('pues_id_puesto')->value('pues_id_puesto');
    }

    private function institucion(string $numero = '1000001'): int
    {
        $id = $this->crearInstitucion('Afiliada');
        DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $id)->update(['ined_num_afiliacion' => $numero]);
        DB::table('institucion_nombre')->insert(['instn_id_institucion' => $id, 'instn_nombre' => "Facultad {$numero}", 'instn_fecha_inicio' => '2000-01-01']);

        return $id;
    }

    private function designar(int $puesto, int $institucion): int
    {
        $persona = (int) DB::table('persona')->insertGetId(['pers_nombre' => 'Juan', 'pers_primer_apellido' => 'Pérez', 'pers_segundo_apellido' => 'López', 'pers_genero' => 'M', 'pers_id_institucion_educativa' => $institucion], 'pers_id_persona');
        DB::table('designacion')->insert(['desi_id_puesto' => $puesto, 'desi_id_persona' => $persona, 'desi_fecha_inicio' => '2024-01-01']);

        return $persona;
    }

    private function zona(int $numero): int
    {
        return (int) DB::table('zona_regional')->where('zore_numero', $numero)->value('zore_id_zona_regional');
    }
}

<?php

namespace Tests\Feature\Directorios;

use App\Enums\Privilegio;
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
 * Directorios de puestos (M7): agregar y quitar puestos (sin huecos en el
 * orden), reordenar con reordenar_puestos_directorio, quién ocupa cada
 * puesto (o "Vacante") y los permisos.
 */
class OrdenDirectoriosTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    private Usuario $admin;

    private int $cnd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosInicialesSeeder::class);
        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $this->cnd = (int) DB::table('tipo_directorio')->where('tidi_nombre', 'Consejo Nacional Directivo')->value('tidi_id_tipo_directorio');
    }

    public function test_la_pantalla_ofrece_los_directorios_de_puestos_y_los_puestos_que_corresponden(): void
    {
        $presidente = $this->puesto('Presidente de prueba', 'Nacional');
        $this->puesto('Vocal regional de prueba', 'Regional', ['pues_id_zona_regional' => DB::table('zona_regional')->value('zore_id_zona_regional')]);

        $this->actingAs($this->admin)->get("/directorios/orden?directorio={$this->cnd}")->assertOk()
            ->assertSee('Consejo Nacional Directivo')->assertSee('Consejos Regionales')->assertSee('Coordinaciones Nacionales')
            ->assertDontSee('>Instituciones</a>', false)
            // Al Consejo Nacional se agregan puestos nacionales; los regionales no se ofrecen aquí
            ->assertSee('<option value="'.$presidente.'">', false)
            ->assertDontSee('Vocal regional de prueba');
    }

    public function test_agregar_quitar_y_reordenar_sin_huecos_con_su_bitacora(): void
    {
        [$a, $b, $c] = [$this->puesto('Presidente de prueba', 'Nacional'), $this->puesto('Secretario de prueba', 'Nacional'), $this->puesto('Tesorero de prueba', 'Nacional')];
        foreach ([$a, $b, $c] as $puesto) {
            $this->actingAs($this->admin)->post("/directorios/{$this->cnd}/puestos", ['puesto' => $puesto])
                ->assertSessionHas('estado', 'Se agregó el puesto al final del directorio.');
        }
        $this->assertSame([$a, $b, $c], $this->orden());
        $this->assertSame(['Agregado al directorio: Consejo Nacional Directivo'], app(BitacoraService::class)->recientes(1)->first()['detalles']);

        // Reordenar: el último pasa al primer lugar
        $this->actingAs($this->admin)->put("/directorios/{$this->cnd}/orden", ['orden' => [$c, $a, $b], 'movido' => $c])
            ->assertSessionHas('estado', 'Se guardó el nuevo orden.');
        $this->assertSame([$c, $a, $b], $this->orden());
        $linea = app(BitacoraService::class)->recientes(1)->first();
        $this->assertSame(['reordenó', 'el orden de los directorios'], [$linea['verbo'], $linea['objeto']]);
        $this->assertContains('Tesorero de prueba · Nacional, orden en el directorio: 3 → 1', $linea['detalles']);

        // Una lista incompleta, no
        $this->actingAs($this->admin)->put("/directorios/{$this->cnd}/orden", ['orden' => [$a, $b], 'movido' => $a])->assertSessionHas('aviso');
        $this->assertSame([$c, $a, $b], $this->orden());

        // Quitar el primero: los que seguían suben un lugar
        $this->actingAs($this->admin)->delete("/directorios/{$this->cnd}/puestos/{$c}")->assertSessionHas('estado');
        $this->assertSame([$a, $b], $this->orden());
        $this->assertSame([1, 2], DB::table('puesto_directorio')->where('pudi_id_tipo_directorio', $this->cnd)->orderBy('pudi_orden')->pluck('pudi_orden')->map(fn ($o) => (int) $o)->all());
    }

    public function test_los_puestos_institucionales_no_van_en_directorios(): void
    {
        $director = $this->puesto('Director de prueba', 'Institucional', ['pues_id_institucion_educativa' => $this->crearInstitucion('Afiliada')]);

        $this->actingAs($this->admin)->post("/directorios/{$this->cnd}/puestos", ['puesto' => $director])
            ->assertSessionHas('aviso', 'Los puestos institucionales no van en directorios.');
        $this->assertSame([], $this->orden());
    }

    public function test_cada_puesto_dice_quien_lo_ocupa_o_vacante(): void
    {
        $presidente = $this->puesto('Presidente de prueba', 'Nacional');
        DB::table('puesto_directorio')->insert(['pudi_id_puesto' => $presidente, 'pudi_id_tipo_directorio' => $this->cnd, 'pudi_orden' => 1]);
        // Sin ocupante (la pantalla lo dice "Vacante")
        $this->actingAs($this->admin)->get("/directorios/orden?directorio={$this->cnd}")->assertSee('"ocupante":null');

        $persona = (int) $this->crearCuenta(Rol::ADMINISTRADOR, [], ['pers_nombre' => 'Inés', 'pers_primer_apellido' => 'Lara', 'pers_genero' => 'F'])->usua_id_persona;
        $organizacion = (int) DB::table('organizacion')->insertGetId([
            'orga_nombre' => 'CACECA de prueba',
            'orga_id_domicilio' => DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $this->crearInstitucion('Observadora'))->value('ined_id_domicilio'),
            'orga_activo' => true,
        ], 'orga_id_organizacion');
        DB::table('persona')->where('pers_id_persona', $persona)->update(['pers_id_organizacion' => $organizacion]);
        DB::table('designacion')->insert(['desi_id_puesto' => $presidente, 'desi_id_persona' => $persona, 'desi_fecha_inicio' => '2024-01-01']);

        // En el género de quien lo ocupa
        $this->actingAs($this->admin)->get("/directorios/orden?directorio={$this->cnd}")->assertSee('Inés Lara')->assertSee('Presidenta de prueba');

        // Una persona oculta no se muestra: el puesto se ve vacante
        DB::table('persona')->where('pers_id_persona', $persona)->update(['pers_oculta_admin' => true]);
        $this->actingAs($this->admin)->get("/directorios/orden?directorio={$this->cnd}")->assertDontSee('Inés Lara')->assertSee('"ocupante":null');
    }

    public function test_sin_el_privilegio_responde_403(): void
    {
        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $this->admin->usua_id_rol)
            ->where('ropr_id_privilegio', DB::table('privilegio')->where('priv_nombre', Privilegio::GestionarDirectorios->value)->value('priv_id_privilegio'))
            ->update(['ropr_activo' => false]);

        $this->actingAs($this->admin)->get('/directorios/orden')->assertForbidden();
        $this->actingAs($this->admin)->put("/directorios/{$this->cnd}/orden", ['orden' => [1], 'movido' => 1])->assertForbidden();
    }

    /**
     * @return array<int, int>
     */
    private function orden(): array
    {
        return DB::table('puesto_directorio')->where('pudi_id_tipo_directorio', $this->cnd)->orderBy('pudi_orden')->pluck('pudi_id_puesto')->map(fn ($p) => (int) $p)->all();
    }

    /**
     * @param  array<string, mixed>  $ambito
     */
    private function puesto(string $cargo, string $nivel, array $ambito = []): int
    {
        $id = (int) DB::table('cargo')->insertGetId(['carg_nombre_m' => $cargo, 'carg_nombre_f' => str_replace(['Presidente', 'Secretario', 'Tesorero', 'Director'], ['Presidenta', 'Secretaria', 'Tesorera', 'Directora'], $cargo), 'carg_nivel' => $nivel, 'carg_activo' => true], 'carg_id_cargo');

        return (int) DB::table('puesto')->insertGetId(['pues_id_cargo' => $id] + $ambito, 'pues_id_puesto');
    }
}

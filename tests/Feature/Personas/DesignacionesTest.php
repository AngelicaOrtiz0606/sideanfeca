<?php

namespace Tests\Feature\Personas;

use App\Enums\Privilegio;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\BitacoraService;
use Database\Seeders\CatalogosInicialesSeeder;
use Database\Seeders\GradosAcademicosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreaCuentas;
use Tests\Support\CreaInstituciones;
use Tests\TestCase;

/**
 * Designaciones desde la ficha de una persona (M6): agregar (una o varias,
 * el puesto se crea si no existe), terminar (la persona deja el puesto, con
 * desactivar_designacion) y borrar la capturada por error (con motivo, con
 * eliminar_designacion_erronea). Cada acción es una sola línea en la bitácora.
 */
class DesignacionesTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    private Usuario $admin;

    private int $persona;

    private int $presidente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosInicialesSeeder::class);
        $this->seed(GradosAcademicosSeeder::class);
        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $organizacion = (int) DB::table('organizacion')->insertGetId([
            'orga_nombre' => 'CACECA de prueba',
            'orga_id_domicilio' => DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $this->crearInstitucion('Observadora'))->value('ined_id_domicilio'),
            'orga_activo' => true,
        ], 'orga_id_organizacion');
        $this->presidente = $this->cargo('Presidente de prueba', 'Presidenta de prueba', 'Nacional');

        // Una persona registrada como en la pantalla, con una designación desde 2024
        $this->actingAs($this->admin)->post('/personas', [
            'nombre' => 'Teresa', 'primer_apellido' => 'Gómez', 'segundo_apellido' => '', 'curp' => '', 'genero' => 'F',
            'pertenencia' => 'organizacion', 'organizacion' => $organizacion, 'grados' => [],
            'correo' => 'teresa@caceca.org', 'telefono' => ['lada' => '55', 'valor' => '56228300', 'extension' => ''], 'contactos' => [],
            'designaciones' => [['cargo' => $this->presidente, 'fecha_inicio' => '2024-01-01']],
        ])->assertSessionHasNoErrors();
        $this->persona = (int) DB::table('persona')->where('pers_nombre', 'Teresa')->where('pers_primer_apellido', 'Gómez')->value('pers_id_persona');
    }

    public function test_la_ficha_muestra_sus_designaciones_con_sus_acciones(): void
    {
        $this->actingAs($this->admin)->get("/personas/{$this->persona}?pestana=designaciones")->assertOk()
            ->assertSee('Designaciones')
            ->assertSee('Presidenta de prueba')
            ->assertSee('Vigente')
            ->assertSee('href="'.route('personas.designaciones.create', $this->persona).'"', false)
            ->assertSee('Terminar designación')
            ->assertSee('Borrar designación');
    }

    public function test_agregar_designaciones_crea_el_puesto_y_deja_una_linea_en_la_bitacora(): void
    {
        $this->actingAs($this->admin)->get("/personas/{$this->persona}/designaciones/create")->assertOk()
            ->assertSee('Agregar designación')->assertSee('data-vue="designaciones"', false);

        $vocal = $this->cargo('Vocal de prueba', 'Vocal de prueba', 'Regional');
        $zona = (int) DB::table('zona_regional')->where('zore_numero', 2)->value('zore_id_zona_regional');

        $this->actingAs($this->admin)->post("/personas/{$this->persona}/designaciones", [
            'designaciones' => [['cargo' => $vocal, 'zona' => $zona, 'fecha_inicio' => '2025-03-01']],
        ])->assertRedirect(route('personas.show', ['persona' => $this->persona, 'pestana' => 'designaciones']))
            ->assertSessionHas('estado', 'Se registró la designación.');

        $this->assertSame(2, DB::table('designacion')->where('desi_id_persona', $this->persona)->count());
        $this->assertDatabaseHas('puesto', ['pues_id_cargo' => $vocal, 'pues_id_zona_regional' => $zona]);

        $linea = app(BitacoraService::class)->recientes(1)->first();
        $this->assertSame('las designaciones de la persona Teresa Gómez, de CACECA de prueba', $linea['objeto']);
        $this->assertStringStartsWith('Designación: Vocal de prueba · Zona 2', $linea['detalles'][0]);
        $this->assertStringStartsWith('Puesto creado: Vocal de prueba · Zona 2', $linea['detalles'][1]);

        // Regional sin zona, y el mismo puesto ocupado: no
        $this->actingAs($this->admin)->post("/personas/{$this->persona}/designaciones", ['designaciones' => [['cargo' => $vocal, 'fecha_inicio' => '2025-03-01']]])
            ->assertSessionHasErrors(['designaciones.0.zona' => 'Designación 1: elige la zona regional.']);
        $this->actingAs($this->admin)->post("/personas/{$this->persona}/designaciones", ['designaciones' => [['cargo' => $this->presidente, 'fecha_inicio' => '2025-03-01']]])
            ->assertSessionHasErrors('designaciones.0.cargo');
    }

    public function test_un_puesto_ocupado_se_puede_reemplazar_y_la_designacion_anterior_termina_el_dia_antes(): void
    {
        $otra = (int) DB::table('persona')->insertGetId(['pers_nombre' => 'Laura', 'pers_primer_apellido' => 'Pérez', 'pers_genero' => 'F',
            'pers_id_organizacion' => DB::table('persona')->where('pers_id_persona', $this->persona)->value('pers_id_organizacion')], 'pers_id_persona');
        // Ya estuvo en los directorios (un puesto que terminó), así tiene ficha
        $vocal = (int) DB::table('puesto')->insertGetId(['pues_id_cargo' => $this->cargo('Vocal nacional', 'Vocal nacional', 'Nacional')], 'pues_id_puesto');
        DB::table('designacion')->insert(['desi_id_puesto' => $vocal, 'desi_id_persona' => $otra, 'desi_fecha_inicio' => '2020-01-01', 'desi_fecha_fin' => '2020-12-31']);

        // Laura quiere la presidencia que ocupa Teresa desde 2024: se ofrece reemplazarla
        $this->actingAs($this->admin)->post("/personas/{$otra}/designaciones", ['designaciones' => [['cargo' => $this->presidente, 'fecha_inicio' => '2026-03-01']]])
            ->assertSessionHasErrors(['designaciones.0.reemplazar_ocupante' => 'Designación 1: ese puesto lo ocupa Teresa Gómez desde el 01/01/2024. Si deja el puesto, marca «Sí, reemplazar»: su designación terminará el 28/02/2026.']);

        $this->actingAs($this->admin)->post("/personas/{$otra}/designaciones", ['designaciones' => [['cargo' => $this->presidente, 'fecha_inicio' => '2026-03-01', 'reemplazar_ocupante' => '1']]])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-02-28', DB::table('designacion')->where('desi_id_persona', $this->persona)->value('desi_fecha_fin'));
        $this->assertTrue(DB::table('designacion')->where('desi_id_persona', $otra)->where('desi_fecha_inicio', '2026-03-01')->exists());
    }

    public function test_terminar_la_designacion_libera_el_puesto_desde_esa_fecha(): void
    {
        $designacion = $this->designacion();

        // La fecha debe ser después de su inicio
        $this->actingAs($this->admin)->patch("/personas/{$this->persona}/designaciones/{$designacion}/terminar", ['fecha' => '2024-01-01'])
            ->assertSessionHasErrors(['fecha' => 'Debe ser después del día en que empezó.']);

        $this->actingAs($this->admin)->patch("/personas/{$this->persona}/designaciones/{$designacion}/terminar", ['fecha' => '2025-06-01'])
            ->assertSessionHas('estado', 'Se registró que deja el puesto. El puesto queda libre desde esa fecha.');

        // Su último día es el anterior; desde esa fecha el puesto lo puede ocupar otra persona
        $this->assertSame('2025-05-31', DB::table('designacion')->where('desi_id_designacion', $designacion)->value('desi_fecha_fin'));

        $linea = app(BitacoraService::class)->recientes(1)->first();
        $this->assertSame('modificó', $linea['verbo']);
        $this->assertSame('la designación de Teresa Gómez como Presidenta de prueba · Nacional', $linea['objeto']);
        $this->assertSame(['Deja el puesto desde: sin valor → 01/06/2025'], $linea['detalles']);

        $this->actingAs($this->admin)->get("/personas/{$this->persona}?pestana=designaciones")->assertSee('Terminada');
    }

    public function test_borrar_la_designacion_erronea_pide_motivo_y_cuida_que_no_se_quede_sin_ninguna(): void
    {
        $primera = $this->designacion();

        // Es su única designación y no tiene cuenta: no se puede quedar sin ninguna
        $this->actingAs($this->admin)->delete("/personas/{$this->persona}/designaciones/{$primera}", ['motivo' => 'Error de captura'])
            ->assertSessionHas('aviso');
        $this->assertDatabaseHas('designacion', ['desi_id_designacion' => $primera]);

        // Con otra designación, sí (y solo con un motivo de la lista)
        $vocal = $this->cargo('Vocal de prueba', 'Vocal de prueba', 'Nacional');
        $this->actingAs($this->admin)->post("/personas/{$this->persona}/designaciones", ['designaciones' => [['cargo' => $vocal, 'fecha_inicio' => '2025-01-01']]])
            ->assertSessionHasNoErrors();
        $segunda = (int) DB::table('designacion')->where('desi_id_persona', $this->persona)->max('desi_id_designacion');

        $this->actingAs($this->admin)->delete("/personas/{$this->persona}/designaciones/{$segunda}", ['motivo' => 'Porque sí'])
            ->assertSessionHasErrors('motivo');
        $this->actingAs($this->admin)->delete("/personas/{$this->persona}/designaciones/{$segunda}", ['motivo' => 'Duplicado'])
            ->assertSessionHas('estado', 'Se borró la designación. Queda en la bitácora con su motivo.');
        $this->assertDatabaseMissing('designacion', ['desi_id_designacion' => $segunda]);

        $linea = app(BitacoraService::class)->recientes(1)->first();
        $this->assertSame(['eliminó', 'una designación', 'Duplicado'], [$linea['verbo'], $linea['objeto'], $linea['motivo']]);
        $this->assertSame(['Persona: Teresa Gómez', 'Puesto: Vocal de prueba · Nacional'], $linea['detalles']);
    }

    public function test_al_editar_se_terminan_borran_y_agregan_designaciones_en_la_misma_pantalla(): void
    {
        $designacion = $this->designacion();
        $this->actingAs($this->admin)->get("/personas/{$this->persona}/edit")->assertOk()
            ->assertSee('name="existentes['.$designacion.'][hasta]"', false)->assertSee('data-vue="designaciones"', false);

        // Terminarla (su último día) y agregar otra, en un solo guardado
        $vocal = $this->cargo('Vocal de prueba', 'Vocal de prueba', 'Nacional');
        $this->guardar([
            'existentes' => [$designacion => ['hasta' => '2025-05-31']],
            'designaciones' => [['cargo' => $vocal, 'fecha_inicio' => '2025-06-01']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('2025-05-31', DB::table('designacion')->where('desi_id_designacion', $designacion)->value('desi_fecha_fin'));
        $this->assertSame(2, DB::table('designacion')->where('desi_id_persona', $this->persona)->count());

        // La fecha de inicio también se corrige (error de captura), con su bitácora
        $this->guardar(['existentes' => [$designacion => ['desde' => '2023-12-15', 'hasta' => '2025-05-31']]])->assertSessionHasNoErrors();
        $this->assertSame('2023-12-15', DB::table('designacion')->where('desi_id_designacion', $designacion)->value('desi_fecha_inicio'));
        $this->assertContains('Fecha de inicio: 01/01/2024 → 15/12/2023', app(BitacoraService::class)->recientes(1)->first()['detalles']);
        $this->guardar(['existentes' => [$designacion => ['desde' => '2025-07-01', 'hasta' => '2025-05-31']]])->assertSessionHasErrors("existentes.{$designacion}.hasta");

        // Ya terminada: la fecha de fin se corrige, pero no se quita
        $this->guardar(['existentes' => [$designacion => ['hasta' => '']]])->assertSessionHasErrors("existentes.{$designacion}.hasta");
        $this->guardar(['existentes' => [$designacion => ['hasta' => '2025-04-30']]])->assertSessionHasNoErrors();
        $this->assertSame('2025-04-30', DB::table('designacion')->where('desi_id_designacion', $designacion)->value('desi_fecha_fin'));

        // Borrar la capturada por error pide el motivo
        $this->guardar(['existentes' => [$designacion => ['borrar' => '1']]])->assertSessionHasErrors("existentes.{$designacion}.motivo");
        $this->guardar(['existentes' => [$designacion => ['borrar' => '1', 'motivo' => 'Error de captura']]])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('designacion', ['desi_id_designacion' => $designacion]);

        // No puede quedarse sin ninguna (no tiene cuenta)
        $ultima = $this->designacion();
        $this->guardar(['existentes' => [$ultima => ['borrar' => '1', 'motivo' => 'Duplicado']]])->assertSessionHasErrors('existentes');
    }

    public function test_una_designacion_de_otra_persona_no_se_toca_y_sin_privilegio_responde_403(): void
    {
        $designacion = $this->designacion();
        $otra = (int) $this->crearCuenta(Rol::ADMINISTRADOR)->usua_id_persona;
        $this->actingAs($this->admin)->patch("/personas/{$otra}/designaciones/{$designacion}/terminar", ['fecha' => '2025-06-01'])->assertNotFound();

        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $this->admin->usua_id_rol)
            ->where('ropr_id_privilegio', DB::table('privilegio')->where('priv_nombre', Privilegio::GestionarDesignaciones->value)->value('priv_id_privilegio'))
            ->update(['ropr_activo' => false]);

        $this->actingAs($this->admin)->get("/personas/{$this->persona}/designaciones/create")->assertForbidden();
        $this->actingAs($this->admin)->patch("/personas/{$this->persona}/designaciones/{$designacion}/terminar", ['fecha' => '2025-06-01'])->assertForbidden();
        $this->actingAs($this->admin)->delete("/personas/{$this->persona}/designaciones/{$designacion}", ['motivo' => 'Duplicado'])->assertForbidden();
        // La ficha se sigue viendo, pero sin las acciones
        $this->actingAs($this->admin)->get("/personas/{$this->persona}?pestana=designaciones")->assertOk()->assertDontSee('Terminar designación');
    }

    /**
     * Guarda el formulario de edición con sus datos actuales y estos cambios.
     *
     * @param  array<string, mixed>  $cambios
     */
    private function guardar(array $cambios): TestResponse
    {
        return $this->actingAs($this->admin)->put("/personas/{$this->persona}", $cambios + [
            'nombre' => 'Teresa', 'primer_apellido' => 'Gómez', 'segundo_apellido' => '', 'curp' => '', 'genero' => 'F',
            'pertenencia' => 'organizacion', 'organizacion' => DB::table('persona')->where('pers_id_persona', $this->persona)->value('pers_id_organizacion'),
            'grados' => [], 'correo' => 'teresa@caceca.org', 'telefono' => ['lada' => '55', 'valor' => '56228300', 'extension' => ''], 'contactos' => [],
        ]);
    }

    private function designacion(): int
    {
        return (int) DB::table('designacion')->where('desi_id_persona', $this->persona)->value('desi_id_designacion');
    }

    private function cargo(string $masculino, string $femenino, string $nivel): int
    {
        return (int) DB::table('cargo')->insertGetId(['carg_nombre_m' => $masculino, 'carg_nombre_f' => $femenino, 'carg_nivel' => $nivel, 'carg_activo' => true], 'carg_id_cargo');
    }
}

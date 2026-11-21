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
 * Nueva persona (M5, lote 3): se registra con su primer cargo. El puesto se
 * crea si no existe; un cargo institucional es de su institución; el puesto
 * no puede estar ocupado. Quien solo tenía cuenta se reutiliza, sin duplicarla.
 */
class NuevaPersonaTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    private Usuario $admin;

    private int $organizacion;

    /** Último movimiento de la bitácora antes de cada prueba. */
    private int $antes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosInicialesSeeder::class);
        $this->seed(GradosAcademicosSeeder::class);
        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $this->organizacion = (int) DB::table('organizacion')->insertGetId([
            'orga_nombre' => 'CACECA',
            'orga_id_domicilio' => DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $this->crearInstitucion('Observadora'))->value('ined_id_domicilio'),
            'orga_activo' => true,
        ], 'orga_id_organizacion');
        $this->antes = (int) DB::table('bitacora')->max('bita_id_bitacora');
    }

    public function test_la_lista_ofrece_nueva_persona_y_el_formulario_pide_el_cargo(): void
    {
        $this->actingAs($this->admin)->get('/personas')->assertSee('href="'.route('personas.create').'"', false)->assertSee('Nueva persona');
        $this->actingAs($this->admin)->get('/personas/create')->assertOk()
            ->assertSee('Registrar persona')->assertSee('data-vue="designaciones"', false)->assertSee('Designaciones');
    }

    public function test_registrar_con_un_cargo_nacional_crea_el_puesto_y_la_designacion(): void
    {
        $presidente = $this->cargo('Presidente', 'Nacional');

        $respuesta = $this->registrar(['cargo' => $presidente, 'fecha_inicio' => '2025-01-15']);

        $persona = (int) DB::table('persona')->where('pers_nombre', 'Anaís')->value('pers_id_persona');
        $respuesta->assertRedirect("/personas/{$persona}")->assertSessionHas('estado', 'Se registró Anaís Pérez López con su designación.');

        $puesto = (int) DB::table('puesto')->where('pues_id_cargo', $presidente)->value('pues_id_puesto');
        $this->assertDatabaseHas('designacion', ['desi_id_puesto' => $puesto, 'desi_id_persona' => $persona, 'desi_fecha_inicio' => '2025-01-15', 'desi_fecha_fin' => null]);
        $this->assertDatabaseHas('contacto', ['cont_id_persona' => $persona, 'cont_valor' => 'ana@caceca.org', 'cont_visible' => true]);
        // En la bitácora, una sola línea: la persona, con su designación y el puesto creado como detalles
        $ultima = DB::table('bitacora')->orderByDesc('bita_id_bitacora')->first();
        $this->assertSame(['Registro', 'PERSONA', $persona], [$ultima->bita_accion, $ultima->bita_tabla, (int) $ultima->bita_referencia]);
        $this->assertSame(0, DB::table('bitacora')->whereIn('bita_tabla', ['PUESTO', 'DESIGNACION'])->where('bita_id_bitacora', '>', $this->antes)->count());

        $linea = app(BitacoraService::class)->recientes(1)->first();
        $this->assertSame('a la persona Anaís Pérez López, de CACECA', $linea['objeto']);
        $this->assertSame(['Designación: Presidentea · Nacional, desde 15/01/2025', 'Puesto creado: Presidente · Nacional'], $linea['detalles']);

        // Ya aparece en Personas
        $this->actingAs($this->admin)->get('/personas')->assertSee('Anaís Pérez López');
    }

    public function test_un_puesto_ocupado_no_recibe_a_otra_persona(): void
    {
        $presidente = $this->cargo('Presidente', 'Nacional');
        $this->registrar(['cargo' => $presidente, 'fecha_inicio' => '2025-01-15'])->assertSessionHasNoErrors();

        $this->registrar(['cargo' => $presidente, 'nombre' => 'Beatriz', 'curp' => '', 'correo' => 'bea@caceca.org'])
            ->assertSessionHasErrors(['designaciones.0.cargo' => 'Designación 1: ese puesto ya lo ocupa Anaís Pérez López en esa fecha. Primero termina esa designación.']);
        $this->assertDatabaseMissing('persona', ['pers_nombre' => 'Beatriz']);
    }

    public function test_un_cargo_institucional_es_de_su_institucion_y_uno_regional_pide_zona(): void
    {
        $director = $this->cargo('Director', 'Institucional');
        $fca = $this->crearInstitucion('Afiliada');

        // Perteneciendo a una organización, no
        $this->registrar(['cargo' => $director])->assertSessionHasErrors('designaciones.0.cargo');

        $this->registrar(['cargo' => $director, 'pertenencia' => 'institucion', 'institucion' => $fca])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('puesto', ['pues_id_cargo' => $director, 'pues_id_institucion_educativa' => $fca, 'pues_titularidad' => false]);

        $consejero = $this->cargo('Consejero', 'Regional');
        $this->registrar(['cargo' => $consejero, 'nombre' => 'Carlos', 'curp' => '', 'correo' => 'carlos@caceca.org'])
            ->assertSessionHasErrors(['designaciones.0.zona' => 'Designación 1: elige la zona regional.']);
    }

    public function test_puede_registrarse_con_varias_designaciones_sin_repetir_puesto(): void
    {
        $presidente = $this->cargo('Presidente', 'Nacional');
        $consejero = $this->cargo('Consejero', 'Regional');
        $zona = (int) DB::table('zona_regional')->where('zore_numero', 2)->value('zore_id_zona_regional');

        // El mismo puesto dos veces, no
        $this->registrar(['designaciones' => [
            ['cargo' => $presidente, 'fecha_inicio' => '2025-01-15'],
            ['cargo' => $presidente, 'fecha_inicio' => '2025-02-01'],
        ]])->assertSessionHasErrors(['designaciones.1.cargo' => 'Designación 2: es el mismo puesto que la designación 1.']);

        // Dos puestos distintos (y una fila vacía, que no cuenta): dos designaciones
        $this->registrar(['designaciones' => [
            ['cargo' => $presidente, 'fecha_inicio' => '2025-01-15'],
            ['cargo' => $consejero, 'zona' => $zona, 'fecha_inicio' => '2024-06-01'],
            ['cargo' => '', 'fecha_inicio' => '2025-01-15'],
        ]])->assertSessionHasNoErrors()->assertSessionHas('estado', 'Se registró Anaís Pérez López con sus designaciones.');

        $persona = (int) DB::table('persona')->where('pers_nombre', 'Anaís')->value('pers_id_persona');
        $this->assertSame(2, DB::table('designacion')->where('desi_id_persona', $persona)->count());
        $this->assertDatabaseHas('puesto', ['pues_id_cargo' => $consejero, 'pues_id_zona_regional' => $zona]);

        // Sin ningún cargo, no
        $this->registrar(['designaciones' => [], 'nombre' => 'Carlos', 'correo' => 'carlos@caceca.org'])->assertSessionHasErrors(['designaciones' => 'Agrega al menos una designación.']);
    }

    public function test_sin_pertenencia_no_recibe_cargo(): void
    {
        $this->registrar(['cargo' => $this->cargo('Presidente', 'Nacional'), 'pertenencia' => 'ninguna'])
            ->assertSessionHasErrors(['pertenencia' => 'Para recibir una designación debe pertenecer a una institución u organización.']);
        $this->assertDatabaseMissing('persona', ['pers_nombre' => 'Anaís']);
    }

    public function test_quien_solo_tenia_cuenta_se_reutiliza_sin_duplicarse(): void
    {
        $cuenta = (int) $this->crearCuenta(Rol::ADMINISTRADOR, [], ['pers_nombre' => 'Ana', 'pers_primer_apellido' => 'Pérez', 'pers_segundo_apellido' => 'López', 'pers_genero' => 'F', 'pers_curp' => 'PELA900101MDFRPN01'])->usua_id_persona;

        // Mientras se escribe, aparece (por nombre o por CURP), con el enlace para usar sus datos
        $this->actingAs($this->admin)->getJson('/personas/coincidencias?nombre=ana%20perez')->assertOk()
            ->assertJsonFragment(['nombre' => 'Ana Pérez López', 'en_directorio' => false, 'tiene_cuenta' => true, 'enlace' => route('personas.create', ['persona' => $cuenta])]);
        $this->actingAs($this->admin)->getJson('/personas/coincidencias?curp=PELA900101MDFRPN01')->assertJsonFragment(['misma_curp' => true]);
        $this->actingAs($this->admin)->getJson('/personas/coincidencias?nombre=an')->assertExactJson([]);

        // El formulario llega con sus datos
        $this->actingAs($this->admin)->get("/personas/create?persona={$cuenta}")->assertOk()
            ->assertSee('ya tiene cuenta en el sistema')->assertSee('name="persona_existente" value="'.$cuenta.'"', false);

        $antes = DB::table('persona')->count();
        $this->registrar(['cargo' => $this->cargo('Presidente', 'Nacional'), 'persona_existente' => $cuenta, 'nombre' => 'Ana', 'curp' => 'PELA900101MDFRPN01'])
            ->assertRedirect("/personas/{$cuenta}");
        $this->assertSame($antes, DB::table('persona')->count());
        $this->assertTrue(DB::table('designacion')->where('desi_id_persona', $cuenta)->exists());

        // Ya está en los directorios: volver a "usar sus datos" lleva a su ficha
        $this->actingAs($this->admin)->get("/personas/create?persona={$cuenta}")->assertRedirect("/personas/{$cuenta}");
    }

    public function test_sin_el_privilegio_responde_403(): void
    {
        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $this->admin->usua_id_rol)
            ->where('ropr_id_privilegio', DB::table('privilegio')->where('priv_nombre', Privilegio::GestionarPersonas->value)->value('priv_id_privilegio'))
            ->update(['ropr_activo' => false]);

        $this->actingAs($this->admin)->get('/personas/create')->assertForbidden();
        $this->actingAs($this->admin)->getJson('/personas/coincidencias?nombre=ana%20perez')->assertForbidden();
        $this->registrar(['cargo' => 1])->assertForbidden();
    }

    // ---- Ayudas ----

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function registrar(array $cambios): TestResponse
    {
        // Una sola designación ('cargo' y su ámbito) se envía como la primera fila de designaciones
        if (array_key_exists('cargo', $cambios)) {
            $cambios['designaciones'] = [array_filter([
                'cargo' => $cambios['cargo'], 'zona' => $cambios['zona'] ?? null, 'coordinacion' => $cambios['coordinacion'] ?? null,
                'fecha_inicio' => $cambios['fecha_inicio'] ?? '2025-01-15',
            ], fn ($v) => $v !== null)];
            unset($cambios['cargo'], $cambios['zona'], $cambios['coordinacion'], $cambios['fecha_inicio']);
        }

        return $this->actingAs($this->admin)->post('/personas', $cambios + [
            'nombre' => 'Anaís', 'primer_apellido' => 'Pérez', 'segundo_apellido' => 'López', 'curp' => '', 'genero' => 'F',
            'pertenencia' => 'organizacion', 'organizacion' => $this->organizacion, 'grados' => [],
            'correo' => 'ana@caceca.org', 'telefono' => ['lada' => '55', 'valor' => '56228300', 'extension' => ''], 'contactos' => [],
        ]);
    }

    private function cargo(string $nombre, string $nivel): int
    {
        return (int) (DB::table('cargo')->where('carg_nombre_m', $nombre)->value('carg_id_cargo')
            ?? DB::table('cargo')->insertGetId(['carg_nombre_m' => $nombre, 'carg_nombre_f' => $nombre.'a', 'carg_nivel' => $nivel, 'carg_activo' => true], 'carg_id_cargo'));
    }
}

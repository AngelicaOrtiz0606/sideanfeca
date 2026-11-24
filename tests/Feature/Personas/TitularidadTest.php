<?php

namespace Tests\Feature\Personas;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogosInicialesSeeder;
use Database\Seeders\GradosAcademicosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreaCuentas;
use Tests\Support\CreaInstituciones;
use Tests\TestCase;

/**
 * Titular de la institución al designar: un cargo institucional se marca
 * como titular. Si ya había titular hay que confirmar el cambio: si es el
 * mismo puesto, la designación anterior termina el día antes; si es otro
 * puesto, ese deja de ser el titular (y quien lo ocupa conserva su cargo).
 */
class TitularidadTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    private Usuario $admin;

    private int $fca;

    private int $director;

    private int $coordinador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosInicialesSeeder::class);
        $this->seed(GradosAcademicosSeeder::class);
        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $this->fca = $this->crearInstitucion('Afiliada');
        DB::table('institucion_nombre')->insert(['instn_id_institucion' => $this->fca, 'instn_nombre' => 'Facultad de prueba', 'instn_fecha_inicio' => '2000-01-01']);
        $this->director = $this->cargo('Director', 'Directora');
        $this->coordinador = $this->cargo('Coordinador Académico', 'Coordinadora Académica');
    }

    public function test_sin_titular_la_persona_queda_como_titular(): void
    {
        $this->actingAs($this->admin)->getJson("/personas/titular-de/{$this->fca}")->assertOk()->assertExactJson([]);

        $this->registrar('Rosa', $this->director, '2024-01-01')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('puesto', ['pues_id_cargo' => $this->director, 'pues_id_institucion_educativa' => $this->fca, 'pues_titularidad' => true]);
        $this->actingAs($this->admin)->getJson("/personas/titular-de/{$this->fca}")->assertOk()
            ->assertExactJson(['cargo_id' => $this->director, 'cargo' => 'Directora', 'ocupante' => 'Rosa Gómez', 'desde' => '01/01/2024']);
    }

    public function test_el_mismo_puesto_pide_confirmar_y_termina_la_designacion_anterior(): void
    {
        $this->registrar('Rosa', $this->director, '2024-01-01')->assertSessionHasNoErrors();

        $this->registrar('Luis', $this->director, '2026-03-01')
            ->assertSessionHasErrors(['designaciones.0.reemplazar' => 'Designación 1: el titular de la institución es Rosa Gómez (Directora). Confirma el cambio de titular.']);

        $this->registrar('Luis', $this->director, '2026-03-01', reemplazar: true)->assertSessionHasNoErrors();

        $rosa = (int) DB::table('persona')->where('pers_nombre', 'Rosa')->value('pers_id_persona');
        $luis = (int) DB::table('persona')->where('pers_nombre', 'Luis')->value('pers_id_persona');
        $this->assertSame('2026-02-28', DB::table('designacion')->where('desi_id_persona', $rosa)->value('desi_fecha_fin'));
        $this->assertSame('2026-03-01', DB::table('designacion')->where('desi_id_persona', $luis)->value('desi_fecha_inicio'));
        $this->assertSame(1, DB::table('puesto')->where('pues_id_institucion_educativa', $this->fca)->count());
    }

    public function test_otro_puesto_pasa_a_ser_el_titular_y_el_anterior_conserva_su_cargo(): void
    {
        $this->registrar('Rosa', $this->director, '2024-01-01')->assertSessionHasNoErrors();

        $this->registrar('Luis', $this->coordinador, '2025-01-01', reemplazar: true)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('puesto', ['pues_id_cargo' => $this->director, 'pues_id_institucion_educativa' => $this->fca, 'pues_titularidad' => false]);
        $this->assertDatabaseHas('puesto', ['pues_id_cargo' => $this->coordinador, 'pues_id_institucion_educativa' => $this->fca, 'pues_titularidad' => true]);
        $rosa = (int) DB::table('persona')->where('pers_nombre', 'Rosa')->value('pers_id_persona');
        $this->assertNull(DB::table('designacion')->where('desi_id_persona', $rosa)->value('desi_fecha_fin'));
        $this->assertSame(1, DB::table('puesto')->where('pues_id_institucion_educativa', $this->fca)->where('pues_titularidad', true)->count());
    }

    public function test_sin_marcar_titular_el_puesto_no_es_titular(): void
    {
        $this->registrar('Rosa', $this->director, '2024-01-01', titular: false)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('puesto', ['pues_id_cargo' => $this->director, 'pues_id_institucion_educativa' => $this->fca, 'pues_titularidad' => false]);
    }

    private function cargo(string $masculino, string $femenino): int
    {
        return (int) DB::table('cargo')->insertGetId(['carg_nombre_m' => $masculino, 'carg_nombre_f' => $femenino, 'carg_nivel' => 'Institucional', 'carg_activo' => true], 'carg_id_cargo');
    }

    private function registrar(string $nombre, int $cargo, string $desde, bool $titular = true, bool $reemplazar = false): TestResponse
    {
        return $this->actingAs($this->admin)->post('/personas', [
            'nombre' => $nombre, 'primer_apellido' => 'Gómez', 'segundo_apellido' => '', 'curp' => '', 'genero' => $nombre === 'Rosa' ? 'F' : 'M',
            'pertenencia' => 'institucion', 'institucion' => $this->fca, 'grados' => [],
            'correo' => mb_strtolower($nombre).'@fca.mx', 'telefono' => ['lada' => '55', 'valor' => '56228300', 'extension' => ''], 'contactos' => [],
            'designaciones' => [array_filter(['cargo' => $cargo, 'fecha_inicio' => $desde, 'titular' => $titular ? '1' : null, 'reemplazar' => $reemplazar ? '1' : null])],
        ]);
    }
}

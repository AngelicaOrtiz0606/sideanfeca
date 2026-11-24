<?php

namespace Tests\Feature\Instituciones;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogosInicialesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\Support\CreaInstituciones;
use Tests\TestCase;

/**
 * Las personas de una institución: en la lista solo el titular de hoy; en la
 * consulta (pestaña Personas) todas, las de hoy primero (el titular antes que
 * nadie) y después las anteriores.
 */
class PersonasDeInstitucionTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    private Usuario $admin;

    private int $fca;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosInicialesSeeder::class);
        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $this->fca = $this->crearInstitucion('Afiliada');
        DB::table('institucion_nombre')->insert(['instn_id_institucion' => $this->fca, 'instn_nombre' => 'Facultad de prueba', 'instn_fecha_inicio' => '2000-01-01']);
    }

    public function test_la_lista_muestra_solo_al_titular_de_hoy(): void
    {
        $director = $this->puesto('Director', 'Directora', titular: true);
        $coordinador = $this->puesto('Coordinador Académico', 'Coordinadora Académica');
        $this->designar($director, $this->persona('Rosa', 'F'), '2024-01-01');
        $this->designar($coordinador, $this->persona('Luis', 'M'), '2024-01-01');

        $this->actingAs($this->admin)->get('/instituciones')->assertOk()
            ->assertSeeInOrder(['Facultad de prueba', 'Rosa Gómez', 'Directora'])
            ->assertDontSee('Luis Gómez');
    }

    public function test_sin_titular_hoy_la_lista_lo_dice(): void
    {
        $director = $this->puesto('Director', 'Directora', titular: true);
        $this->designar($director, $this->persona('Rosa', 'F'), '2020-01-01', '2023-12-31');

        $this->actingAs($this->admin)->get('/instituciones')->assertOk()
            ->assertSee('Sin titular')->assertDontSee('Rosa Gómez');
    }

    public function test_la_consulta_muestra_a_todas_las_personas_de_hoy_y_anteriores(): void
    {
        $director = $this->puesto('Director', 'Directora', titular: true);
        $coordinador = $this->puesto('Coordinador Académico', 'Coordinadora Académica');
        $this->designar($coordinador, $this->persona('Luis', 'M'), '2025-01-01');
        $this->designar($director, $this->persona('Rosa', 'F'), '2024-01-01');
        $this->designar($director, $this->persona('Pedro', 'M'), '2018-01-01', '2023-12-31');
        $oculta = $this->persona('Oculta', 'F');
        DB::table('persona')->where('pers_id_persona', $oculta)->update(['pers_oculta_admin' => true]);
        $this->designar($coordinador, $oculta, '2015-01-01', '2017-12-31');

        $this->actingAs($this->admin)->get("/instituciones/{$this->fca}?pestana=personas")->assertOk()
            ->assertSeeInOrder([
                'Rosa Gómez', 'Directora', 'Titular', 'Vigente',
                'Luis Gómez', 'Coordinador Académico', 'Vigente',
                'Pedro Gómez', 'Director', '1 de enero de 2018 – 31 de diciembre de 2023', 'Terminada',
                'Persona oculta',
            ])
            ->assertDontSee('Oculta Gómez');
    }

    private function puesto(string $masculino, string $femenino, bool $titular = false): int
    {
        $cargo = (int) DB::table('cargo')->insertGetId(['carg_nombre_m' => $masculino, 'carg_nombre_f' => $femenino, 'carg_nivel' => 'Institucional', 'carg_activo' => true], 'carg_id_cargo');

        return (int) DB::table('puesto')->insertGetId(['pues_id_cargo' => $cargo, 'pues_id_institucion_educativa' => $this->fca, 'pues_titularidad' => $titular], 'pues_id_puesto');
    }

    private function persona(string $nombre, string $genero): int
    {
        return (int) DB::table('persona')->insertGetId(['pers_nombre' => $nombre, 'pers_primer_apellido' => 'Gómez', 'pers_genero' => $genero, 'pers_id_institucion_educativa' => $this->fca], 'pers_id_persona');
    }

    private function designar(int $puesto, int $persona, string $desde, ?string $hasta = null): void
    {
        DB::table('designacion')->insert(['desi_id_puesto' => $puesto, 'desi_id_persona' => $persona, 'desi_fecha_inicio' => $desde, 'desi_fecha_fin' => $hasta]);
    }
}

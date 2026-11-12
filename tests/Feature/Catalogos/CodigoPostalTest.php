<?php

namespace Tests\Feature\Catalogos;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogosInicialesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Criterio de M3: lectura del catálogo de SEPOMEX (municipios y colonias).
 * No hay pantalla: se consulta por código postal para llenar domicilios.
 * Las pruebas crean unos cuantos municipios y colonias en la base de pruebas.
 */
class CodigoPostalTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $this->seed(CatalogosInicialesSeeder::class);

        $cdmx = (int) DB::table('entidad_federativa')->where('enfe_nombre', 'Ciudad de México')->value('enfe_id_entidad_federativa');
        $coyoacan = (int) DB::table('alcaldia_municipio')->insertGetId(
            ['almu_id_entidad_federativa' => $cdmx, 'almu_clave' => '003', 'almu_nombre' => 'Coyoacán'],
            'almu_id_alcaldia_municipio',
        );
        DB::table('colonia')->insert([
            ['colo_id_alcaldia_municipio' => $coyoacan, 'colo_codigo_postal' => '04510', 'colo_id_asenta_cpcons' => '0002', 'colo_nombre' => 'Universidad Nacional Autónoma de México'],
            ['colo_id_alcaldia_municipio' => $coyoacan, 'colo_codigo_postal' => '04510', 'colo_id_asenta_cpcons' => '0001', 'colo_nombre' => 'Ciudad Universitaria'],
            ['colo_id_alcaldia_municipio' => $coyoacan, 'colo_codigo_postal' => '04360', 'colo_id_asenta_cpcons' => '0003', 'colo_nombre' => 'Copilco Universidad'],
        ]);
    }

    public function test_trae_municipio_entidad_zona_y_colonias_del_codigo_postal(): void
    {
        $this->actingAs($this->admin)->getJson('/codigos-postales/04510')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.municipio', 'Coyoacán')
            ->assertJsonPath('0.entidad', 'Ciudad de México')
            ->assertJsonPath('0.zona', 'Zona 7 · Ciudad de México')
            ->assertJsonCount(2, '0.colonias')
            ->assertJsonPath('0.colonias.0.nombre', 'Ciudad Universitaria');
    }

    public function test_un_codigo_postal_que_no_existe_regresa_vacio(): void
    {
        $this->actingAs($this->admin)->getJson('/codigos-postales/99999')->assertOk()->assertExactJson([]);
    }

    public function test_un_codigo_postal_mal_escrito_no_se_consulta(): void
    {
        $this->actingAs($this->admin)->getJson('/codigos-postales/0451')->assertNotFound();
        $this->actingAs($this->admin)->getJson('/codigos-postales/abcde')->assertNotFound();
        $this->actingAs($this->admin)->getJson("/codigos-postales/04510' or '1'='1")->assertNotFound();
    }

    public function test_sin_sesion_no_se_puede_consultar(): void
    {
        $this->get('/codigos-postales/04510')->assertRedirect(route('login'));
    }

    public function test_el_catalogo_no_tiene_pantalla_ni_enlace_en_el_menu(): void
    {
        $this->actingAs($this->admin)->get('/inicio')->assertDontSee('/codigos-postales')->assertDontSee('/ubicaciones');
        $this->actingAs($this->admin)->get('/ubicaciones')->assertNotFound();
    }
}

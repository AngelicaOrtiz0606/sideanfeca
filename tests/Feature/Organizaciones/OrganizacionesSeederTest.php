<?php

namespace Tests\Feature\Organizaciones;

use Database\Seeders\CatalogosInicialesSeeder;
use Database\Seeders\OrganizacionesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Seeder de organizaciones: registra CACECA con su domicilio de oficina, una
 * sola vez, y no hace nada (solo avisa) si falta el catálogo de Correos.
 */
class OrganizacionesSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_registra_caceca_con_su_domicilio_una_sola_vez(): void
    {
        $this->seed(CatalogosInicialesSeeder::class);
        $mexico = (int) DB::table('entidad_federativa')->where('enfe_clave', '15')->value('enfe_id_entidad_federativa');
        $naucalpan = (int) DB::table('alcaldia_municipio')->insertGetId(['almu_id_entidad_federativa' => $mexico, 'almu_clave' => '057', 'almu_nombre' => 'Naucalpan de Juárez'], 'almu_id_alcaldia_municipio');
        $colonia = (int) DB::table('colonia')->insertGetId(['colo_id_alcaldia_municipio' => $naucalpan, 'colo_codigo_postal' => '53390', 'colo_id_asenta_cpcons' => '0001', 'colo_nombre' => 'Lomas de Sotelo'], 'colo_id_colonia');

        $this->seed(OrganizacionesSeeder::class);
        $this->seed(OrganizacionesSeeder::class);

        $this->assertSame(1, DB::table('organizacion')->where('orga_nombre', 'CACECA')->count());
        $caceca = DB::table('organizacion')->join('domicilio', 'domi_id_domicilio', '=', 'orga_id_domicilio')->where('orga_nombre', 'CACECA')->first();
        $this->assertSame(
            [$colonia, 'Blvd. Manuel Ávila Camacho', '5', 'Torre B, Piso 10, Suite 1000', true],
            [(int) $caceca->domi_id_colonia, $caceca->domi_calle, $caceca->domi_num_ext, $caceca->domi_num_int, (bool) $caceca->orga_activo],
        );
    }

    public function test_sin_el_catalogo_de_correos_no_registra_nada(): void
    {
        $this->seed(OrganizacionesSeeder::class);

        $this->assertDatabaseMissing('organizacion', ['orga_nombre' => 'CACECA']);
    }
}

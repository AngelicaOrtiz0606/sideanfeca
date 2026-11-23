<?php

namespace Tests\Feature\Catalogos;

use App\Models\Rol;
use Database\Seeders\CargosBaseSeeder;
use Database\Seeders\CatalogosInicialesSeeder;
use Database\Seeders\GradosAcademicosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\Support\CreaInstituciones;
use Tests\TestCase;

/**
 * El botón Borrar de cada lista abre la ventana "dialogo-borrar". Las listas
 * usan $id en cada fila; si la ventana tomara ese $id, su identificador sería
 * el número del último registro y el botón no la encontraría (no hacía nada).
 */
class DialogoBorrarTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    public function test_cada_lista_tiene_la_ventana_para_borrar_con_su_identificador(): void
    {
        $this->seed(CatalogosInicialesSeeder::class);
        $this->seed(GradosAcademicosSeeder::class);
        $this->seed(CargosBaseSeeder::class);
        $cargo = (int) DB::table('cargo')->where('carg_nombre_m', 'Presidente')->value('carg_id_cargo');
        DB::table('puesto')->insert(['pues_id_cargo' => $cargo]);
        DB::table('organizacion')->insert([
            'orga_nombre' => 'Organización de prueba',
            'orga_id_domicilio' => DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $this->crearInstitucion('Observadora'))->value('ined_id_domicilio'),
            'orga_activo' => true,
        ]);
        $admin = $this->crearCuenta(Rol::ADMINISTRADOR);

        foreach (['/puestos', '/cargos', '/grados-academicos', '/coordinaciones', '/zonas', '/organizaciones', '/instituciones', '/personas'] as $ruta) {
            $this->actingAs($admin)->get($ruta)->assertOk()
                ->assertSee('<dialog id="dialogo-borrar" class="dialogo"', false);
        }
    }
}

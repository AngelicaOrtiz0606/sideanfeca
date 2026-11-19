<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\Support\CreaInstituciones;
use Tests\TestCase;

/**
 * Las listas se muestran de 10 en 10, conservando los filtros. El aviso de
 * nombre repetido sigue revisando contra todos los registros, no solo la página.
 */
class PaginacionTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
    }

    public function test_los_cargos_se_muestran_de_10_en_10_y_conservan_el_filtro(): void
    {
        foreach (range(1, 12) as $n) {
            DB::table('cargo')->insert(['carg_nombre_m' => sprintf('Cargo %02d', $n), 'carg_nombre_f' => sprintf('Carga %02d', $n), 'carg_nivel' => 'Nacional', 'carg_activo' => true]);
        }

        $primera = $this->actingAs($this->admin)->get('/cargos')->assertOk();
        $this->assertSame(10, substr_count($primera->getContent(), 'class="tabla__principal"'));
        $primera->assertSee('Mostrando 1–10 de 12')->assertSee('Cargo 10')->assertDontSee('>Cargo 11<', false);

        $segunda = $this->actingAs($this->admin)->get('/cargos?page=2')->assertOk();
        $this->assertSame(2, substr_count($segunda->getContent(), 'class="tabla__principal"'));
        $segunda->assertSee('Mostrando 11–12 de 12');

        // Los enlaces de página conservan el filtro
        $this->actingAs($this->admin)->get('/cargos?nivel=Nacional')->assertSee('nivel=Nacional&amp;page=2', false);

        // El aviso de repetido conoce los 12 aunque la página muestre 10
        $primera->assertSee('Cargo 12', false);
    }

    public function test_una_pagina_que_ya_no_existe_muestra_la_ultima(): void
    {
        foreach (range(1, 3) as $n) {
            DB::table('cargo')->insert(['carg_nombre_m' => "Cargo {$n}", 'carg_nombre_f' => "Carga {$n}", 'carg_nivel' => 'Nacional', 'carg_activo' => true]);
        }

        $this->actingAs($this->admin)->get('/cargos?page=5')->assertOk()->assertSee('Mostrando 1–3 de 3');
    }

    public function test_las_instituciones_se_muestran_de_10_en_10(): void
    {
        foreach (range(1, 11) as $n) {
            $id = $this->crearInstitucion('Observadora');
            DB::table('institucion_nombre')->insert(['instn_id_institucion' => $id, 'instn_nombre' => sprintf('Institución %02d', $n), 'instn_fecha_inicio' => '2020-01-01']);
        }

        $this->actingAs($this->admin)->get('/instituciones')->assertOk()->assertSee('Mostrando 1–10 de 11');
    }
}

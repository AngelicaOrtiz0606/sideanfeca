<?php

namespace Tests\Feature\Catalogos;

use Database\Seeders\CargosBaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cargos base de ANFECA: se cargan con su nivel, el seeder se puede volver a
 * correr sin duplicar, y respeta los cargos que ya existían.
 */
class CargosBaseSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_carga_los_cargos_base_con_su_nivel(): void
    {
        $this->seed(CargosBaseSeeder::class);

        $this->assertSame(39, DB::table('cargo')->count());
        $this->assertSame(['Nacional' => 8, 'Regional' => 4, 'Institucional' => 27],
            DB::table('cargo')->selectRaw('carg_nivel, count(*) as total')->groupBy('carg_nivel')->pluck('total', 'carg_nivel')->map(fn ($n) => (int) $n)
                ->sortBy(fn ($n, $nivel) => array_search($nivel, ['Nacional', 'Regional', 'Institucional']))->all());
        $this->assertDatabaseHas('cargo', ['carg_nombre_m' => 'Coordinador Regional', 'carg_nombre_f' => 'Coordinadora Regional', 'carg_nivel' => 'Regional', 'carg_activo' => true]);
        $this->assertDatabaseHas('cargo', ['carg_nombre_m' => 'Representante de ANFECA ante ALAFEC', 'carg_nombre_f' => 'Representante de ANFECA ante ALAFEC', 'carg_nivel' => 'Nacional']);
    }

    public function test_se_puede_volver_a_correr_y_respeta_los_que_ya_existian(): void
    {
        // Ya existía "director" escrito distinto: no se duplica ni se cambia
        DB::table('cargo')->insert(['carg_nombre_m' => 'DIRECTOR', 'carg_nombre_f' => 'DIRECTORA', 'carg_nivel' => 'Institucional', 'carg_activo' => false]);

        $this->seed(CargosBaseSeeder::class);
        $this->seed(CargosBaseSeeder::class);

        $this->assertSame(39, DB::table('cargo')->count());
        $this->assertDatabaseHas('cargo', ['carg_nombre_m' => 'DIRECTOR', 'carg_activo' => false]);
        $this->assertDatabaseMissing('cargo', ['carg_nombre_m' => 'Director']);
    }
}

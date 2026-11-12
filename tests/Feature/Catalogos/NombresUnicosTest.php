<?php

namespace Tests\Feature\Catalogos;

use App\Models\Rol;
use App\Support\TextoCatalogo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Nombres de catálogo: se guardan como se escriben (sin espacios de sobra) y
 * se comparan sin importar mayúsculas, acentos ni espacios. La ñ es otra letra.
 * Las pantallas reciben la lista de nombres para avisar mientras se escribe.
 */
class NombresUnicosTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    public function test_la_clave_ignora_mayusculas_acentos_y_espacios_pero_no_la_ene(): void
    {
        $this->assertSame('presidente', TextoCatalogo::clave('PrEsIdEnTe'));
        $this->assertSame('presidente', TextoCatalogo::clave('  Presidénte '));
        $this->assertSame('coordinador nacional', TextoCatalogo::clave("Coordinador \t  NACIONAL"));
        $this->assertSame('vinculacion y pinguino', TextoCatalogo::clave('Vinculación y Pingüino'));
        $this->assertNotSame(TextoCatalogo::clave('Año'), TextoCatalogo::clave('Ano'));
        $this->assertSame('ANFECA Región Sur', TextoCatalogo::limpiar('  ANFECA   Región  Sur '));
    }

    public function test_se_guarda_tal_como_se_escribe_sin_espacios_de_sobra(): void
    {
        $admin = $this->crearCuenta(Rol::ADMINISTRADOR);

        $this->actingAs($admin)->post('/cargos', ['masculino' => '  Enlace   ANFECA ', 'femenino' => 'Enlace ANFECA', 'nivel' => 'Nacional'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cargo', ['carg_nombre_m' => 'Enlace ANFECA']);
    }

    public function test_las_ventanas_reciben_los_nombres_para_avisar_mientras_se_escribe(): void
    {
        $admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        DB::table('cargo')->insert(['carg_nombre_m' => 'Presidente', 'carg_nombre_f' => 'Presidenta', 'carg_nivel' => 'Nacional']);
        DB::table('nivel_academico')->insert(['niac_nombre' => 'Doctorado', 'niac_abr_m' => 'Dr.', 'niac_abr_f' => 'Dra.']);

        $this->actingAs($admin)->get('/cargos')
            ->assertSee('data-mensaje-repetido="Ya existe el cargo «:existente».', false)
            ->assertSee('&quot;Presidenta&quot;', false);

        $this->actingAs($admin)->get('/grados-academicos')
            ->assertSee('data-mensaje-repetido="Ya existe el grado «:existente».', false)
            ->assertSee('&quot;Doctorado&quot;', false);

        $this->actingAs($admin)->get('/coordinaciones')->assertSee('data-existentes=', false)
            ->assertSee('class="campo__obligatorio"', false)
            ->assertSeeText('Los campos con')
            ->assertSee('(obligatorio)', false);
        $this->actingAs($admin)->get('/zonas')->assertSee('data-existentes=', false);
    }

    public function test_el_menu_dice_grados_academicos(): void
    {
        $this->actingAs($this->crearCuenta(Rol::ADMINISTRADOR))->get('/inicio')
            ->assertSeeText('Grados académicos')
            ->assertDontSeeText('Niveles académicos')
            ->assertSee(route('grados.index'));
    }
}

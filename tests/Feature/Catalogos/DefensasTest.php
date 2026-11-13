<?php

namespace Tests\Feature\Catalogos;

use App\Models\Rol;
use App\Models\Usuario;
use App\Services\Catalogos\CargoService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Defensas contra inyecciones y choques al guardar al mismo tiempo.
 */
class DefensasTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
    }

    public function test_en_el_servidor_oficial_el_navegador_solo_ejecuta_scripts_del_sistema(): void
    {
        config(['app.debug' => false]);

        $politica = $this->get('/login')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self'", $politica);
        $this->assertStringContainsString("object-src 'none'", $politica);
        $this->assertStringContainsString("form-action 'self'", $politica);
        $this->assertStringContainsString("frame-ancestors 'none'", $politica);
        $this->assertStringNotContainsString('unsafe-inline', $politica);
        $this->assertStringNotContainsString('unsafe-eval', $politica);
    }

    public function test_con_depuracion_activa_solo_se_impide_el_marco(): void
    {
        config(['app.debug' => true]);

        $this->get('/login')->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
    }

    public function test_las_pantallas_no_tienen_scripts_escritos_dentro(): void
    {
        DB::table('cargo')->insert(['carg_nombre_m' => 'Presidente', 'carg_nombre_f' => 'Presidenta', 'carg_nivel' => 'Nacional']);

        foreach (['/inicio', '/zonas', '/coordinaciones', '/cargos', '/grados-academicos', '/instituciones', '/instituciones/create'] as $direccion) {
            $html = $this->actingAs($this->admin)->get($direccion)->getContent();

            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $html, "{$direccion} tiene un script escrito dentro.");
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $html, "{$direccion} tiene un atributo on…=.");
            $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $html, "{$direccion} tiene estilos escritos dentro.");
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function textosPeligrosos(): array
    {
        return [
            'etiqueta de HTML' => ['<script>alert(1)</script>', 'El texto no puede llevar los símbolos < ni >.'],
            'fórmula con =' => ['=HYPERLINK("http://x","clic")', 'El texto no puede empezar con =, +, - ni @.'],
            'fórmula con +' => ['+1+1', 'El texto no puede empezar con =, +, - ni @.'],
            'fórmula con -' => ['-2+3', 'El texto no puede empezar con =, +, - ni @.'],
            'fórmula con @' => ['@SUMA(A1)', 'El texto no puede empezar con =, +, - ni @.'],
            'salto de línea' => ["Presidente\nGeneral", 'El texto tiene caracteres que no se permiten (por ejemplo, saltos de línea).'],
            'carácter nulo' => ["Presi\0dente", 'El texto tiene caracteres que no se permiten (por ejemplo, saltos de línea).'],
        ];
    }

    #[DataProvider('textosPeligrosos')]
    public function test_los_catalogos_rechazan_textos_peligrosos(string $texto, string $mensaje): void
    {
        $this->actingAs($this->admin)->post('/cargos', ['masculino' => $texto, 'femenino' => 'Algo', 'nivel' => 'Nacional'])
            ->assertSessionHasErrors(['masculino' => $mensaje]);

        $this->actingAs($this->admin)->post('/grados-academicos', ['nombre' => $texto, 'masculina' => 'X.', 'femenina' => 'X.'])
            ->assertSessionHasErrors('nombre');

        $this->assertSame(0, DB::table('cargo')->count());
    }

    public function test_textos_normales_con_guiones_y_signos_si_se_aceptan(): void
    {
        $this->actingAs($this->admin)->post('/cargos', ['masculino' => 'Coordinador – Región Sur (ANFECA)', 'femenino' => 'Coordinadora – Región Sur (ANFECA)', 'nivel' => 'Regional'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cargo', ['carg_nombre_m' => 'Coordinador – Región Sur (ANFECA)']);
    }

    public function test_las_pantallas_avisan_mientras_se_escribe_un_texto_peligroso(): void
    {
        $this->actingAs($this->admin)->get('/cargos')->assertSee('data-texto-seguro', false);
        $this->actingAs($this->admin)->get('/coordinaciones')->assertSee('id="coordinacion-descripcion" data-texto-seguro', false);
    }

    public function test_si_la_base_rechaza_un_repetido_al_mismo_tiempo_regresa_con_un_mensaje_claro(): void
    {
        $this->mock(CargoService::class)->shouldReceive('registrar')
            ->andThrow(new UniqueConstraintViolationException('pgsql', 'insert into cargo …', [], new PDOException('duplicate key')));

        $this->actingAs($this->admin)->from('/cargos')
            ->post('/cargos', ['masculino' => 'Tesorero', 'femenino' => 'Tesorera', 'nivel' => 'Nacional', '_dialogo' => 'cargo'])
            ->assertRedirect('/cargos')
            ->assertSessionHasErrors(['general' => 'Alguien acaba de registrar ese mismo dato. Revisa la lista e inténtalo de nuevo.'])
            ->assertSessionHasInput('masculino', 'Tesorero');
    }

    public function test_si_la_base_rechaza_un_borrado_por_datos_relacionados_regresa_con_un_aviso(): void
    {
        $id = (int) DB::table('cargo')->insertGetId(['carg_nombre_m' => 'Tesorero', 'carg_nombre_f' => 'Tesorera', 'carg_nivel' => 'Nacional'], 'carg_id_cargo');
        $llaveForanea = new class('violates foreign key constraint') extends PDOException
        {
            protected $code = '23503';
        };
        $this->mock(CargoService::class)->shouldReceive('eliminar')
            ->andThrow(new QueryException('pgsql', 'delete from cargo …', [], $llaveForanea));

        $this->actingAs($this->admin)->from('/cargos')->delete("/cargos/{$id}", ['motivo' => 'Duplicado'])
            ->assertRedirect('/cargos')
            ->assertSessionHas('aviso', 'No se pudo completar porque el registro ya tiene datos relacionados. Revisa la lista.');
    }
}

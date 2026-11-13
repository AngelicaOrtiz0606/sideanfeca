<?php

namespace Tests\Feature\Instituciones;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogosInicialesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Número de afiliación automático: año (2 dígitos) + zona (2 dígitos) +
 * consecutivo (3 dígitos), con su propia cuenta para cada año y zona.
 * Lo asigna el servidor al guardar; también se puede escribir a mano.
 */
class NumeroAfiliacionTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private Usuario $admin;

    private int $colonia;

    private int $unam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $this->seed(CatalogosInicialesSeeder::class);

        $cdmx = (int) DB::table('entidad_federativa')->where('enfe_nombre', 'Ciudad de México')->value('enfe_id_entidad_federativa');
        $municipio = (int) DB::table('alcaldia_municipio')->insertGetId(['almu_id_entidad_federativa' => $cdmx, 'almu_clave' => '003', 'almu_nombre' => 'Coyoacán'], 'almu_id_alcaldia_municipio');
        $this->colonia = (int) DB::table('colonia')->insertGetId(['colo_id_alcaldia_municipio' => $municipio, 'colo_codigo_postal' => '04510', 'colo_id_asenta_cpcons' => '0001', 'colo_nombre' => 'Ciudad Universitaria'], 'colo_id_colonia');

        $this->actingAs($this->admin)->post('/instituciones', ['tipo' => 'Universidad', 'participacion' => 'Matriz', 'desde' => '1959-01-01', 'razon_social' => 'Universidad Nacional Autónoma de México', 'sector' => 'Publica'])
            ->assertSessionHasNoErrors();
        $this->unam = (int) DB::table('institucion_nombre')->where('instn_nombre', 'Universidad Nacional Autónoma de México')->value('instn_id_institucion');
    }

    public function test_el_numero_automatico_es_ano_zona_y_consecutivo(): void
    {
        $this->actingAs($this->admin)->post('/instituciones', $this->afiliada('Facultad Uno', '2026-02-10', 7))
            ->assertSessionHas('estado', 'Se registró Facultad Uno con el número 2607001.');

        $this->assertSame('2607001', $this->numeroDe('Facultad Uno'));
    }

    public function test_cada_ano_y_zona_lleva_su_propia_cuenta(): void
    {
        $this->registrar('Facultad Uno', '2026-02-10', 7);
        $this->registrar('Facultad Dos', '2026-08-01', 7);
        $this->registrar('Facultad Tres', '2026-08-01', 3);
        $this->registrar('Facultad Cuatro', '2025-12-31', 7);

        $this->assertSame(['2607001', '2607002', '2603001', '2507001'], [
            $this->numeroDe('Facultad Uno'), $this->numeroDe('Facultad Dos'), $this->numeroDe('Facultad Tres'), $this->numeroDe('Facultad Cuatro'),
        ]);
    }

    public function test_sigue_despues_del_mas_alto_aunque_haya_huecos_y_numeros_a_mano(): void
    {
        // Capturado a mano (por ejemplo, una afiliada antigua): el consecutivo sigue después
        $this->actingAs($this->admin)->post('/instituciones', ['numero_modo' => 'manual', 'numero' => '2607010'] + $this->afiliada('Facultad Antigua', '2026-01-05', 7))
            ->assertSessionHasNoErrors();

        $this->registrar('Facultad Nueva', '2026-03-01', 7);

        $this->assertSame('2607011', $this->numeroDe('Facultad Nueva'));
    }

    public function test_el_numero_a_mano_que_no_sigue_la_regla_se_acepta_si_no_esta_repetido(): void
    {
        $this->actingAs($this->admin)->post('/instituciones', ['numero_modo' => 'manual', 'numero' => '1234567'] + $this->afiliada('Facultad Antigua', '2026-01-05', 7))
            ->assertSessionHasNoErrors();
        $this->assertSame('1234567', $this->numeroDe('Facultad Antigua'));

        $this->actingAs($this->admin)->post('/instituciones', ['numero_modo' => 'manual', 'numero' => '1234567'] + $this->afiliada('Otra', '2026-01-05', 7))
            ->assertSessionHasErrors(['numero' => 'El número 1234567 ya pertenece a otra institución.']);
    }

    public function test_en_modo_automatico_se_ignora_un_numero_enviado(): void
    {
        $this->actingAs($this->admin)->post('/instituciones', ['numero_modo' => 'automatico', 'numero' => '9999999'] + $this->afiliada('Facultad Uno', '2026-02-10', 7))
            ->assertSessionHasNoErrors();

        $this->assertSame('2607001', $this->numeroDe('Facultad Uno'));
    }

    public function test_al_llegar_a_999_pide_escribir_el_numero(): void
    {
        $this->actingAs($this->admin)->post('/instituciones', ['numero_modo' => 'manual', 'numero' => '2607999'] + $this->afiliada('Facultad Ultima', '2026-01-05', 7))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post('/instituciones', $this->afiliada('Facultad Sin Lugar', '2026-02-10', 7))
            ->assertSessionHasErrors(['numero' => 'Ya no hay consecutivos para ese año y zona. Escribe el número.']);
        $this->assertNull($this->numeroDe('Facultad Sin Lugar'));
    }

    public function test_la_pantalla_muestra_el_numero_automatico_y_la_opcion_de_escribirlo(): void
    {
        $this->actingAs($this->admin)->get('/instituciones/create')->assertOk()
            ->assertSee('name="numero_modo" value="automatico"', false)
            ->assertSee('data-numero-afiliacion', false)
            ->assertSeeText('Escribir el número');
    }

    /**
     * @return array<string, mixed>
     */
    private function afiliada(string $nombre, string $desde, int $zona): array
    {
        return ['tipo' => 'Facultad', 'universidad' => $this->unam, 'participacion' => 'Afiliada', 'numero_modo' => 'automatico', 'desde' => $desde,
            'razon_social' => $nombre, 'sector' => 'Publica', 'colonia' => $this->colonia, 'calle' => 'Av. Universidad', 'num_ext' => '3000',
            'zona' => (int) DB::table('zona_regional')->where('zore_numero', $zona)->value('zore_id_zona_regional')];
    }

    private function registrar(string $nombre, string $desde, int $zona): void
    {
        $this->actingAs($this->admin)->post('/instituciones', $this->afiliada($nombre, $desde, $zona))->assertSessionHasNoErrors();
    }

    private function numeroDe(string $nombre): ?string
    {
        $id = DB::table('institucion_nombre')->where('instn_nombre', $nombre)->value('instn_id_institucion');

        return $id === null ? null : DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $id)->value('ined_num_afiliacion');
    }
}

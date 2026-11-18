<?php

namespace Tests\Feature\Organizaciones;

use App\Enums\Privilegio;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogosInicialesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Organizaciones (M4, lote 3): nombre sin repetirse, domicilio obligatorio,
 * sitio web; activar y desactivar; borrar solo sin personas.
 */
class OrganizacionesTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private Usuario $admin;

    private int $colonia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $this->seed(CatalogosInicialesSeeder::class);

        $cdmx = (int) DB::table('entidad_federativa')->where('enfe_nombre', 'Ciudad de México')->value('enfe_id_entidad_federativa');
        $municipio = (int) DB::table('alcaldia_municipio')->insertGetId(['almu_id_entidad_federativa' => $cdmx, 'almu_clave' => '015', 'almu_nombre' => 'Cuauhtémoc'], 'almu_id_alcaldia_municipio');
        $this->colonia = (int) DB::table('colonia')->insertGetId(['colo_id_alcaldia_municipio' => $municipio, 'colo_codigo_postal' => '06600', 'colo_id_asenta_cpcons' => '0002', 'colo_nombre' => 'Juárez'], 'colo_id_colonia');
    }

    public function test_registrar_una_organizacion_con_su_domicilio_y_bitacora(): void
    {
        $this->actingAs($this->admin)->post('/organizaciones', $this->caceca())
            ->assertRedirect('/organizaciones')
            ->assertSessionHas('estado', 'Se registró CACECA.');

        $organizacion = DB::table('organizacion')->where('orga_nombre', 'CACECA')->first();
        $this->assertSame('https://www.caceca.org', $organizacion->orga_sitio_web);
        $this->assertTrue((bool) $organizacion->orga_activo);
        $this->assertDatabaseHas('domicilio', ['domi_id_domicilio' => $organizacion->orga_id_domicilio, 'domi_id_colonia' => $this->colonia, 'domi_calle' => 'Av. Reforma', 'domi_num_ext' => '222']);

        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Registro', 'ORGANIZACION', (int) $organizacion->orga_id_organizacion], [$bitacora->bita_accion, $bitacora->bita_tabla, (int) $bitacora->bita_referencia]);
    }

    public function test_la_lista_muestra_su_ubicacion_y_el_menu_la_ofrece(): void
    {
        $this->registrar($this->caceca());

        $this->actingAs($this->admin)->get('/organizaciones')->assertOk()
            ->assertSee('CACECA')
            ->assertSee('caceca.org')
            ->assertSee('Cuauhtémoc, Ciudad de México')
            ->assertSee('href="'.route('organizaciones.index').'"', false);
    }

    public function test_el_nombre_no_se_repite_aunque_cambien_mayusculas_acentos_o_espacios(): void
    {
        $this->registrar(['nombre' => 'Asociación de Contadores'] + $this->caceca());

        $this->actingAs($this->admin)->post('/organizaciones', ['nombre' => '  ASOCIACION   de contadores '] + $this->caceca())
            ->assertSessionHasErrors(['nombre' => 'Ya existe la organización «Asociación de Contadores».']);

        $this->assertSame(1, DB::table('organizacion')->count());
    }

    public function test_el_domicilio_es_obligatorio_y_el_sitio_web_solo_http(): void
    {
        $this->actingAs($this->admin)->post('/organizaciones', ['nombre' => 'CACECA'])
            ->assertSessionHasErrors(['colonia' => 'Escribe el código postal y elige la colonia.', 'calle' => 'Escribe la calle.', 'num_ext' => 'Escribe el número exterior.']);

        $this->actingAs($this->admin)->post('/organizaciones', ['sitio_web' => 'javascript:alert(1)'] + $this->caceca())
            ->assertSessionHasErrors('sitio_web');

        $this->assertSame(0, DB::table('organizacion')->count());
    }

    public function test_los_textos_peligrosos_se_rechazan(): void
    {
        $this->actingAs($this->admin)->post('/organizaciones', ['nombre' => '<script>alert(1)</script>'] + $this->caceca())->assertSessionHasErrors('nombre');
        $this->actingAs($this->admin)->post('/organizaciones', ['calle' => '=HYPERLINK("x")'] + $this->caceca())->assertSessionHasErrors('calle');

        $this->assertSame(0, DB::table('organizacion')->count());
    }

    public function test_editar_registra_cada_cambio_en_la_bitacora(): void
    {
        $id = $this->registrar($this->caceca());

        $this->actingAs($this->admin)->put("/organizaciones/{$id}", ['nombre' => 'CACECA, A.C.', 'sitio_web' => '', 'num_ext' => '224'] + $this->caceca())
            ->assertRedirect('/organizaciones')
            ->assertSessionHasNoErrors();

        $organizacion = DB::table('organizacion')->where('orga_id_organizacion', $id)->first();
        $this->assertSame(['CACECA, A.C.', null], [$organizacion->orga_nombre, $organizacion->orga_sitio_web]);
        $this->assertSame('224', DB::table('domicilio')->where('domi_id_domicilio', $organizacion->orga_id_domicilio)->value('domi_num_ext'));

        $campos = array_column($this->detalles((int) $this->ultimaBitacora()->bita_id_bitacora), 0);
        $this->assertSame(['Nombre de la organización', 'Sitio web', 'Domicilio'], $campos);

        // Al editarse a sí misma no se considera repetida
        $this->actingAs($this->admin)->put("/organizaciones/{$id}", ['nombre' => 'caceca, a.c.'] + $this->caceca())->assertSessionHasNoErrors();
    }

    public function test_desactivar_y_activar(): void
    {
        $id = $this->registrar($this->caceca());

        $this->actingAs($this->admin)->patch("/organizaciones/{$id}/estado", ['activa' => '0'])
            ->assertSessionHas('estado', 'Se desactivó CACECA. Sus personas la conservan.');
        $this->assertFalse((bool) DB::table('organizacion')->where('orga_id_organizacion', $id)->value('orga_activo'));
        $this->assertSame('Desactivacion', $this->ultimaBitacora()->bita_accion);

        $this->actingAs($this->admin)->patch("/organizaciones/{$id}/estado", ['activa' => '1'])->assertSessionHas('estado', 'Se activó CACECA.');
        $this->assertTrue((bool) DB::table('organizacion')->where('orga_id_organizacion', $id)->value('orga_activo'));
    }

    public function test_sin_personas_se_borra_con_su_domicilio_y_motivo(): void
    {
        $id = $this->registrar($this->caceca());
        $domicilio = DB::table('organizacion')->where('orga_id_organizacion', $id)->value('orga_id_domicilio');

        $this->actingAs($this->admin)->delete("/organizaciones/{$id}", ['motivo' => 'Error de captura'])
            ->assertSessionHas('estado', 'Se borró CACECA.');

        $this->assertDatabaseMissing('organizacion', ['orga_id_organizacion' => $id]);
        $this->assertDatabaseMissing('domicilio', ['domi_id_domicilio' => $domicilio]);
        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Eliminacion', 'Error de captura'], [$bitacora->bita_accion, $bitacora->bita_motivo]);
        $this->assertSame([['Nombre de la organización', 'CACECA', null]], $this->detalles((int) $bitacora->bita_id_bitacora));
    }

    public function test_con_personas_no_se_borra_y_lo_explica(): void
    {
        $id = $this->registrar($this->caceca());
        DB::table('persona')->where('pers_id_persona', $this->admin->usua_id_persona)->update(['pers_id_organizacion' => $id]);

        $this->actingAs($this->admin)->delete("/organizaciones/{$id}", ['motivo' => 'Error de captura'])
            ->assertSessionHas('aviso', 'No se puede borrar porque 1 persona pertenece a esta organización. Si ya no participa, desactívala: su historial se conserva.');
        $this->assertDatabaseHas('organizacion', ['orga_id_organizacion' => $id]);

        // En la lista, el botón Borrar aparece en gris con la explicación
        $this->actingAs($this->admin)->get('/organizaciones')->assertSee('accion--bloqueada', false);
    }

    public function test_sin_el_privilegio_responde_403(): void
    {
        $id = $this->registrar($this->caceca());

        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $this->admin->usua_id_rol)
            ->where('ropr_id_privilegio', DB::table('privilegio')->where('priv_nombre', Privilegio::GestionarInstituciones->value)->value('priv_id_privilegio'))
            ->update(['ropr_activo' => false]);

        $this->actingAs($this->admin)->get('/organizaciones')->assertForbidden();
        $this->actingAs($this->admin)->post('/organizaciones', ['nombre' => 'Otra'] + $this->caceca())->assertForbidden();
        $this->actingAs($this->admin)->put("/organizaciones/{$id}", $this->caceca())->assertForbidden();
        $this->actingAs($this->admin)->delete("/organizaciones/{$id}", ['motivo' => 'Error de captura'])->assertForbidden();
        $this->assertSame(1, DB::table('organizacion')->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function caceca(): array
    {
        return ['nombre' => 'CACECA', 'sitio_web' => 'https://www.caceca.org', 'colonia' => $this->colonia, 'calle' => 'Av. Reforma', 'num_ext' => '222'];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function registrar(array $datos): int
    {
        $this->actingAs($this->admin)->post('/organizaciones', $datos)->assertSessionHasNoErrors();

        return (int) DB::table('organizacion')->where('orga_nombre', $datos['nombre'])->value('orga_id_organizacion');
    }

    private function ultimaBitacora(): object
    {
        return DB::table('bitacora')->orderByDesc('bita_id_bitacora')->first();
    }

    /**
     * @return array<int, array{0: string, 1: ?string, 2: ?string}>
     */
    private function detalles(int $idBitacora): array
    {
        return DB::table('bitacora_detalle')->where('bide_id_bitacora', $idBitacora)->orderBy('bide_id_detalle')->get()
            ->map(fn (object $d) => [$d->bide_campo, $d->bide_anterior, $d->bide_nuevo])->all();
    }
}

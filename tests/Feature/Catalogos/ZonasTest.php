<?php

namespace Tests\Feature\Catalogos;

use App\Enums\Privilegio;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Criterios de M3 (zonas regionales):
 * - Zona: número del 1 al 7 y nombre únicos.
 * - Cada cambio queda en la bitácora, en la misma transacción.
 */
class ZonasTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
    }

    public function test_el_admin_ve_la_lista_con_sus_cifras(): void
    {
        $norte = $this->zona(2, 'Norte');
        $this->entidad('19', 'Nuevo León', $norte);
        $this->entidad('28', 'Tamaulipas', $norte);

        $this->actingAs($this->admin)->get('/zonas')
            ->assertOk()
            ->assertSeeText('Zonas regionales')
            ->assertSeeText('Norte')
            ->assertSee('data-etiqueta="Entidades">2<', false);
    }

    public function test_sin_el_privilegio_responde_403(): void
    {
        $zona = $this->zona(1, 'Noroeste');
        $this->quitarPrivilegio(Privilegio::GestionarZonas);

        $this->actingAs($this->admin)->get('/zonas')->assertForbidden();
        $this->actingAs($this->admin)->get("/zonas/{$zona}")->assertForbidden();
        $this->actingAs($this->admin)->put("/zonas/{$zona}", ['numero' => 1, 'nombre' => 'Otra'])->assertForbidden();
    }

    public function test_la_consulta_muestra_sus_entidades(): void
    {
        $zona = $this->zona(1, 'Noroeste');
        $this->entidad('26', 'Sonora', $zona);

        $this->actingAs($this->admin)->get("/zonas/{$zona}")
            ->assertOk()
            ->assertSeeText('Noroeste')
            ->assertSeeText('Sonora');

        // En la lista, el botón Borrar explica por qué no se puede
        $this->actingAs($this->admin)->get('/zonas')
            ->assertSee('No se puede borrar porque tiene 1 entidad federativa. Para borrarla, primero pasa sus entidades a otra zona.')
            ->assertSee('accion--bloqueada', false);
    }

    public function test_una_direccion_que_no_es_numero_no_existe(): void
    {
        $this->actingAs($this->admin)->get('/zonas/abc')->assertNotFound();
        $this->actingAs($this->admin)->get('/zonas/999999')->assertNotFound();
    }

    public function test_editar_guarda_y_registra_la_bitacora_con_sus_cambios(): void
    {
        $zona = $this->zona(1, 'Noroeste');

        $this->actingAs($this->admin)->from('/zonas')->put("/zonas/{$zona}", ['numero' => 3, 'nombre' => 'Pacífico Norte'])
            ->assertRedirect('/zonas')
            ->assertSessionHas('estado', 'Se guardaron los cambios de la Zona 3 · Pacífico Norte.');

        $this->assertDatabaseHas('zona_regional', ['zore_id_zona_regional' => $zona, 'zore_numero' => 3, 'zore_nombre' => 'Pacífico Norte']);

        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Modificacion', 'ZONA_REGIONAL', $zona, $this->admin->usua_id_usuario], [$bitacora->bita_accion, $bitacora->bita_tabla, $bitacora->bita_referencia, $bitacora->bita_id_usuario]);
        $this->assertSame(
            [['Número', '1', '3'], ['Nombre de la zona', 'Noroeste', 'Pacífico Norte']],
            $this->detalles($bitacora->bita_id_bitacora),
        );
    }

    public function test_guardar_sin_cambios_no_llena_la_bitacora(): void
    {
        $zona = $this->zona(1, 'Noroeste');
        $antes = DB::table('bitacora')->count();

        $this->actingAs($this->admin)->put("/zonas/{$zona}", ['numero' => 1, 'nombre' => 'Noroeste'])->assertRedirect();

        $this->assertSame($antes, DB::table('bitacora')->count());
    }

    public function test_el_numero_debe_ser_del_1_al_7(): void
    {
        $zona = $this->zona(1, 'Noroeste');

        foreach ([0, 8, 'tres'] as $numero) {
            $this->actingAs($this->admin)->from('/zonas')
                ->put("/zonas/{$zona}", ['numero' => $numero, 'nombre' => 'Noroeste'])
                ->assertRedirect('/zonas')
                ->assertSessionHasErrors('numero');
        }

        $this->assertDatabaseHas('zona_regional', ['zore_id_zona_regional' => $zona, 'zore_numero' => 1]);
    }

    public function test_el_numero_no_se_repite(): void
    {
        $this->zona(2, 'Norte');
        $zona = $this->zona(1, 'Noroeste');

        $this->actingAs($this->admin)->put("/zonas/{$zona}", ['numero' => 2, 'nombre' => 'Noroeste'])
            ->assertSessionHasErrors(['numero' => 'Ya existe una zona con ese número.']);
    }

    public function test_el_nombre_no_se_repite_sin_importar_mayusculas(): void
    {
        $this->zona(2, 'Norte');
        $zona = $this->zona(1, 'Noroeste');

        $this->actingAs($this->admin)->put("/zonas/{$zona}", ['numero' => 1, 'nombre' => '  NORTE '])
            ->assertSessionHasErrors(['nombre' => 'Ya existe una zona con ese nombre.']);
    }

    public function test_con_errores_la_ventana_se_vuelve_a_abrir_con_lo_escrito(): void
    {
        $this->zona(2, 'Norte');
        $zona = $this->zona(1, 'Noroeste');

        $this->actingAs($this->admin)->from('/zonas')->followingRedirects()
            ->put("/zonas/{$zona}", ['numero' => 2, 'nombre' => 'Pacífico', '_dialogo' => 'zona', '_id' => $zona])
            ->assertSeeText('Ya existe una zona con ese número.')
            ->assertSee('id="dialogo-zona" class="dialogo dialogo--amplio" aria-labelledby="dialogo-zona-titulo"  data-abrir-al-cargar', false)
            ->assertSee('action="'.route('zonas.update', $zona).'"', false)
            ->assertSee('value="Pacífico"', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_mover_una_entidad_la_registra_en_la_bitacora(): void
    {
        $noroeste = $this->zona(1, 'Noroeste');
        $norte = $this->zona(2, 'Norte');
        $sonora = $this->entidad('26', 'Sonora', $norte);

        $this->actingAs($this->admin)->put("/zonas/{$noroeste}", ['numero' => 1, 'nombre' => 'Noroeste', 'entidades' => [$sonora]])
            ->assertRedirect()
            ->assertSessionHas('estado', 'Se guardaron los cambios de la Zona 1 · Noroeste. Pasaron a esta zona: Sonora (antes en la Zona 2 · Norte).');

        $this->assertSame($noroeste, (int) DB::table('entidad_federativa')->where('enfe_id_entidad_federativa', $sonora)->value('enfe_id_zona_regional'));

        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Modificacion', 'ENTIDAD_FEDERATIVA', $sonora], [$bitacora->bita_accion, $bitacora->bita_tabla, $bitacora->bita_referencia]);
        $this->assertSame([['Zona regional', 'Zona 2 · Norte', 'Zona 1 · Noroeste']], $this->detalles($bitacora->bita_id_bitacora));
    }

    public function test_la_ventana_sabe_en_que_zona_esta_cada_entidad(): void
    {
        $noroeste = $this->zona(1, 'Noroeste');
        $norte = $this->zona(2, 'Norte');
        $this->entidad('26', 'Sonora', $norte);

        // Con esto, al abrir la ventana de otra zona se pregunta antes de pasar Sonora
        $this->actingAs($this->admin)->get('/zonas')
            ->assertOk()
            ->assertSee('data-entidad="Sonora"', false)
            ->assertSee('data-zona-id="'.$norte.'"', false)
            ->assertSee('data-zona-actual="Zona 2 · Norte"', false)
            ->assertSee('data-zona="'.$noroeste.'"', false)
            ->assertSee('id="dialogo-cambio-zona"', false);
    }

    public function test_una_entidad_no_se_queda_sin_zona_al_desmarcarla(): void
    {
        $zona = $this->zona(1, 'Noroeste');
        $sonora = $this->entidad('26', 'Sonora', $zona);

        // Sin enviar Sonora (en el formulario aparece fija): sigue en la zona
        $this->actingAs($this->admin)->put("/zonas/{$zona}", ['numero' => 1, 'nombre' => 'Noroeste', 'entidades' => []])
            ->assertRedirect();

        $this->assertSame($zona, (int) DB::table('entidad_federativa')->where('enfe_id_entidad_federativa', $sonora)->value('enfe_id_zona_regional'));
    }

    public function test_una_entidad_inexistente_se_rechaza(): void
    {
        $zona = $this->zona(1, 'Noroeste');

        $this->actingAs($this->admin)->put("/zonas/{$zona}", ['numero' => 1, 'nombre' => 'Noroeste', 'entidades' => [999999]])
            ->assertSessionHasErrors('entidades.0');
    }

    public function test_registrar_una_zona_nueva_con_un_numero_libre(): void
    {
        $norte = $this->zona(2, 'Norte');
        $sonora = $this->entidad('26', 'Sonora', $norte);

        $this->actingAs($this->admin)->get('/zonas')
            ->assertOk()
            ->assertSee('data-titulo="Nueva zona"', false);

        $this->actingAs($this->admin)->from('/zonas')->post('/zonas', ['numero' => 1, 'nombre' => 'Noroeste', 'entidades' => [$sonora]])
            ->assertRedirect('/zonas')
            ->assertSessionHas('estado', 'Se registró la Zona 1 · Noroeste.');

        $id = (int) DB::table('zona_regional')->where('zore_numero', 1)->value('zore_id_zona_regional');
        $this->assertSame($id, (int) DB::table('entidad_federativa')->where('enfe_id_entidad_federativa', $sonora)->value('enfe_id_zona_regional'));

        $this->assertTrue(DB::table('bitacora')->where(['bita_accion' => 'Registro', 'bita_tabla' => 'ZONA_REGIONAL', 'bita_referencia' => $id])->exists());
    }

    public function test_con_las_7_zonas_no_se_puede_registrar_otra(): void
    {
        foreach (range(1, 7) as $numero) {
            $this->zona($numero, "Zona de prueba {$numero}");
        }

        $this->actingAs($this->admin)->get('/zonas')->assertDontSee('data-titulo="Nueva zona"', false);

        // Aunque alguien envíe el formulario, el número 8 no existe y los 1 a 7 ya están ocupados
        $this->actingAs($this->admin)->post('/zonas', ['numero' => 8, 'nombre' => 'Octava'])->assertSessionHasErrors('numero');
        $this->actingAs($this->admin)->post('/zonas', ['numero' => 7, 'nombre' => 'Octava'])->assertSessionHasErrors('numero');
    }

    public function test_una_zona_con_entidades_no_se_borra(): void
    {
        $zona = $this->zona(1, 'Noroeste');
        $this->entidad('26', 'Sonora', $zona);

        $this->actingAs($this->admin)->delete("/zonas/{$zona}", ['motivo' => 'Duplicado'])
            ->assertRedirect('/zonas')
            ->assertSessionHas('aviso', 'No se puede borrar porque tiene 1 entidad federativa. Para borrarla, primero pasa sus entidades a otra zona.');

        $this->assertDatabaseHas('zona_regional', ['zore_id_zona_regional' => $zona]);
    }

    public function test_una_zona_vacia_se_borra_con_motivo_y_queda_en_la_bitacora(): void
    {
        $zona = $this->zona(5, 'Centro Sur');

        $this->actingAs($this->admin)->get('/zonas')
            ->assertSee('data-accion="'.route('zonas.destroy', $zona).'"', false)
            ->assertSee('data-bloqueo=""', false)
            ->assertSee('Decisión de la organización');

        $this->actingAs($this->admin)->delete("/zonas/{$zona}", ['motivo' => 'Decisión de la organización'])
            ->assertRedirect('/zonas')
            ->assertSessionHas('estado', 'Se borró la Zona 5 · Centro Sur.');

        $this->assertDatabaseMissing('zona_regional', ['zore_id_zona_regional' => $zona]);

        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Eliminacion', 'ZONA_REGIONAL', $zona, 'Decisión de la organización'], [$bitacora->bita_accion, $bitacora->bita_tabla, $bitacora->bita_referencia, $bitacora->bita_motivo]);
        $this->assertSame([['Número', '5', null], ['Nombre de la zona', 'Centro Sur', null]], $this->detalles($bitacora->bita_id_bitacora));
    }

    public function test_borrar_exige_un_motivo_de_la_lista(): void
    {
        $zona = $this->zona(5, 'Centro Sur');

        $this->actingAs($this->admin)->delete("/zonas/{$zona}", [])->assertSessionHasErrors('motivo');

        // La ventana de borrar se vuelve a abrir para esa zona
        $this->actingAs($this->admin)->from('/zonas')->followingRedirects()
            ->delete("/zonas/{$zona}", ['_id' => $zona])
            ->assertSee('id="dialogo-borrar" class="dialogo" aria-labelledby="dialogo-borrar-titulo"  data-abrir-al-cargar', false)
            ->assertSeeText('¿Borrar la Zona 5 · Centro Sur?');

        $this->actingAs($this->admin)->delete("/zonas/{$zona}", ['motivo' => 'Porque sí'])->assertSessionHasErrors('motivo');

        $this->assertDatabaseHas('zona_regional', ['zore_id_zona_regional' => $zona]);
    }

    public function test_el_nombre_se_muestra_escapado(): void
    {
        $zona = $this->zona(1, '<script>alert(1)</script>');

        $this->actingAs($this->admin)->get("/zonas/{$zona}")
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    private function zona(int $numero, string $nombre): int
    {
        return (int) DB::table('zona_regional')->insertGetId(['zore_numero' => $numero, 'zore_nombre' => $nombre], 'zore_id_zona_regional');
    }

    private function entidad(string $clave, string $nombre, int $zona): int
    {
        return (int) DB::table('entidad_federativa')->insertGetId([
            'enfe_clave' => $clave,
            'enfe_nombre' => $nombre,
            'enfe_id_zona_regional' => $zona,
        ], 'enfe_id_entidad_federativa');
    }

    private function quitarPrivilegio(Privilegio $privilegio): void
    {
        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $this->admin->usua_id_rol)
            ->where('ropr_id_privilegio', DB::table('privilegio')->where('priv_nombre', $privilegio->value)->value('priv_id_privilegio'))
            ->update(['ropr_activo' => false]);
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
        return DB::table('bitacora_detalle')
            ->where('bide_id_bitacora', $idBitacora)
            ->orderBy('bide_id_detalle')
            ->get()
            ->map(fn (object $d) => [$d->bide_campo, $d->bide_anterior, $d->bide_nuevo])
            ->all();
    }
}

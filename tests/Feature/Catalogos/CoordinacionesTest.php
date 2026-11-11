<?php

namespace Tests\Feature\Catalogos;

use App\Enums\Privilegio;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogosInicialesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\Support\CreaInstituciones;
use Tests\TestCase;

/**
 * Criterios de M3 (coordinaciones nacionales):
 * - Reordenar coordinaciones llama reordenar_coordinaciones y no deja huecos ni duplicados.
 * - Desactivar coordinación no borra sus puestos históricos.
 * - Solo se borra si no tiene puestos, con motivo, y el orden queda sin huecos.
 */
class CoordinacionesTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $this->seed(CatalogosInicialesSeeder::class);
    }

    public function test_la_lista_sale_en_su_orden(): void
    {
        $nombres = DB::table('coordinacion_nacional')->orderBy('cona_orden')->pluck('cona_nombre')->all();

        $this->actingAs($this->admin)->get('/coordinaciones')
            ->assertOk()
            ->assertSeeInOrder($nombres);
    }

    public function test_sin_el_privilegio_responde_403(): void
    {
        $id = $this->idEnLugar(1);
        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $this->admin->usua_id_rol)
            ->where('ropr_id_privilegio', DB::table('privilegio')->where('priv_nombre', Privilegio::GestionarCoordinaciones->value)->value('priv_id_privilegio'))
            ->update(['ropr_activo' => false]);

        $this->actingAs($this->admin)->get('/coordinaciones')->assertForbidden();
        $this->actingAs($this->admin)->get('/coordinaciones/orden')->assertForbidden();
        $this->actingAs($this->admin)->put('/coordinaciones/orden', ['orden' => $this->ordenActual()])->assertForbidden();
        $this->actingAs($this->admin)->patch("/coordinaciones/{$id}/estado", ['activa' => '0'])->assertForbidden();
        $this->actingAs($this->admin)->delete("/coordinaciones/{$id}", ['motivo' => 'Duplicado'])->assertForbidden();
    }

    public function test_una_nueva_queda_al_final_y_se_registra_en_la_bitacora(): void
    {
        $ultimo = (int) DB::table('coordinacion_nacional')->max('cona_orden');

        $this->actingAs($this->admin)->post('/coordinaciones', ['nombre' => 'Educación Continua', 'descripcion' => ''])
            ->assertRedirect()
            ->assertSessionHas('estado', 'Se registró la coordinación Educación Continua. Quedó al final del orden.');

        $nueva = DB::table('coordinacion_nacional')->where('cona_nombre', 'Educación Continua')->first();
        $this->assertSame($ultimo + 1, (int) $nueva->cona_orden);
        $this->assertNull($nueva->cona_descripcion);
        $this->assertTrue((bool) $nueva->cona_activo);

        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Registro', 'COORDINACION_NACIONAL', (int) $nueva->cona_id_coordinacion_nacional], [$bitacora->bita_accion, $bitacora->bita_tabla, $bitacora->bita_referencia]);
    }

    public function test_el_nombre_es_obligatorio_y_no_se_repite(): void
    {
        $this->actingAs($this->admin)->post('/coordinaciones', ['nombre' => ''])->assertSessionHasErrors('nombre');
        $this->actingAs($this->admin)->post('/coordinaciones', ['nombre' => ' historia '])
            ->assertSessionHasErrors(['nombre' => 'Ya existe una coordinación con ese nombre.']);
        $this->actingAs($this->admin)->post('/coordinaciones', ['nombre' => str_repeat('a', 71)])->assertSessionHasErrors('nombre');
        $this->actingAs($this->admin)->post('/coordinaciones', ['nombre' => 'Nueva', 'descripcion' => str_repeat('a', 101)])->assertSessionHasErrors('descripcion');
    }

    public function test_editar_registra_los_cambios_en_la_bitacora(): void
    {
        $id = $this->idEnLugar(8); // Historia

        $this->actingAs($this->admin)->put("/coordinaciones/{$id}", ['nombre' => 'Historia de la Contaduría', 'descripcion' => 'Memoria de la profesión'])
            ->assertRedirect("/coordinaciones/{$id}");

        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Modificacion', 'COORDINACION_NACIONAL', $id], [$bitacora->bita_accion, $bitacora->bita_tabla, $bitacora->bita_referencia]);
        $this->assertSame([
            ['Nombre de la coordinación', 'Historia', 'Historia de la Contaduría'],
            ['Descripción', null, 'Memoria de la profesión'],
        ], $this->detalles($bitacora->bita_id_bitacora));
    }

    public function test_desactivar_no_borra_sus_puestos_ni_sus_designaciones(): void
    {
        $id = $this->idEnLugar(1);
        [$puesto, $designacion] = $this->puestoConDesignacion($id);

        $this->actingAs($this->admin)->patch("/coordinaciones/{$id}/estado", ['activa' => '0'])
            ->assertRedirect("/coordinaciones/{$id}")
            ->assertSessionHas('estado', 'Se desactivó la coordinación. Sus puestos y su historial se conservan.');

        $this->assertFalse((bool) DB::table('coordinacion_nacional')->where('cona_id_coordinacion_nacional', $id)->value('cona_activo'));
        $this->assertDatabaseHas('puesto', ['pues_id_puesto' => $puesto, 'pues_id_coordinacion_nacional' => $id]);
        $this->assertDatabaseHas('designacion', ['desi_id_designacion' => $designacion, 'desi_id_puesto' => $puesto]);

        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Desactivacion', 'COORDINACION_NACIONAL', $id], [$bitacora->bita_accion, $bitacora->bita_tabla, $bitacora->bita_referencia]);

        // Sigue en el orden y en la consulta, con sus puestos
        $this->actingAs($this->admin)->get("/coordinaciones/{$id}")
            ->assertSeeText('Desactivada')
            ->assertSeeText('Coordinadora Nacional') // el cargo según el género de quien lo ocupa
            ->assertSeeText('Ana Prueba');
    }

    public function test_activarla_de_nuevo(): void
    {
        $id = $this->idEnLugar(2);
        DB::table('coordinacion_nacional')->where('cona_id_coordinacion_nacional', $id)->update(['cona_activo' => false]);

        $this->actingAs($this->admin)->patch("/coordinaciones/{$id}/estado", ['activa' => '1'])
            ->assertSessionHas('estado', 'Se activó la coordinación.');

        $this->assertTrue((bool) DB::table('coordinacion_nacional')->where('cona_id_coordinacion_nacional', $id)->value('cona_activo'));
        $this->assertSame('Activacion', $this->ultimaBitacora()->bita_accion);
    }

    public function test_una_coordinacion_con_puestos_no_se_borra(): void
    {
        $id = $this->idEnLugar(1);
        $this->puestoConDesignacion($id);

        $this->actingAs($this->admin)->get("/coordinaciones/{$id}")
            ->assertSeeText('No se puede borrar porque tiene 1 puesto.')
            ->assertDontSee('dialogo-borrar', false);

        $this->actingAs($this->admin)->delete("/coordinaciones/{$id}", ['motivo' => 'Error de captura'])
            ->assertRedirect("/coordinaciones/{$id}")
            ->assertSessionHas('aviso');

        $this->assertDatabaseHas('coordinacion_nacional', ['cona_id_coordinacion_nacional' => $id]);
    }

    public function test_sin_puestos_se_borra_con_motivo_y_las_siguientes_suben_un_lugar(): void
    {
        $id = $this->idEnLugar(5); // Investigación
        $antes = $this->ordenActual();

        $this->actingAs($this->admin)->get("/coordinaciones/{$id}")
            ->assertSeeText('Borrar coordinación')
            ->assertSee('Error de captura');

        $this->actingAs($this->admin)->delete("/coordinaciones/{$id}", ['motivo' => 'Error de captura'])
            ->assertRedirect('/coordinaciones')
            ->assertSessionHas('estado', 'Se borró la coordinación Investigación. Las que seguían subieron un lugar.');

        $this->assertDatabaseMissing('coordinacion_nacional', ['cona_id_coordinacion_nacional' => $id]);

        // Mismo orden, sin la borrada y sin huecos
        $this->assertSame(array_values(array_diff($antes, [$id])), $this->ordenActual());
        $lugares = DB::table('coordinacion_nacional')->orderBy('cona_orden')->pluck('cona_orden')->map(fn ($n) => (int) $n)->all();
        $this->assertSame(range(1, count($antes) - 1), $lugares);

        // La bitácora guarda el borrado con su motivo y, después, el reordenamiento
        $movimientos = DB::table('bitacora')->orderByDesc('bita_id_bitacora')->limit(2)->get()->reverse()->values();
        $this->assertSame(['Eliminacion', 'Error de captura', $id], [$movimientos[0]->bita_accion, $movimientos[0]->bita_motivo, $movimientos[0]->bita_referencia]);
        $this->assertSame([['Nombre de la coordinación', 'Investigación', null], ['Orden', '5', null]], $this->detalles($movimientos[0]->bita_id_bitacora));
        $this->assertSame('Reordenamiento', $movimientos[1]->bita_accion);
        $this->assertCount(count($antes) - 5, $this->detalles($movimientos[1]->bita_id_bitacora));
    }

    public function test_borrar_la_ultima_no_reordena(): void
    {
        $ultima = $this->idEnLugar((int) DB::table('coordinacion_nacional')->max('cona_orden'));

        $this->actingAs($this->admin)->delete("/coordinaciones/{$ultima}", ['motivo' => 'Duplicado'])->assertRedirect('/coordinaciones');

        $this->assertSame('Eliminacion', $this->ultimaBitacora()->bita_accion);
    }

    public function test_borrar_exige_un_motivo_de_la_lista(): void
    {
        $id = $this->idEnLugar(3);

        $this->actingAs($this->admin)->delete("/coordinaciones/{$id}", [])->assertSessionHasErrors('motivo');
        $this->actingAs($this->admin)->delete("/coordinaciones/{$id}", ['motivo' => 'Cancelación ARCO'])->assertSessionHasErrors('motivo');

        $this->assertDatabaseHas('coordinacion_nacional', ['cona_id_coordinacion_nacional' => $id]);
    }

    public function test_reordenar_usa_la_funcion_sql_y_no_deja_huecos_ni_repetidos(): void
    {
        $orden = $this->ordenActual();
        $movida = $orden[10]; // la del lugar 11 sube al lugar 2
        array_splice($orden, 10, 1);
        array_splice($orden, 1, 0, [$movida]);

        $this->actingAs($this->admin)->put('/coordinaciones/orden', ['orden' => $orden])
            ->assertRedirect('/coordinaciones')
            ->assertSessionHas('estado', 'Se guardó el nuevo orden.');

        $this->assertSame($orden, $this->ordenActual());

        $lugares = DB::table('coordinacion_nacional')->orderBy('cona_orden')->pluck('cona_orden')->map(fn ($n) => (int) $n)->all();
        $this->assertSame(range(1, count($orden)), $lugares);

        // reordenar_coordinaciones registró la bitácora con cada cambio de lugar
        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Reordenamiento', 'COORDINACION_NACIONAL', $movida], [$bitacora->bita_accion, $bitacora->bita_tabla, $bitacora->bita_referencia]);
        $this->assertCount(10, $this->detalles($bitacora->bita_id_bitacora)); // la movida y las 9 que bajaron un lugar
    }

    public function test_el_inicio_describe_el_reordenamiento(): void
    {
        $superadmin = $this->crearCuenta(Rol::SUPERADMINISTRADOR, [], ['pers_nombre' => 'Marta', 'pers_primer_apellido' => 'Ruiz']);
        $orden = $this->ordenActual();
        [$orden[0], $orden[1]] = [$orden[1], $orden[0]];

        $this->actingAs($superadmin)->put('/coordinaciones/orden', ['orden' => $orden]);

        $this->actingAs($superadmin)->get('/inicio')
            ->assertSeeText('Marta Ruiz reordenó las coordinaciones nacionales.')
            ->assertSee('Certificación Académica, orden: 1 → 2');
    }

    public function test_un_orden_incompleto_no_se_guarda(): void
    {
        $antes = $this->ordenActual();
        $incompleto = array_slice($antes, 0, -1);

        $this->actingAs($this->admin)->put('/coordinaciones/orden', ['orden' => $incompleto])
            ->assertRedirect('/coordinaciones/orden')
            ->assertSessionHas('aviso');

        $this->assertSame($antes, $this->ordenActual());
    }

    public function test_un_orden_con_repetidos_no_se_guarda(): void
    {
        $antes = $this->ordenActual();
        $repetido = $antes;
        $repetido[1] = $repetido[0];

        $this->actingAs($this->admin)->put('/coordinaciones/orden', ['orden' => $repetido])->assertSessionHasErrors('orden.1');

        $this->assertSame($antes, $this->ordenActual());
    }

    public function test_guardar_el_mismo_orden_no_llena_la_bitacora(): void
    {
        $antes = DB::table('bitacora')->count();

        $this->actingAs($this->admin)->put('/coordinaciones/orden', ['orden' => $this->ordenActual()])
            ->assertSessionHas('estado', 'El orden no cambió.');

        $this->assertSame($antes, DB::table('bitacora')->count());
    }

    public function test_la_pantalla_de_orden_tiene_flechas_accesibles(): void
    {
        $this->actingAs($this->admin)->get('/coordinaciones/orden')
            ->assertOk()
            ->assertSee('data-ordenar-subir', false)
            ->assertSeeText('Subir Academia ANFECA')
            ->assertSeeText('Bajar Academia ANFECA')
            ->assertSee('aria-live="polite"', false);
    }

    private function idEnLugar(int $lugar): int
    {
        return (int) DB::table('coordinacion_nacional')->where('cona_orden', $lugar)->value('cona_id_coordinacion_nacional');
    }

    /**
     * @return array<int, int>
     */
    private function ordenActual(): array
    {
        return DB::table('coordinacion_nacional')->orderBy('cona_orden')->pluck('cona_id_coordinacion_nacional')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Un puesto nacional de la coordinación con una persona designada.
     *
     * @return array{0: int, 1: int}
     */
    private function puestoConDesignacion(int $coordinacion): array
    {
        $cargo = (int) DB::table('cargo')->insertGetId([
            'carg_nombre_m' => 'Coordinador Nacional',
            'carg_nombre_f' => 'Coordinadora Nacional',
            'carg_nivel' => 'Nacional',
        ], 'carg_id_cargo');

        $puesto = (int) DB::table('puesto')->insertGetId([
            'pues_id_cargo' => $cargo,
            'pues_id_coordinacion_nacional' => $coordinacion,
        ], 'pues_id_puesto');

        // Para tener designación, la persona debe pertenecer a una institución
        DB::table('persona')->where('pers_id_persona', $this->admin->usua_id_persona)
            ->update(['pers_id_institucion_educativa' => $this->crearInstitucion('Matriz')]);

        $designacion = (int) DB::table('designacion')->insertGetId([
            'desi_id_puesto' => $puesto,
            'desi_id_persona' => $this->admin->usua_id_persona,
            'desi_fecha_inicio' => now()->subYear()->toDateString(),
        ], 'desi_id_designacion');

        return [$puesto, $designacion];
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

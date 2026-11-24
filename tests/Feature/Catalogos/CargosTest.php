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
 * Catálogo de cargos (M3): registrar, editar, desactivar y borrar desde la lista.
 */
class CargosTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
    }

    public function test_la_lista_muestra_los_cargos_con_su_nivel_y_filtros(): void
    {
        $this->cargo('Presidente', 'Presidenta', 'Nacional');
        $this->cargo('Director', 'Directora', 'Institucional');

        $this->actingAs($this->admin)->get('/cargos')
            ->assertOk()
            ->assertSeeText('Presidenta')
            ->assertSee('nivel-cargo--institucional', false)
            ->assertSee('href="'.route('cargos.index', ['nivel' => 'Regional']).'"', false);

        // Filtros en el servidor: por nivel y por texto (sin importar acentos ni mayúsculas).
        // Se revisan solo las filas: el aviso de nombre repetido conoce todos los cargos a propósito.
        $this->assertSame(['Director'], $this->filas('/cargos?nivel=Institucional'));
        $this->assertSame(['Presidente'], $this->filas(route('cargos.index', ['texto' => 'PRESIDÉNTA'])));
        $this->actingAs($this->admin)->get('/cargos?texto=tesorero')->assertSee('Ningún cargo coincide con la búsqueda.');
    }

    public function test_sin_el_privilegio_responde_403(): void
    {
        $id = $this->cargo('Presidente', 'Presidenta', 'Nacional');
        $this->quitarPrivilegio(Privilegio::GestionarCargos);

        $this->actingAs($this->admin)->get('/cargos')->assertForbidden();
        $this->actingAs($this->admin)->post('/cargos', ['masculino' => 'A', 'femenino' => 'B', 'nivel' => 'Nacional'])->assertForbidden();
        $this->actingAs($this->admin)->delete("/cargos/{$id}", ['motivo' => 'Duplicado'])->assertForbidden();
    }

    public function test_registrar_un_cargo_con_su_bitacora(): void
    {
        $this->actingAs($this->admin)->from('/cargos')
            ->post('/cargos', ['masculino' => ' Tesorero ', 'femenino' => 'Tesorera', 'nivel' => 'Nacional'])
            ->assertRedirect('/cargos')
            ->assertSessionHas('estado', 'Se registró el cargo Tesorero / Tesorera.');

        $id = (int) DB::table('cargo')->where('carg_nombre_m', 'Tesorero')->value('carg_id_cargo');
        $this->assertDatabaseHas('cargo', ['carg_id_cargo' => $id, 'carg_nombre_f' => 'Tesorera', 'carg_nivel' => 'Nacional', 'carg_activo' => true]);
        $this->assertSame(['Registro', 'CARGO', $id], $this->resumen($this->ultimaBitacora()));
    }

    public function test_los_nombres_no_se_repiten_y_el_nivel_es_de_la_lista(): void
    {
        $this->cargo('Presidente', 'Presidenta', 'Nacional');

        $this->actingAs($this->admin)->post('/cargos', ['masculino' => 'presidente', 'femenino' => 'Otra', 'nivel' => 'Nacional'])
            ->assertSessionHasErrors(['masculino' => 'Ya existe el cargo «Presidente».']);
        $this->actingAs($this->admin)->post('/cargos', ['masculino' => 'Otro', 'femenino' => 'PRESIDENTA', 'nivel' => 'Nacional'])
            ->assertSessionHasErrors('femenino');
        // Sin importar mayúsculas, acentos ni espacios
        $this->actingAs($this->admin)->post('/cargos', ['masculino' => '  PrEsIdÉnTe  ', 'femenino' => 'Otra', 'nivel' => 'Nacional'])
            ->assertSessionHasErrors(['masculino' => 'Ya existe el cargo «Presidente».']);
        $this->actingAs($this->admin)->post('/cargos', ['masculino' => 'Otro', 'femenino' => 'Otra', 'nivel' => 'Mundial'])
            ->assertSessionHasErrors('nivel');
        $this->actingAs($this->admin)->post('/cargos', ['masculino' => '', 'femenino' => '', 'nivel' => ''])
            ->assertSessionHasErrors(['masculino', 'femenino', 'nivel']);
    }

    public function test_editar_registra_cada_cambio(): void
    {
        $id = $this->cargo('Coordinador', 'Coordinadora', 'Nacional');

        $this->actingAs($this->admin)->from('/cargos')
            ->put("/cargos/{$id}", ['masculino' => 'Coordinador Regional', 'femenino' => 'Coordinadora Regional', 'nivel' => 'Regional'])
            ->assertRedirect('/cargos');

        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Modificacion', 'CARGO', $id], $this->resumen($bitacora));
        $this->assertSame([
            ['Nombre masculino', 'Coordinador', 'Coordinador Regional'],
            ['Nombre femenino', 'Coordinadora', 'Coordinadora Regional'],
            ['Nivel', 'Nacional', 'Regional'],
        ], $this->detalles($bitacora->bita_id_bitacora));
    }

    public function test_con_puestos_el_nivel_no_cambia(): void
    {
        $id = $this->cargo('Presidente', 'Presidenta', 'Nacional');
        DB::table('puesto')->insert(['pues_id_cargo' => $id]);

        $this->actingAs($this->admin)->put("/cargos/{$id}", ['masculino' => 'Presidente', 'femenino' => 'Presidenta', 'nivel' => 'Regional'])
            ->assertSessionHasErrors(['nivel' => 'El nivel no se puede cambiar porque el cargo ya tiene puestos.']);

        // Los nombres sí se pueden corregir
        $this->actingAs($this->admin)->put("/cargos/{$id}", ['masculino' => 'Presidente Nacional', 'femenino' => 'Presidenta Nacional', 'nivel' => 'Nacional'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get('/cargos')->assertSee('data-puestos="1"', false);
        $this->assertDatabaseHas('cargo', ['carg_id_cargo' => $id, 'carg_nivel' => 'Nacional']);
    }

    public function test_un_cargo_con_puestos_vacios_se_borra_junto_con_ellos(): void
    {
        $id = $this->cargo('Tesorero', 'Tesorera', 'Nacional');
        $puesto = (int) DB::table('puesto')->insertGetId(['pues_id_cargo' => $id], 'pues_id_puesto');
        DB::table('puesto_directorio')->insert(['pudi_id_puesto' => $puesto, 'pudi_id_tipo_directorio' => 1, 'pudi_orden' => 1]);

        // La ventana avisa que también se borra su puesto
        $this->actingAs($this->admin)->get('/cargos')
            ->assertSee('También se borra su puesto, que nunca ha tenido personas.')
            ->assertDontSee('accion--bloqueada', false);

        $this->actingAs($this->admin)->delete("/cargos/{$id}", ['motivo' => 'Error de captura'])
            ->assertSessionHas('estado', 'Se borró el cargo Tesorero.');

        $this->assertDatabaseMissing('cargo', ['carg_id_cargo' => $id]);
        $this->assertDatabaseMissing('puesto', ['pues_id_puesto' => $puesto]);
        $this->assertDatabaseMissing('puesto_directorio', ['pudi_id_puesto' => $puesto]);
        // Queda en la bitácora: el puesto y el cargo, con el mismo motivo
        $this->assertSame(1, DB::table('bitacora')->where('bita_tabla', 'PUESTO')->where('bita_referencia', $puesto)->where('bita_accion', 'Eliminacion')->where('bita_motivo', 'Error de captura')->count());
        $this->assertSame(['Puestos vacíos borrados con el cargo', '1', null], array_values(array_slice($this->detalles($this->ultimaBitacora()->bita_id_bitacora), -1))[0]);
    }

    public function test_un_cargo_cuyos_puestos_ya_tuvieron_personas_no_se_borra(): void
    {
        $id = $this->cargo('Director', 'Directora', 'Institucional');
        $institucion = DB::table('institucion_educativa')->insertGetId([
            'ined_id_tipo_institucion' => DB::table('tipo_institucion_educativa')->where('tiie_nombre', 'Universidad')->value('tiie_id_tipo_institucion'),
            'ined_participacion' => 'Matriz', 'ined_sector' => 'Publica', 'ined_activo' => true,
        ], 'ined_id_institucion_educativa');
        $puesto = (int) DB::table('puesto')->insertGetId(['pues_id_cargo' => $id, 'pues_id_institucion_educativa' => $institucion], 'pues_id_puesto');
        DB::table('persona')->where('pers_id_persona', $this->admin->usua_id_persona)->update(['pers_id_institucion_educativa' => $institucion]);
        DB::table('designacion')->insert(['desi_id_puesto' => $puesto, 'desi_id_persona' => $this->admin->usua_id_persona, 'desi_fecha_inicio' => '2024-01-01']);

        $this->actingAs($this->admin)->get('/cargos')
            ->assertSee('No se puede borrar porque sus puestos ya tuvieron 1 designación.')
            ->assertSee('accion--bloqueada', false);

        $this->actingAs($this->admin)->delete("/cargos/{$id}", ['motivo' => 'Duplicado'])
            ->assertRedirect('/cargos')
            ->assertSessionHas('aviso');
        $this->assertDatabaseHas('cargo', ['carg_id_cargo' => $id]);
        $this->assertDatabaseHas('puesto', ['pues_id_puesto' => $puesto]);
    }

    public function test_desactivar_y_activar(): void
    {
        $id = $this->cargo('Presidente', 'Presidenta', 'Nacional');

        $this->actingAs($this->admin)->from('/cargos')->patch("/cargos/{$id}/estado", ['activa' => '0'])
            ->assertSessionHas('estado', 'Se desactivó el cargo Presidente. Sus puestos y su historial se conservan.');
        $this->assertDatabaseHas('cargo', ['carg_id_cargo' => $id, 'carg_activo' => false]);
        $this->assertSame('Desactivacion', $this->ultimaBitacora()->bita_accion);

        $this->actingAs($this->admin)->from('/cargos')->patch("/cargos/{$id}/estado", ['activa' => '1']);
        $this->assertDatabaseHas('cargo', ['carg_id_cargo' => $id, 'carg_activo' => true]);
    }

    public function test_sin_puestos_se_borra_con_motivo(): void
    {
        $id = $this->cargo('Tesorero', 'Tesorera', 'Nacional');

        $this->actingAs($this->admin)->delete("/cargos/{$id}", [])->assertSessionHasErrors('motivo');

        $this->actingAs($this->admin)->delete("/cargos/{$id}", ['motivo' => 'Error de captura'])
            ->assertRedirect('/cargos')
            ->assertSessionHas('estado', 'Se borró el cargo Tesorero.');

        $this->assertDatabaseMissing('cargo', ['carg_id_cargo' => $id]);
        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Eliminacion', 'CARGO', $id], $this->resumen($bitacora));
        $this->assertSame('Error de captura', $bitacora->bita_motivo);
    }

    public function test_con_errores_la_ventana_se_vuelve_a_abrir(): void
    {
        $this->cargo('Presidente', 'Presidenta', 'Nacional');

        $this->actingAs($this->admin)->from('/cargos')->followingRedirects()
            ->post('/cargos', ['masculino' => 'Presidente', 'femenino' => 'Otra', 'nivel' => 'Nacional', '_dialogo' => 'cargo', '_id' => ''])
            ->assertSeeText('Ya existe el cargo «Presidente».')
            ->assertSee('id="dialogo-cargo" class="dialogo dialogo--mediano" aria-labelledby="dialogo-cargo-titulo"  data-abrir-al-cargar', false);
    }

    /**
     * Cargos (en masculino) que aparecen en las filas de la tabla.
     *
     * @return array<int, string>
     */
    private function filas(string $direccion): array
    {
        preg_match_all('/class="tabla__principal">([^<]*)</', $this->actingAs($this->admin)->get($direccion)->assertOk()->getContent(), $coincidencias);

        return array_map('trim', $coincidencias[1]);
    }

    private function cargo(string $masculino, string $femenino, string $nivel): int
    {
        return (int) DB::table('cargo')->insertGetId(
            ['carg_nombre_m' => $masculino, 'carg_nombre_f' => $femenino, 'carg_nivel' => $nivel],
            'carg_id_cargo',
        );
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
     * @return array{0: string, 1: string, 2: int}
     */
    private function resumen(object $bitacora): array
    {
        return [$bitacora->bita_accion, $bitacora->bita_tabla, (int) $bitacora->bita_referencia];
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

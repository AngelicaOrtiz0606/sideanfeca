<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Bitácora completa (solo superadministrador): cada movimiento en una línea
 * con todos sus cambios, filtros (texto, acción, qué se modificó y fechas),
 * de 10 en 10 y en CSV. Solo se consulta.
 */
class BitacoraTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private Usuario $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = $this->crearCuenta(Rol::SUPERADMINISTRADOR, [], ['pers_nombre' => 'Marta', 'pers_primer_apellido' => 'Ruiz']);
    }

    public function test_muestra_cada_movimiento_con_todos_sus_cambios(): void
    {
        $id = $this->movimiento('Modificacion', 'COORDINACION_NACIONAL', null);
        foreach (['Orden', 'Activo', 'Nombre corto', 'Descripción'] as $i => $campo) {
            DB::select('select bitacora_detalle_registrar(?, ?, ?, ?, ?)', [$id, null, $campo, (string) $i, (string) ($i + 1)]);
        }

        $this->actingAs($this->superadmin)->get('/bitacora')->assertOk()
            ->assertSee('Marta Ruiz')->assertSee('Ver los 4 cambios')
            ->assertSee('Descripción: 3 → 4')
            ->assertDontSee('más en la bitácora completa');
    }

    public function test_filtra_por_texto_accion_tabla_y_fechas(): void
    {
        $luis = $this->crearCuenta(Rol::ADMINISTRADOR, [], ['pers_nombre' => 'Luis', 'pers_primer_apellido' => 'Ramírez']);
        $this->movimiento('Registro', 'ZONA_REGIONAL', null, $luis);
        $this->movimiento('Eliminacion', 'PERSONA', 999999, motivo: 'Duplicado');
        $this->movimiento('Registro', 'CARGO', null);
        $puesto = $this->movimiento('Modificacion', 'PERSONA', null);
        DB::select('select bitacora_detalle_registrar(?, ?, ?, ?, ?)', [$puesto, null, 'Designación', null, 'Presidenta · Nacional, desde 01/01/2026']);

        // Quién lo hizo (sin acentos ni mayúsculas)
        $this->actingAs($this->superadmin)->get('/bitacora?texto=luis+ramirez')->assertOk()->assertSee('Mostrando 1–1 de 1');
        // El texto de un cambio
        $this->actingAs($this->superadmin)->get('/bitacora?texto=presidenta')->assertOk()->assertSee('Mostrando 1–1 de 1');
        // Acción y tabla
        $this->actingAs($this->superadmin)->get('/bitacora?accion=Eliminacion')->assertOk()->assertSee('Motivo: Duplicado')->assertSee('Mostrando 1–1 de 1');
        $this->actingAs($this->superadmin)->get('/bitacora?tabla=CARGO')->assertOk()->assertSee('Mostrando 1–1 de 1');
        // Fechas: todo pasó hoy (la bitácora no se puede cambiar, ni en pruebas); si vienen al revés, se acomodan
        $hoy = now()->toDateString();
        $this->actingAs($this->superadmin)->get("/bitacora?desde={$hoy}&hasta={$hoy}")->assertOk()->assertSee('Mostrando 1–4 de 4');
        $this->actingAs($this->superadmin)->get('/bitacora?hasta=2020-12-31')->assertOk()->assertSee('Ningún movimiento coincide con los filtros.');
        $this->actingAs($this->superadmin)->get("/bitacora?desde={$hoy}&hasta=2020-01-01")->assertOk()->assertSee('Mostrando 1–4 de 4');
        // Una fecha que no existe se ignora
        $this->actingAs($this->superadmin)->get('/bitacora?desde=2030-02-31')->assertOk()->assertSee('Mostrando 1–4 de 4');
        // Valores que no existen se ignoran
        $this->actingAs($this->superadmin)->get('/bitacora?accion=Borrar&tabla=USUARIO%27')->assertOk()->assertSee('Mostrando 1–4 de 4');
    }

    public function test_de_diez_en_diez_y_el_inicio_lleva_a_la_bitacora(): void
    {
        foreach (range(1, 12) as $numero) {
            $this->movimiento('Registro', 'ZONA_REGIONAL', $numero);
        }

        $this->actingAs($this->superadmin)->get('/bitacora')->assertOk()->assertSee('Mostrando 1–10 de 12');
        $this->actingAs($this->superadmin)->get('/bitacora?page=2')->assertOk()->assertSee('Mostrando 11–12 de 12');
        $this->actingAs($this->superadmin)->get('/inicio')->assertOk()->assertSee('href="'.route('bitacora.index').'"', false);
    }

    public function test_el_csv_trae_todo_lo_filtrado(): void
    {
        foreach (range(1, 12) as $numero) {
            $this->movimiento('Registro', 'ZONA_REGIONAL', $numero);
        }
        $this->movimiento('Eliminacion', 'PERSONA', 999999, motivo: 'Cancelación ARCO');

        $csv = $this->actingAs($this->superadmin)->get('/bitacora/csv')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}Fecha,Hora,Quién,Acción,\"Qué hizo\",Motivo,Cambios", $csv);
        $this->assertSame(14, substr_count($csv, "\n"));
        $this->assertStringContainsString('"Marta Ruiz eliminó a una persona eliminada (#999999)."', $csv);

        $filtrado = $this->actingAs($this->superadmin)->get('/bitacora/csv?accion=Eliminacion')->streamedContent();
        $this->assertSame(2, substr_count($filtrado, "\n"));
    }

    public function test_el_administrador_no_la_ve_y_no_se_puede_modificar(): void
    {
        $admin = $this->crearCuenta(Rol::ADMINISTRADOR);

        $this->actingAs($admin)->get('/bitacora')->assertForbidden();
        $this->actingAs($admin)->get('/bitacora/csv')->assertForbidden();

        // No hay rutas para cambiarla
        foreach (['post', 'put', 'patch', 'delete'] as $metodo) {
            $this->actingAs($this->superadmin)->{$metodo}('/bitacora')->assertStatus(405);
        }
    }

    private function movimiento(string $accion, string $tabla, ?int $referencia, ?Usuario $quien = null, ?string $motivo = null): int
    {
        return (int) DB::selectOne('select bitacora_registrar(?, ?, ?, ?, ?) as id', [($quien ?? $this->superadmin)->usua_id_usuario, $accion, $tabla, $referencia, $motivo])->id;
    }
}

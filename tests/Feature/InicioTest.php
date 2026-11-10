<?php

namespace Tests\Feature;

use App\Enums\ResultadoAcceso;
use App\Models\Rol;
use Database\Seeders\CatalogosInicialesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\Support\CreaInstituciones;
use Tests\TestCase;

/**
 * Criterios de la pantalla de inicio (sección 10):
 * - Las instituciones y coordinaciones desactivadas no se cuentan; las matrices tampoco.
 * - El admin no ve el bloque de bitácora, ni siquiera vacío.
 * - Los contadores vienen de consultas a la base.
 * - No muestra designaciones ni asignaciones.
 */
class InicioTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    public function test_saluda_con_el_nombre_y_el_perfil(): void
    {
        $cuenta = $this->crearCuenta(Rol::SUPERADMINISTRADOR, [], [
            'pers_nombre' => 'Marta',
            'pers_primer_apellido' => 'Ruiz',
            'pers_segundo_apellido' => 'Soto',
        ]);

        $this->actingAs($cuenta)->get('/inicio')
            ->assertOk()
            ->assertSee('Bienvenida, Marta Ruiz Soto')
            ->assertSee('Superadmin')
            ->assertSee('Solo se cuentan las activas.');
    }

    public function test_cuenta_solo_afiliadas_y_observadoras_activas(): void
    {
        $this->crearInstitucion('Afiliada');
        $this->crearInstitucion('Afiliada');
        $this->crearInstitucion('Afiliada', activa: false);
        $this->crearInstitucion('Observadora');
        $this->crearInstitucion('Observadora', activa: false);
        $this->crearInstitucion('Matriz');

        $this->actingAs($this->crearCuenta())->get('/inicio')
            ->assertViewHas('contadores', fn (array $c): bool => $c['afiliadas'] === 2 && $c['observadoras'] === 1);
    }

    public function test_cuenta_solo_coordinaciones_activas(): void
    {
        $this->seed(CatalogosInicialesSeeder::class);
        DB::table('coordinacion_nacional')->where('cona_orden', 14)->update(['cona_activo' => false]);

        $this->actingAs($this->crearCuenta())->get('/inicio')
            ->assertViewHas('contadores', fn (array $contadores): bool => $contadores['coordinaciones'] === 13);
    }

    public function test_los_contadores_cambian_cuando_cambian_los_datos(): void
    {
        $cuenta = $this->crearCuenta();

        $this->actingAs($cuenta)->get('/inicio')
            ->assertViewHas('contadores', fn (array $c): bool => $c['afiliadas'] === 0);

        $this->crearInstitucion('Afiliada');

        $this->actingAs($cuenta)->get('/inicio')
            ->assertViewHas('contadores', fn (array $c): bool => $c['afiliadas'] === 1);
    }

    public function test_el_admin_no_ve_el_bloque_de_bitacora_ni_vacio(): void
    {
        $this->actingAs($this->crearCuenta(Rol::ADMINISTRADOR))->get('/inicio')
            ->assertDontSee('Actividad reciente')
            ->assertDontSee('Todavía no hay movimientos registrados.')
            ->assertViewHas('movimientos', fn ($movimientos): bool => $movimientos->isEmpty());
    }

    public function test_el_superadmin_ve_el_bloque_vacio_si_no_hay_movimientos(): void
    {
        $this->actingAs($this->crearCuenta(Rol::SUPERADMINISTRADOR))->get('/inicio')
            ->assertSee('Actividad reciente')
            ->assertSee('Todavía no hay movimientos registrados.');
    }

    public function test_el_superadmin_ve_los_cinco_movimientos_mas_recientes(): void
    {
        $cuenta = $this->crearCuenta(Rol::SUPERADMINISTRADOR, [], [
            'pers_nombre' => 'Marta',
            'pers_primer_apellido' => 'Ruiz',
        ]);

        // La bitácora no admite cambios (trigger de solo inserción): dentro de la
        // transacción de la prueba todas las filas tienen la misma hora y el orden
        // lo decide el número de movimiento, del más nuevo al más antiguo.
        foreach (range(1, 7) as $numero) {
            DB::select('select bitacora_registrar(?, ?, ?, ?, ?)', [$cuenta->usua_id_usuario, 'Registro', 'ZONA_REGIONAL', $numero, null]);
        }

        $this->actingAs($cuenta)->get('/inicio')
            ->assertSeeInOrder(['la zona regional #7', 'la zona regional #6', 'la zona regional #5', 'la zona regional #4', 'la zona regional #3'])
            ->assertDontSee('la zona regional #2')
            ->assertSee('Marta Ruiz')
            ->assertSee('registró');

        $this->actingAs($cuenta)->get('/inicio')
            ->assertViewHas('hayMasMovimientos', true)
            ->assertSee('Se muestran los 5 movimientos más recientes.');
    }

    public function test_con_cinco_movimientos_o_menos_no_ofrece_ver_mas(): void
    {
        $cuenta = $this->crearCuenta(Rol::SUPERADMINISTRADOR);

        foreach (range(1, 5) as $numero) {
            DB::select('select bitacora_registrar(?, ?, ?, ?, ?)', [$cuenta->usua_id_usuario, 'Registro', 'ZONA_REGIONAL', $numero, null]);
        }

        $this->actingAs($cuenta)->get('/inicio')
            ->assertViewHas('hayMasMovimientos', false)
            ->assertDontSee('Se muestran los 5 movimientos más recientes.');
    }

    public function test_un_registro_de_persona_borrada_dice_persona_eliminada(): void
    {
        $cuenta = $this->crearCuenta(Rol::SUPERADMINISTRADOR);

        DB::select('select bitacora_registrar(?, ?, ?, ?, ?)', [$cuenta->usua_id_usuario, 'Eliminacion', 'PERSONA', 999999, 'Cancelación ARCO']);

        $this->actingAs($cuenta)->get('/inicio')
            ->assertSeeText('eliminó a una persona eliminada (#999999)')
            ->assertSee('Motivo:')
            ->assertSee('Cancelación ARCO');
    }

    public function test_cada_movimiento_se_lee_como_oracion_con_sus_cambios(): void
    {
        $cuenta = $this->crearCuenta(Rol::SUPERADMINISTRADOR, [], [
            'pers_nombre' => 'Marta',
            'pers_primer_apellido' => 'Ruiz',
        ]);

        $idBitacora = DB::selectOne(
            'select bitacora_registrar(?, ?, ?, ?, ?) as id',
            [$cuenta->usua_id_usuario, 'Modificacion', 'PERSONA', $cuenta->usua_id_persona, null]
        )->id;
        // Campo organizacional: guarda valores. Campo personal: solo el nombre del campo.
        DB::select('select bitacora_detalle_registrar(?, ?, ?, ?, ?)', [$idBitacora, null, 'Orden', '3', '5']);
        DB::select('select bitacora_detalle_registrar(?, ?, ?, ?, ?)', [$idBitacora, null, 'Correo', null, null]);

        $this->actingAs($cuenta)->get('/inicio')
            ->assertSeeText('Marta Ruiz modificó los datos de la persona Marta Ruiz.')
            ->assertSee('Ver los 2 cambios')
            ->assertSeeInOrder(['Orden: 3 → 5', 'Correo'])
            ->assertSee('data-tono="info"', false);
    }

    public function test_el_registro_de_una_cuenta_dice_su_perfil(): void
    {
        $superadmin = $this->crearCuenta(Rol::SUPERADMINISTRADOR, [], ['pers_nombre' => 'Marta', 'pers_primer_apellido' => 'Ruiz']);
        $admin = $this->crearCuenta(Rol::ADMINISTRADOR, [], ['pers_nombre' => 'Luis', 'pers_primer_apellido' => 'Ramírez']);

        DB::select('select bitacora_registrar(?, ?, ?, ?, ?)', [$superadmin->usua_id_usuario, 'Registro', 'USUARIO', $admin->usua_id_usuario, null]);

        $this->actingAs($superadmin)->get('/inicio')
            ->assertSeeText('Marta Ruiz creó la cuenta de Luis Ramírez (Administrador).')
            ->assertSee('data-tono="exito"', false);
    }

    public function test_un_movimiento_sobre_un_registro_con_nombre_lo_nombra(): void
    {
        $cuenta = $this->crearCuenta(Rol::SUPERADMINISTRADOR, [], ['pers_nombre' => 'Marta', 'pers_primer_apellido' => 'Ruiz']);
        $this->seed(CatalogosInicialesSeeder::class);
        $idZona = DB::table('zona_regional')->where('zore_numero', 7)->value('zore_id_zona_regional');

        DB::select('select bitacora_registrar(?, ?, ?, ?, ?)', [$cuenta->usua_id_usuario, 'Modificacion', 'ZONA_REGIONAL', $idZona, null]);

        $this->actingAs($cuenta)->get('/inicio')
            ->assertSeeText('Marta Ruiz modificó la zona regional Ciudad de México.');
    }

    public function test_un_movimiento_sobre_todo_el_catalogo_se_entiende(): void
    {
        $cuenta = $this->crearCuenta(Rol::SUPERADMINISTRADOR, [], ['pers_nombre' => 'Marta', 'pers_primer_apellido' => 'Ruiz']);

        $this->seed(CatalogosInicialesSeeder::class);
        $idCoordinacion = DB::table('coordinacion_nacional')->where('cona_orden', 11)->value('cona_id_coordinacion_nacional');

        $idBitacora = DB::selectOne(
            'select bitacora_registrar(?, ?, ?, ?, ?) as id',
            [$cuenta->usua_id_usuario, 'Reordenamiento', 'COORDINACION_NACIONAL', null, null]
        )->id;
        DB::select('select bitacora_detalle_registrar(?, ?, ?, ?, ?)', [$idBitacora, $idCoordinacion, 'Orden', '11', '9']);

        $this->actingAs($cuenta)->get('/inicio')
            ->assertSeeText('Marta Ruiz reordenó las coordinaciones nacionales.')
            ->assertSee('Formación Profesional y Académica, orden: 11 → 9');
    }

    public function test_muestra_el_inicio_de_sesion_anterior(): void
    {
        $cuenta = $this->crearCuenta();

        DB::table('log_acceso')->insert([
            'loga_id_usuario' => $cuenta->usua_id_usuario,
            'loga_resultado' => ResultadoAcceso::Exitoso->value,
            'loga_ip' => '10.0.0.1',
            'loga_fecha_hora' => '2026-11-09 18:40:00',
        ]);
        DB::table('log_acceso')->insert([
            'loga_id_usuario' => $cuenta->usua_id_usuario,
            'loga_resultado' => ResultadoAcceso::Exitoso->value,
            'loga_ip' => '10.0.0.1',
            'loga_fecha_hora' => '2026-11-10 09:15:00',
        ]);

        $this->actingAs($cuenta)->get('/inicio')
            ->assertSeeText('Tu inicio de sesión anterior fue el 9 de noviembre de 2026 a las 18:40.');
    }

    public function test_el_saludo_usa_bienvenido_para_hombres(): void
    {
        $this->actingAs($this->crearCuenta(Rol::ADMINISTRADOR, [], ['pers_nombre' => 'Luis', 'pers_genero' => 'M']))->get('/inicio')
            ->assertSee('Bienvenido, Luis');
    }

    public function test_cada_tarjeta_dice_cuantas_estan_desactivadas(): void
    {
        $this->crearInstitucion('Afiliada');
        $this->crearInstitucion('Afiliada', activa: false);

        $this->actingAs($this->crearCuenta())->get('/inicio')
            ->assertSee('Desactivadas: 1');
    }

    public function test_avisa_cuando_es_el_primer_inicio_de_sesion(): void
    {
        $this->actingAs($this->crearCuenta())->get('/inicio')
            ->assertSee('Este es tu primer inicio de sesión.');
    }

    public function test_no_muestra_designaciones_ni_asignaciones(): void
    {
        $this->actingAs($this->crearCuenta(Rol::SUPERADMINISTRADOR))->get('/inicio')
            ->assertDontSee('Designaci')
            ->assertDontSee('Asignaci');
    }
}

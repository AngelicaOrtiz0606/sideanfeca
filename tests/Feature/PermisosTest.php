<?php

namespace Tests\Feature;

use App\Enums\Privilegio;
use App\Models\Rol;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Criterios de M2 (roles y permisos):
 * - Admin recibe 403 al entrar a bitácora y a gestión de usuarios.
 * - Superadmin accede a todo.
 * - Un cambio de privilegios aplica sin reiniciar sesión.
 *
 * También: la flecha de regreso no muestra pantallas después de cerrar sesión.
 */
class PermisosTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    public function test_el_admin_recibe_403_en_la_bitacora_y_en_usuarios(): void
    {
        $admin = $this->crearCuenta(Rol::ADMINISTRADOR);

        $this->actingAs($admin)->get('/bitacora')->assertForbidden();
        $this->actingAs($admin)->get('/usuarios')->assertForbidden();
    }

    public function test_el_admin_no_ve_los_enlaces_de_bitacora_ni_usuarios(): void
    {
        $this->actingAs($this->crearCuenta(Rol::ADMINISTRADOR))->get('/inicio')
            ->assertOk()
            ->assertDontSee(route('bitacora.index'))
            ->assertDontSee(route('usuarios.index'));
    }

    public function test_el_superadmin_entra_a_la_bitacora_y_a_usuarios(): void
    {
        $superadmin = $this->crearCuenta(Rol::SUPERADMINISTRADOR);

        $this->actingAs($superadmin)->get('/bitacora')
            ->assertOk()
            ->assertSeeText('Bitácora')
            ->assertSeeText('Esta sección estará disponible próximamente.');

        $this->actingAs($superadmin)->get('/usuarios')
            ->assertOk()
            ->assertSeeText('Usuarios');
    }

    public function test_el_superadmin_tiene_todos_los_privilegios(): void
    {
        $superadmin = $this->crearCuenta(Rol::SUPERADMINISTRADOR);

        foreach (Privilegio::cases() as $privilegio) {
            $this->assertTrue(
                Gate::forUser($superadmin)->allows($privilegio->value),
                "El Superadmin debería tener {$privilegio->value}.",
            );
        }
    }

    public function test_el_admin_tiene_todo_menos_bitacora_y_usuarios(): void
    {
        $admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $sinPermiso = [Privilegio::VerBitacora, Privilegio::GestionarUsuarios];

        foreach (Privilegio::cases() as $privilegio) {
            $this->assertSame(
                ! in_array($privilegio, $sinPermiso, true),
                Gate::forUser($admin)->allows($privilegio->value),
                "Revisa el privilegio {$privilegio->value} del Admin.",
            );
        }
    }

    public function test_los_gates_coinciden_con_la_funcion_sql(): void
    {
        $admin = $this->crearCuenta(Rol::ADMINISTRADOR);

        foreach (Privilegio::cases() as $privilegio) {
            $segunLaBase = (bool) DB::selectOne(
                'select cuenta_tiene_privilegio(?, ?) as tiene',
                [$admin->usua_id_usuario, $privilegio->value],
            )->tiene;

            $this->assertSame($segunLaBase, Gate::forUser($admin)->allows($privilegio->value));
        }
    }

    public function test_cada_privilegio_del_codigo_existe_en_la_base(): void
    {
        $this->asegurarRoles();

        $enLaBase = DB::table('privilegio')->orderBy('priv_nombre')->pluck('priv_nombre')->all();
        $enElCodigo = array_map(fn (Privilegio $p) => $p->value, Privilegio::cases());
        sort($enElCodigo);

        $this->assertSame($enLaBase, $enElCodigo);
    }

    public function test_quitar_un_privilegio_aplica_sin_reiniciar_sesion(): void
    {
        $superadmin = $this->crearCuenta(Rol::SUPERADMINISTRADOR);
        $this->actingAs($superadmin)->get('/bitacora')->assertOk();

        $this->cambiarPrivilegio(Rol::SUPERADMINISTRADOR, Privilegio::VerBitacora, activo: false);

        // Misma sesión, siguiente clic: ya no entra ni ve el enlace
        $this->get('/bitacora')->assertForbidden();
        $this->get('/inicio')->assertOk()->assertDontSee(route('bitacora.index'));
        $this->assertAuthenticatedAs($superadmin);

        // Y al devolverlo, vuelve a entrar sin iniciar sesión otra vez
        $this->cambiarPrivilegio(Rol::SUPERADMINISTRADOR, Privilegio::VerBitacora, activo: true);
        $this->get('/bitacora')->assertOk();
    }

    public function test_dar_un_privilegio_aplica_sin_reiniciar_sesion(): void
    {
        $admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $this->actingAs($admin)->get('/bitacora')->assertForbidden();

        $idRol = Rol::where('rol_nombre', Rol::ADMINISTRADOR)->value('rol_id_rol');
        $idPrivilegio = DB::table('privilegio')->where('priv_nombre', Privilegio::VerBitacora->value)->value('priv_id_privilegio');
        DB::table('rol_privilegio')->insert([
            'ropr_id_rol' => $idRol,
            'ropr_id_privilegio' => $idPrivilegio,
            'ropr_activo' => true,
        ]);

        $this->get('/bitacora')->assertOk();
    }

    public function test_una_cuenta_desactivada_no_tiene_privilegios(): void
    {
        $inactiva = $this->crearCuenta(Rol::SUPERADMINISTRADOR, ['usua_activo' => false]);

        foreach (Privilegio::cases() as $privilegio) {
            $this->assertFalse(Gate::forUser($inactiva)->allows($privilegio->value));
        }
    }

    public function test_sin_sesion_la_bitacora_manda_al_login(): void
    {
        $this->get('/bitacora')->assertRedirect(route('login'));
        $this->get('/usuarios')->assertRedirect(route('login'));
    }

    public function test_la_pagina_403_es_amable_y_no_muestra_datos_tecnicos(): void
    {
        $this->actingAs($this->crearCuenta(Rol::ADMINISTRADOR))->get('/bitacora')
            ->assertForbidden()
            ->assertSeeText('Esta sección no está disponible para tu perfil')
            ->assertSee(route('inicio'))
            ->assertSee('class="encabezado', false)
            ->assertDontSee('ver_bitacora')
            ->assertDontSee('AuthorizationException')
            ->assertDontSee('This action is unauthorized');
    }

    public function test_el_inicio_no_ofrece_ver_mas_mientras_no_exista_la_consulta_completa(): void
    {
        $superadmin = $this->crearCuenta(Rol::SUPERADMINISTRADOR);

        foreach (range(1, 6) as $i) {
            DB::select('select bitacora_registrar(?, ?, ?, ?, ?)', [$superadmin->usua_id_usuario, 'Registro', 'ZONA_REGIONAL', $i, null]);
        }

        $this->actingAs($superadmin)->get('/inicio')
            ->assertSeeText('La consulta completa de la bitácora estará disponible próximamente.')
            ->assertDontSee('Ver más');
    }

    public function test_las_pantallas_con_sesion_no_se_guardan_en_el_navegador(): void
    {
        $respuesta = $this->actingAs($this->crearCuenta())->get('/inicio');

        $respuesta->assertOk()->assertHeader('Pragma', 'no-cache');
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
        $respuesta->assertSee('data-con-sesion', false);
    }

    public function test_despues_de_cerrar_sesion_regresar_al_inicio_manda_al_login(): void
    {
        $cuenta = $this->crearCuenta();

        $this->post('/login', ['correo' => $cuenta->usua_correo, 'contrasenia' => $this->contraseniaDePrueba]);
        $this->get('/inicio')->assertOk();

        $this->post('/logout')->assertRedirect(route('login'));

        // Lo que pasa al dar clic en la flecha de regreso: el navegador pide otra vez la página
        $this->get('/inicio')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    private function cambiarPrivilegio(string $rol, Privilegio $privilegio, bool $activo): void
    {
        DB::table('rol_privilegio')
            ->where('ropr_id_rol', Rol::where('rol_nombre', $rol)->value('rol_id_rol'))
            ->where('ropr_id_privilegio', DB::table('privilegio')->where('priv_nombre', $privilegio->value)->value('priv_id_privilegio'))
            ->update(['ropr_activo' => $activo]);
    }
}

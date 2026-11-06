<?php

namespace Tests\Feature;

use App\Enums\ResultadoAcceso;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Rol;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Criterios de M1 (inicio y cierre de sesión):
 * - Inicio correcto, contraseña incorrecta y cuenta inactiva generan una
 *   fila en LOG_ACCESO con IP.
 * - Una cuenta inactiva no entra aunque tenga la contraseña correcta.
 */
class LoginTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private const IP = '10.20.30.40';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withServerVariables(['REMOTE_ADDR' => self::IP]);
    }

    public function test_la_raiz_lleva_al_inicio_de_sesion(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_muestra_el_formulario(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Iniciar sesión')
            ->assertSee('name="correo"', false)
            ->assertSee('autocomplete="current-password"', false);
    }

    public function test_inicio_correcto_entra_y_registra_exitoso_con_ip(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => 'ana@prueba.test']);

        $this->post('/login', ['correo' => 'ana@prueba.test', 'contrasenia' => $this->contraseniaDePrueba])
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($cuenta);
        $this->assertUltimoAcceso(ResultadoAcceso::Exitoso, $cuenta->usua_id_usuario);
    }

    public function test_contrasenia_incorrecta_no_entra_y_registra_fallido_con_cuenta(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => 'ana@prueba.test']);

        $this->post('/login', ['correo' => 'ana@prueba.test', 'contrasenia' => 'otra-contrasenia'])
            ->assertSessionHasErrors(['correo' => 'El correo o la contraseña no son correctos.']);

        $this->assertGuest();
        $this->assertUltimoAcceso(ResultadoAcceso::Fallido, $cuenta->usua_id_usuario);
    }

    public function test_correo_inexistente_registra_fallido_sin_cuenta(): void
    {
        $this->post('/login', ['correo' => 'nadie@prueba.test', 'contrasenia' => 'lo-que-sea'])
            ->assertSessionHasErrors(['correo' => 'El correo o la contraseña no son correctos.']);

        $this->assertGuest();
        $this->assertUltimoAcceso(ResultadoAcceso::Fallido, null);
    }

    public function test_cuenta_inactiva_no_entra_aunque_la_contrasenia_sea_correcta(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, [
            'usua_correo' => 'ana@prueba.test',
            'usua_activo' => false,
        ]);

        $this->post('/login', ['correo' => 'ana@prueba.test', 'contrasenia' => $this->contraseniaDePrueba])
            ->assertSessionHasErrors(['correo' => 'Tu cuenta está desactivada. Comunícate con la persona responsable del sistema.']);

        $this->assertGuest();
        $this->assertUltimoAcceso(ResultadoAcceso::CuentaInactiva, $cuenta->usua_id_usuario);
    }

    public function test_cuenta_inactiva_con_contrasenia_incorrecta_no_revela_que_esta_inactiva(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, [
            'usua_correo' => 'ana@prueba.test',
            'usua_activo' => false,
        ]);

        $this->post('/login', ['correo' => 'ana@prueba.test', 'contrasenia' => 'otra-contrasenia'])
            ->assertSessionHasErrors(['correo' => 'El correo o la contraseña no son correctos.']);

        $this->assertGuest();
        $this->assertUltimoAcceso(ResultadoAcceso::Fallido, $cuenta->usua_id_usuario);
    }

    public function test_cuenta_anonimizada_no_entra_ni_provoca_error(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, [
            'usua_id_persona' => null,
            'usua_correo' => 'anonimo-1@invalido.invalid',
            'usua_contrasenia' => '!ANONIMIZADA',
            'usua_activo' => false,
        ]);

        $this->post('/login', ['correo' => 'anonimo-1@invalido.invalid', 'contrasenia' => '!ANONIMIZADA'])
            ->assertSessionHasErrors(['correo' => 'El correo o la contraseña no son correctos.']);

        $this->assertGuest();
        $this->assertUltimoAcceso(ResultadoAcceso::Fallido, $cuenta->usua_id_usuario);
    }

    public function test_el_correo_se_acepta_sin_importar_mayusculas(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => 'Ana@Prueba.test']);

        $this->post('/login', ['correo' => 'ANA@prueba.TEST', 'contrasenia' => $this->contraseniaDePrueba])
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($cuenta);
    }

    public function test_al_entrar_cambia_el_id_de_sesion(): void
    {
        $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => 'ana@prueba.test']);

        $this->get('/login');
        $idAntes = session()->getId();

        $this->post('/login', ['correo' => 'ana@prueba.test', 'contrasenia' => $this->contraseniaDePrueba]);

        $this->assertNotSame($idAntes, session()->getId());
    }

    public function test_la_contrasenia_no_se_guarda_al_regresar_con_errores(): void
    {
        $this->post('/login', ['correo' => 'nadie@prueba.test', 'contrasenia' => 'secreta']);

        $this->assertSame('nadie@prueba.test', session()->getOldInput('correo'));
        $this->assertNull(session()->getOldInput('contrasenia'));
    }

    public function test_campos_vacios_muestran_errores_y_no_registran_acceso(): void
    {
        $antes = DB::table('log_acceso')->count();

        $this->post('/login', ['correo' => '', 'contrasenia' => ''])
            ->assertSessionHasErrors([
                'correo' => 'Escribe tu correo electrónico.',
                'contrasenia' => 'Escribe tu contraseña.',
            ]);

        $this->assertSame($antes, DB::table('log_acceso')->count());
    }

    public function test_el_sexto_intento_en_un_minuto_se_bloquea_y_no_se_registra(): void
    {
        $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => 'ana@prueba.test']);
        $antes = DB::table('log_acceso')->count();

        for ($i = 0; $i < LoginRequest::INTENTOS_POR_MINUTO; $i++) {
            $this->post('/login', ['correo' => 'ana@prueba.test', 'contrasenia' => 'incorrecta']);
        }

        $this->post('/login', ['correo' => 'ana@prueba.test', 'contrasenia' => $this->contraseniaDePrueba])
            ->assertSessionHasErrors('correo');

        $this->assertStringStartsWith('Demasiados intentos.', session('errors')->first('correo'));
        $this->assertGuest();
        $this->assertSame($antes + LoginRequest::INTENTOS_POR_MINUTO, DB::table('log_acceso')->count());
    }

    public function test_cerrar_sesion_sale_y_registra_el_cierre(): void
    {
        $cuenta = $this->crearCuenta();

        $this->actingAs($cuenta)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertUltimoAcceso(ResultadoAcceso::CierreSesion, $cuenta->usua_id_usuario);
    }

    public function test_cerrar_sesion_no_funciona_con_un_enlace(): void
    {
        $this->actingAs($this->crearCuenta())
            ->get('/logout')
            ->assertMethodNotAllowed();
    }

    public function test_cuenta_desactivada_con_la_sesion_abierta_sale_en_la_siguiente_peticion(): void
    {
        $cuenta = $this->crearCuenta();

        DB::table('usuario')
            ->where('usua_id_usuario', $cuenta->usua_id_usuario)
            ->update(['usua_activo' => false]);

        $this->actingAs($cuenta)
            ->get('/inicio')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('correo');

        $this->assertGuest();
    }

    public function test_quien_no_inicio_sesion_no_ve_el_inicio(): void
    {
        $this->get('/inicio')->assertRedirect(route('login'));
    }

    public function test_quien_ya_inicio_sesion_no_ve_el_formulario(): void
    {
        $this->actingAs($this->crearCuenta())
            ->get('/login')
            ->assertRedirect(route('inicio'));
    }

    /**
     * Revisa la fila más reciente de LOG_ACCESO.
     */
    private function assertUltimoAcceso(ResultadoAcceso $resultado, ?int $idUsuario): void
    {
        $fila = DB::table('log_acceso')->orderByDesc('loga_id_log')->first();

        $this->assertNotNull($fila, 'No hay filas en LOG_ACCESO.');
        $this->assertSame($resultado->value, $fila->loga_resultado);
        $this->assertSame($idUsuario, $fila->loga_id_usuario);
        $this->assertSame(self::IP, $fila->loga_ip);
    }
}

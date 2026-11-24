<?php

namespace Tests\Feature;

use App\Http\Controllers\Usuarios\CuentaController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SolicitarRecuperacionRequest;
use App\Mail\EnlaceRecuperacion;
use App\Models\Rol;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Refuerzos de la revisión de seguridad:
 * - Límite por IP al iniciar sesión (probar una contraseña contra muchas cuentas).
 * - Límite por IP al pedir enlaces de recuperación (llenar de correos a otros).
 * - Límite al adivinar la contraseña actual en Cambiar contraseña.
 * - Un filtro enviado como lista (?texto[]=a) se ignora, sin error del servidor.
 * - Encabezados de seguridad completos y sin anunciar la versión de PHP.
 */
class SeguridadTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withServerVariables(['REMOTE_ADDR' => '10.9.8.7']);
    }

    public function test_muchos_correos_fallidos_desde_una_ip_la_bloquean_aunque_despues_llegue_la_contrasenia_correcta(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR);

        for ($i = 0; $i < LoginRequest::FALLIDOS_POR_IP; $i++) {
            $this->post('/login', ['correo' => "persona{$i}@prueba.test", 'contrasenia' => 'Comun2026!'])
                ->assertSessionHasErrors(['correo' => 'El correo o la contraseña no son correctos.']);
        }

        $this->post('/login', ['correo' => $cuenta->usua_correo, 'contrasenia' => $this->contraseniaDePrueba])
            ->assertSessionHasErrors(['correo' => 'Demasiados intentos. Espera 10 minutos e inténtalo de nuevo.']);
        $this->assertGuest();

        // Desde otra computadora sí puede entrar
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])
            ->post('/login', ['correo' => $cuenta->usua_correo, 'contrasenia' => $this->contraseniaDePrueba])
            ->assertRedirect(route('inicio'));
    }

    public function test_muchas_solicitudes_de_recuperacion_desde_una_ip_se_detienen(): void
    {
        Mail::fake();
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR);

        for ($i = 0; $i < SolicitarRecuperacionRequest::SOLICITUDES_POR_IP; $i++) {
            $this->post(route('contrasenia.enviar'), ['correo' => "otra{$i}@prueba.test"])->assertSessionHasNoErrors();
        }

        $this->post(route('contrasenia.enviar'), ['correo' => $cuenta->usua_correo])
            ->assertSessionHasErrors(['correo' => 'Ya pediste varios códigos. Espera 60 minutos y vuelve a intentarlo.']);
        Mail::assertNothingSent();
    }

    public function test_el_enlace_de_recuperacion_se_envia_despues_de_responder(): void
    {
        Mail::fake();
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR);

        $this->post(route('contrasenia.enviar'), ['correo' => $cuenta->usua_correo])
            ->assertRedirect(route('contrasenia.solicitar'));

        Mail::assertSent(EnlaceRecuperacion::class, fn (EnlaceRecuperacion $correo) => $correo->hasTo($cuenta->usua_correo));
    }

    public function test_adivinar_la_contrasenia_actual_se_bloquea_tras_cinco_intentos(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR);
        $nueva = ['contrasenia' => 'Nueva-clave-2026!', 'contrasenia_confirmation' => 'Nueva-clave-2026!'];

        for ($i = 0; $i < CuentaController::INTENTOS_PERMITIDOS; $i++) {
            $this->actingAs($cuenta)->put('/cuenta/contrasenia', ['actual' => "Mala-{$i}!"] + $nueva)
                ->assertSessionHasErrors(['actual' => 'Tu contraseña actual no es correcta.']);
        }

        $this->actingAs($cuenta)->put('/cuenta/contrasenia', ['actual' => $this->contraseniaDePrueba] + $nueva)
            ->assertSessionHasErrors(['actual' => 'Escribiste mal tu contraseña actual varias veces. Espera 15 minutos y vuelve a intentarlo.']);
        $this->assertTrue(password_verify($this->contraseniaDePrueba, $cuenta->fresh()->usua_contrasenia));
    }

    public function test_un_filtro_enviado_como_lista_se_ignora_sin_error(): void
    {
        $admin = $this->crearCuenta(Rol::ADMINISTRADOR);

        foreach (['/personas?texto[]=a', '/instituciones?texto[]=a&zona[]=1', '/puestos?texto[]=a&page[]=2', '/personas/coincidencias?nombre[]=a&curp[]=b', '/mi-actividad?texto[]=a'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_encabezados_de_seguridad(): void
    {
        $respuesta = $this->get('/login');

        $respuesta->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeaderMissing('X-Powered-By')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}

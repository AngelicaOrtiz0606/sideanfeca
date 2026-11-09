<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\RecuperacionController;
use App\Http\Requests\Auth\SolicitarRecuperacionRequest;
use App\Mail\EnlaceRecuperacion;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\RecuperacionService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Criterios de M1 (recuperación de contraseña por enlace):
 * - Token con hash SHA-256, vence en 15 minutos, se bloquea tras 5 intentos.
 * - Tarea programada cada hora que llama limpiar_recuperaciones.
 */
class RecuperacionTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private const CORREO = 'ana@prueba.test';

    private const NUEVA = 'Nueva2026!';

    private const NO_VALIDO = 'Este enlace venció o ya se usó.';

    private const BLOQUEADO = 'Este enlace se bloqueó por demasiados intentos.';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_el_login_lleva_a_la_pantalla_que_pide_el_correo(): void
    {
        $this->get('/login')->assertSee('href="'.route('contrasenia.solicitar').'"', false);

        $this->get(route('contrasenia.solicitar'))
            ->assertOk()
            ->assertSee('Recuperar contraseña')
            ->assertSee('name="correo"', false)
            ->assertSee('Enviar enlace');
    }

    public function test_envia_un_enlace_y_solo_guarda_el_hash_del_token(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);

        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO])
            ->assertRedirect(route('contrasenia.solicitar'))
            ->assertSessionHas('estado', RecuperacionController::MENSAJE_ENVIADO);

        [$id, $token] = $this->enlaceEnviado();
        $fila = DB::table('recuperacion_contrasenia')->where('reco_id_usuario', $cuenta->usua_id_usuario)->first();

        $this->assertSame($fila->reco_id_recuperacion, $id);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{64}$/', $token);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $fila->reco_token_hash);
        $this->assertNotSame($token, $fila->reco_token_hash);
        $this->assertSame(0, $fila->reco_intentos);
    }

    public function test_el_enlace_vence_a_los_15_minutos(): void
    {
        $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);

        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);

        $segundos = DB::selectOne(
            'select extract(epoch from reco_fecha_expira - reco_fecha_creacion)::int as segundos from recuperacion_contrasenia'
        )->segundos;

        $this->assertSame(15 * 60, $segundos);
    }

    public function test_el_correo_trae_el_enlace_y_su_vigencia(): void
    {
        $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);

        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);

        Mail::assertSent(EnlaceRecuperacion::class, function (EnlaceRecuperacion $correo): bool {
            $correo->assertSeeInHtml('Crear contraseña nueva');
            $correo->assertSeeInHtml('15 minutos');
            $correo->assertSeeInText($correo->url);

            return $correo->hasTo(self::CORREO);
        });
    }

    public function test_un_correo_no_registrado_ve_el_mismo_mensaje_y_no_recibe_nada(): void
    {
        $this->followRedirects($this->post(route('contrasenia.enviar'), ['correo' => 'nadie@prueba.test']))
            ->assertSee('Si el correo está registrado, te enviamos un enlace');

        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('recuperacion_contrasenia')->count());
    }

    public function test_una_cuenta_inactiva_no_recibe_enlace(): void
    {
        $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO, 'usua_activo' => false]);

        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO])
            ->assertSessionHas('estado', RecuperacionController::MENSAJE_ENVIADO);

        Mail::assertNothingSent();
    }

    public function test_el_enlace_abre_el_formulario_de_contrasenia_nueva_y_confirmacion(): void
    {
        $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);
        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);
        [$id, $token] = $this->enlaceEnviado();

        $this->get(route('contrasenia.restablecer', ['solicitud' => $id, 'token' => $token]))
            ->assertOk()
            ->assertSee('Crear contraseña nueva')
            ->assertSee('name="contrasenia"', false)
            ->assertSee('name="contrasenia_confirmation"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSeeInOrder(['8 caracteres o más', 'Una mayúscula', 'Un número', 'Un carácter especial'])
            ->assertSee('data-igual-a="contrasenia"', false);
    }

    public function test_con_el_enlace_correcto_cambia_la_contrasenia(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);
        $bitacoraAntes = DB::table('bitacora')->count();

        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);
        [$id, $token] = $this->enlaceEnviado();

        $this->guardar($id, $token)
            ->assertRedirect(route('login'))
            ->assertSessionHas('estado', 'Tu contraseña se cambió. Ya puedes iniciar sesión.');

        $this->assertTrue(Hash::check(self::NUEVA, Usuario::findOrFail($cuenta->usua_id_usuario)->usua_contrasenia));
        $this->assertNotNull(DB::table('recuperacion_contrasenia')->value('reco_fecha_uso'));

        // El cambio de contraseña no se registra en la bitácora (decisión del proyecto)
        $this->assertSame($bitacoraAntes, DB::table('bitacora')->count());

        $this->post('/login', ['correo' => self::CORREO, 'contrasenia' => self::NUEVA])->assertRedirect(route('inicio'));
    }

    public function test_el_enlace_solo_sirve_una_vez(): void
    {
        $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);
        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);
        [$id, $token] = $this->enlaceEnviado();

        $this->guardar($id, $token)->assertRedirect(route('login'));

        $this->get(route('contrasenia.restablecer', ['solicitud' => $id, 'token' => $token]))->assertSee(self::NO_VALIDO);
        $this->guardar($id, $token, 'Otra2026!')->assertSee(self::NO_VALIDO);
    }

    public function test_un_enlace_vencido_no_sirve(): void
    {
        $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);
        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);
        [$id, $token] = $this->enlaceEnviado();

        DB::table('recuperacion_contrasenia')->update([
            'reco_fecha_creacion' => DB::raw("now() - interval '20 minutes'"),
            'reco_fecha_expira' => DB::raw("now() - interval '5 minutes'"),
        ]);

        $this->get(route('contrasenia.restablecer', ['solicitud' => $id, 'token' => $token]))
            ->assertSee(self::NO_VALIDO)
            ->assertSee('Pedir un enlace nuevo');
    }

    public function test_un_token_equivocado_cuenta_como_intento(): void
    {
        $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);
        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);
        [$id] = $this->enlaceEnviado();

        $this->get(route('contrasenia.restablecer', ['solicitud' => $id, 'token' => str_repeat('x', 64)]))
            ->assertSee(self::NO_VALIDO);

        $this->assertSame(1, DB::table('recuperacion_contrasenia')->value('reco_intentos'));
    }

    public function test_al_quinto_intento_se_bloquea_aunque_despues_llegue_el_token_correcto(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);
        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);
        [$id, $token] = $this->enlaceEnviado();

        for ($i = 0; $i < RecuperacionService::INTENTOS_MAXIMOS - 1; $i++) {
            $this->guardar($id, str_repeat('x', 64));
        }

        $this->guardar($id, str_repeat('x', 64))->assertSee(self::BLOQUEADO);
        $this->guardar($id, $token)->assertSee(self::BLOQUEADO);

        $this->assertSame(5, DB::table('recuperacion_contrasenia')->value('reco_intentos'));
        $this->assertTrue(Hash::check($this->contraseniaDePrueba, Usuario::findOrFail($cuenta->usua_id_usuario)->usua_contrasenia));
    }

    public function test_un_enlace_nuevo_reemplaza_al_anterior(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);

        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);
        [$idAnterior, $tokenAnterior] = $this->enlaceEnviado();
        Mail::fake();
        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);

        $this->assertSame(1, DB::table('recuperacion_contrasenia')->where('reco_id_usuario', $cuenta->usua_id_usuario)->count());
        $this->get(route('contrasenia.restablecer', ['solicitud' => $idAnterior, 'token' => $tokenAnterior]))
            ->assertSee(self::NO_VALIDO);
    }

    public function test_si_la_cuenta_se_desactiva_el_enlace_deja_de_servir(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);
        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);
        [$id, $token] = $this->enlaceEnviado();

        DB::table('usuario')->where('usua_id_usuario', $cuenta->usua_id_usuario)->update(['usua_activo' => false]);

        $this->guardar($id, $token)->assertSee(self::NO_VALIDO);
    }

    public function test_la_contrasenia_nueva_debe_cumplir_la_regla_sin_gastar_intentos(): void
    {
        $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);
        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);
        [$id, $token] = $this->enlaceEnviado();

        foreach (['solotexto', 'Ab1!', 'sinmayuscula1!', 'SinEspecial1', 'SinNumero!'] as $debil) {
            $this->guardar($id, $token, $debil)->assertSessionHasErrors('contrasenia');
        }

        $this->assertSame(0, DB::table('recuperacion_contrasenia')->value('reco_intentos'));
        $this->assertNull(DB::table('recuperacion_contrasenia')->value('reco_fecha_uso'));
    }

    public function test_las_dos_contrasenias_deben_coincidir(): void
    {
        $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);
        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);
        [$id, $token] = $this->enlaceEnviado();

        $this->post(route('contrasenia.actualizar', ['solicitud' => $id, 'token' => $token]), [
            'contrasenia' => self::NUEVA,
            'contrasenia_confirmation' => 'Distinta2026!',
        ])->assertSessionHasErrors(['contrasenia' => 'Las dos contraseñas no coinciden. Escríbelas de nuevo.']);
    }

    public function test_las_contrasenias_no_se_guardan_al_regresar_con_errores(): void
    {
        $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);
        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);
        [$id, $token] = $this->enlaceEnviado();

        $this->guardar($id, $token, 'solotexto');

        $this->assertNull(session()->getOldInput('contrasenia'));
        $this->assertNull(session()->getOldInput('contrasenia_confirmation'));
    }

    public function test_limita_las_solicitudes_de_enlace(): void
    {
        $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);

        for ($i = 0; $i < SolicitarRecuperacionRequest::SOLICITUDES_PERMITIDAS; $i++) {
            $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO]);
        }

        $this->post(route('contrasenia.enviar'), ['correo' => self::CORREO])->assertSessionHasErrors('correo');

        Mail::assertSentCount(SolicitarRecuperacionRequest::SOLICITUDES_PERMITIDAS);
    }

    public function test_el_comando_borra_las_solicitudes_vencidas_y_usadas(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => self::CORREO]);
        $base = ['reco_id_usuario' => $cuenta->usua_id_usuario];

        // Vencida
        DB::table('recuperacion_contrasenia')->insert($base + [
            'reco_token_hash' => str_repeat('a', 64),
            'reco_fecha_creacion' => DB::raw("now() - interval '30 minutes'"),
            'reco_fecha_expira' => DB::raw("now() - interval '15 minutes'"),
        ]);
        // Ya usada
        DB::table('recuperacion_contrasenia')->insert($base + [
            'reco_token_hash' => str_repeat('b', 64),
            'reco_fecha_expira' => DB::raw("now() + interval '15 minutes'"),
            'reco_fecha_uso' => DB::raw('now()'),
        ]);
        // Vigente: se queda
        DB::table('recuperacion_contrasenia')->insert($base + [
            'reco_token_hash' => str_repeat('c', 64),
            'reco_fecha_expira' => DB::raw("now() + interval '15 minutes'"),
        ]);

        $this->artisan('sideanfeca:limpiar-recuperaciones')
            ->expectsOutput('Solicitudes borradas: 2')
            ->assertExitCode(0);

        $this->assertSame([str_repeat('c', 64)], DB::table('recuperacion_contrasenia')->pluck('reco_token_hash')->all());
    }

    public function test_la_limpieza_esta_programada_cada_hora(): void
    {
        $tarea = collect(app(Schedule::class)->events())
            ->first(fn ($evento) => str_contains($evento->command ?? '', 'sideanfeca:limpiar-recuperaciones'));

        $this->assertNotNull($tarea, 'La tarea de limpieza no está programada.');
        $this->assertSame('0 * * * *', $tarea->expression);
    }

    /**
     * Número de solicitud y token del último enlace enviado.
     *
     * @return array{0: int, 1: string}
     */
    private function enlaceEnviado(): array
    {
        $url = null;

        Mail::assertSent(EnlaceRecuperacion::class, function (EnlaceRecuperacion $correo) use (&$url): bool {
            $url = $correo->url;

            return true;
        });

        preg_match('#/recuperar-contrasenia/(\d+)/([A-Za-z0-9]{64})$#', (string) $url, $partes);

        return [(int) $partes[1], $partes[2]];
    }

    private function guardar(int $id, string $token, string $nueva = self::NUEVA): TestResponse
    {
        return $this->post(route('contrasenia.actualizar', ['solicitud' => $id, 'token' => $token]), [
            'contrasenia' => $nueva,
            'contrasenia_confirmation' => $nueva,
        ]);
    }
}

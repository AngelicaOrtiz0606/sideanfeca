<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ResultadoRestablecimiento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RestablecerContraseniaRequest;
use App\Http\Requests\Auth\SolicitarRecuperacionRequest;
use App\Services\RecuperacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Recuperación de contraseña desde el login:
 * 1. La persona escribe su correo y recibe un enlace.
 * 2. Al dar clic, ve el formulario de contraseña nueva y su confirmación.
 */
class RecuperacionController extends Controller
{
    public const MENSAJE_ENVIADO = 'Si el correo está registrado, te enviamos un enlace para crear una contraseña nueva. Vence en 15 minutos. Si no lo ves, revisa también tu correo no deseado.';

    public function __construct(private readonly RecuperacionService $recuperacion) {}

    public function mostrarSolicitud(): View
    {
        return view('auth.recuperar');
    }

    public function enviar(SolicitarRecuperacionRequest $request): RedirectResponse
    {
        $request->asegurarLimite();

        try {
            $this->recuperacion->solicitar($request->correo());
        } catch (Throwable $error) {
            Log::error('No se pudo enviar el enlace de recuperación.', ['error' => $error->getMessage()]);

            return back()->withInput()->withErrors([
                'correo' => 'No pudimos enviar el correo en este momento. Inténtalo de nuevo en unos minutos.',
            ]);
        }

        return redirect()->route('contrasenia.solicitar')->with('estado', self::MENSAJE_ENVIADO);
    }

    public function mostrarRestablecer(int $solicitud, string $token): View
    {
        $resultado = $this->recuperacion->verificar($solicitud, $token);

        if ($resultado !== ResultadoRestablecimiento::Valido) {
            return $this->enlaceNoValido($resultado);
        }

        return view('auth.restablecer', ['solicitud' => $solicitud, 'token' => $token]);
    }

    public function restablecer(RestablecerContraseniaRequest $request, int $solicitud, string $token): RedirectResponse|View
    {
        $resultado = $this->recuperacion->restablecer(
            $solicitud,
            $token,
            (string) $request->validated('contrasenia'),
        );

        if ($resultado !== ResultadoRestablecimiento::Valido) {
            return $this->enlaceNoValido($resultado);
        }

        return redirect()->route('login')->with('estado', 'Tu contraseña se cambió. Ya puedes iniciar sesión.');
    }

    private function enlaceNoValido(ResultadoRestablecimiento $resultado): View
    {
        return view('auth.enlace-no-valido', [
            'mensaje' => $resultado === ResultadoRestablecimiento::Bloqueado
                ? 'Este enlace se bloqueó por demasiados intentos. Pide un enlace nuevo.'
                : 'Este enlace venció o ya se usó. Los enlaces duran 15 minutos y sirven una sola vez. Pide un enlace nuevo.',
        ]);
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ResultadoAcceso;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AccesoService;
use App\Services\AutenticacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MENSAJE_FALLIDO = 'El correo o la contraseña no son correctos.';

    private const MENSAJE_INACTIVA = 'Tu cuenta está desactivada. Comunícate con la persona responsable del sistema.';

    public function __construct(
        private readonly AutenticacionService $autenticacion,
        private readonly AccesoService $accesos,
    ) {}

    public function mostrar(): View
    {
        return view('auth.login');
    }

    public function iniciar(LoginRequest $request): RedirectResponse
    {
        $request->asegurarQueNoEsteBloqueado();

        $intento = $this->autenticacion->intentar(
            $request->correo(),
            $request->contrasenia(),
            $this->ip($request),
        );

        if (! $intento->fueExitoso()) {
            $request->registrarIntentoFallido();

            throw ValidationException::withMessages([
                'correo' => $intento->resultado === ResultadoAcceso::CuentaInactiva
                    ? self::MENSAJE_INACTIVA
                    : self::MENSAJE_FALLIDO,
            ]);
        }

        $request->limpiarIntentos();

        Auth::login($intento->usuario);
        $request->session()->regenerate();

        return redirect()->intended(route('inicio'));
    }

    public function cerrar(Request $request): RedirectResponse
    {
        $this->accesos->registrar(
            $request->user()->usua_id_usuario,
            ResultadoAcceso::CierreSesion,
            $this->ip($request),
        );

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function ip(Request $request): string
    {
        return $request->ip() ?? '0.0.0.0';
    }
}

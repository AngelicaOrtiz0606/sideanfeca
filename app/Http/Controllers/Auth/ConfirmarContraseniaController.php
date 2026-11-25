<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ConfirmarContrasenia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Confirmar la contraseña antes de una acción delicada (ver ConfirmarContrasenia).
 * Tras 5 intentos equivocados hay que esperar 15 minutos.
 */
class ConfirmarContraseniaController extends Controller
{
    public const INTENTOS_PERMITIDOS = 5;

    public const VENTANA_SEGUNDOS = 900;

    public function mostrar(): View
    {
        return view('auth.confirmar-contrasenia');
    }

    public function confirmar(Request $request): RedirectResponse
    {
        $cuenta = $request->user();
        $limite = 'confirmar-contrasenia|'.$cuenta->getKey();

        if (RateLimiter::tooManyAttempts($limite, self::INTENTOS_PERMITIDOS)) {
            $minutos = (int) ceil(RateLimiter::availableIn($limite) / 60);

            throw ValidationException::withMessages([
                'contrasenia' => "Escribiste mal tu contraseña varias veces. Espera {$minutos} minutos y vuelve a intentarlo.",
            ]);
        }

        $request->validate(['contrasenia' => ['required', 'string', 'max:255']], ['contrasenia.required' => 'Escribe tu contraseña.']);

        if (! Hash::check((string) $request->input('contrasenia'), $cuenta->getAuthPassword())) {
            RateLimiter::hit($limite, self::VENTANA_SEGUNDOS);

            throw ValidationException::withMessages(['contrasenia' => 'La contraseña no es correcta.']);
        }

        RateLimiter::clear($limite);
        $request->session()->put(ConfirmarContrasenia::CLAVE, time());

        return redirect()->intended(route('usuarios.index'));
    }
}

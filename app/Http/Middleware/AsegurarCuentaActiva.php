<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si la cuenta se desactivó (o se anonimizó) mientras la sesión estaba
 * abierta, cierra la sesión en la siguiente petición.
 *
 * Se consulta la base en cada petición para no confiar en una copia
 * guardada de la cuenta.
 */
class AsegurarCuentaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $cuenta = $request->user();

        if ($cuenta !== null && ! $this->sigueActiva($cuenta)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'correo' => 'Tu sesión se cerró porque la cuenta ya no está activa.',
            ]);
        }

        return $next($request);
    }

    private function sigueActiva(Usuario $cuenta): bool
    {
        return Usuario::query()
            ->whereKey($cuenta->getKey())
            ->where('usua_activo', true)
            ->whereNotNull('usua_id_persona')
            ->exists();
    }
}

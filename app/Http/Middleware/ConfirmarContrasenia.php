<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Acciones delicadas (crear, editar, activar o desactivar cuentas): piden
 * volver a escribir la contraseña. Así, quien encuentre una sesión abierta
 * no puede cambiar el correo de una cuenta a uno suyo y quedarse con ella.
 * La confirmación dura 15 minutos.
 *
 * - Al abrir una pantalla: se pide la contraseña y luego se regresa a ella.
 * - Al guardar (ya pasados los 15 minutos): se pide la contraseña y se
 *   regresa a la pantalla anterior para volver a guardar.
 */
class ConfirmarContrasenia
{
    public const CLAVE = 'contrasenia_confirmada_en';

    public const MINUTOS = 15;

    public function handle(Request $request, Closure $next): Response
    {
        $confirmada = (int) $request->session()->get(self::CLAVE, 0);
        if ($confirmada > 0 && time() - $confirmada < self::MINUTOS * 60) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('contrasenia.confirmar');
        }

        // Solo se regresa a una pantalla del propio sistema
        $anterior = url()->previous(route('usuarios.index'));
        $request->session()->put('url.intended', str_starts_with($anterior, url('/').'/') ? $anterior : route('usuarios.index'));

        return redirect()->route('contrasenia.confirmar')
            ->with('aviso', 'Por seguridad, confirma tu contraseña. Después vuelve a guardar.');
    }
}

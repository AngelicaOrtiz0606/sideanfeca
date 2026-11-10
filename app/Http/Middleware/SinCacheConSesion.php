<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pide al navegador que no guarde copia de las pantallas del sistema.
 *
 * Sin esto, después de cerrar sesión la flecha de regreso del navegador
 * vuelve a mostrar la pantalla anterior (copia guardada) aunque la sesión
 * ya no exista. Con no-store, el navegador tiene que pedirla de nuevo y el
 * sistema lo manda al inicio de sesión.
 *
 * También evita que el regreso muestre un formulario viejo (con un token
 * de seguridad que ya no sirve).
 */
class SinCacheConSesion
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        $respuesta->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $respuesta->headers->set('Pragma', 'no-cache');
        $respuesta->headers->set('Expires', '0');

        return $respuesta;
    }
}

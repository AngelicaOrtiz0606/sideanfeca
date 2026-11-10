<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agrega a todas las pantallas los encabezados de seguridad del navegador.
 *
 * - X-Frame-Options y frame-ancestors: ninguna otra página puede mostrar el
 *   sistema dentro de un marco (evita que engañen a alguien con un login
 *   escondido debajo de otra página).
 * - X-Content-Type-Options: el navegador no adivina el tipo de archivo.
 * - Referrer-Policy: la dirección completa no se pasa a otros sitios (así el
 *   enlace de recuperación nunca sale del sistema).
 */
class EncabezadosSeguridad
{
    private const ENCABEZADOS = [
        'X-Frame-Options' => 'DENY',
        'Content-Security-Policy' => "frame-ancestors 'none'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        foreach (self::ENCABEZADOS as $nombre => $valor) {
            $respuesta->headers->set($nombre, $valor);
        }

        return $respuesta;
    }
}

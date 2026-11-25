<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agrega a todas las pantallas los encabezados de seguridad del navegador.
 *
 * - Content-Security-Policy: el navegador solo ejecuta los scripts del propio
 *   sistema (nunca código escrito dentro de la página o traído de otro sitio).
 *   Aunque alguien lograra guardar código en un nombre, no se ejecutaría.
 *   También impide mostrar el sistema dentro de un marco y enviar formularios
 *   a otro sitio.
 * - X-Frame-Options: lo mismo que frame-ancestors, para navegadores antiguos.
 * - X-Content-Type-Options: el navegador no adivina el tipo de archivo.
 * - Referrer-Policy: la dirección completa no se pasa a otros sitios (así el
 *   enlace de recuperación nunca sale del sistema).
 * - Permissions-Policy: el sistema no usa cámara, micrófono, ubicación ni pagos;
 *   ninguna página los puede pedir.
 * - Cross-Origin-Opener-Policy: otra pestaña abierta desde otro sitio no puede
 *   manipular la ventana del sistema.
 * - Strict-Transport-Security (solo cuando el sitio ya va por HTTPS): el
 *   navegador recuerda usar siempre HTTPS durante un año.
 * - Se quita X-Powered-By: no se anuncia la versión de PHP.
 *
 * Con APP_DEBUG=true (máquina de práctica) o con "npm run dev" solo se aplica
 * frame-ancestors: la página de errores de Laravel y el servidor de Vite usan
 * scripts que la política completa bloquearía. En el servidor oficial
 * (APP_DEBUG=false) se aplica completa.
 */
class EncabezadosSeguridad
{
    private const POLITICA = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self'",
        "img-src 'self' data:",
        "font-src 'self'",
        "connect-src 'self'",
        "form-action 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'none'",
    ];

    private const ENCABEZADOS = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        foreach (self::ENCABEZADOS as $nombre => $valor) {
            $respuesta->headers->set($nombre, $valor);
        }

        if ($request->isSecure()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // PHP lo agrega por su cuenta; se quita aquí y también conviene expose_php = Off
        header_remove('X-Powered-By');
        $respuesta->headers->remove('X-Powered-By');

        $completa = ! config('app.debug') && ! Vite::isRunningHot();
        $respuesta->headers->set('Content-Security-Policy', $completa ? implode('; ', self::POLITICA) : "frame-ancestors 'none'");

        return $respuesta;
    }
}

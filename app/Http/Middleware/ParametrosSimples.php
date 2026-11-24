<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ningún filtro de la dirección (?texto=, ?zona=, ?page=…) recibe una lista:
 * si alguien escribe ?texto[]=a a mano, ese parámetro se ignora como si no
 * viniera, en vez de terminar en un error del servidor.
 *
 * Solo toca la dirección: los formularios que envían listas (designaciones,
 * contactos, grados) van en el cuerpo de la petición y no cambian.
 */
class ParametrosSimples
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->query->all() as $nombre => $valor) {
            if (is_array($valor)) {
                $request->query->remove($nombre);
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Pantalla provisional para las direcciones que ya existen y ya están
 * protegidas con su privilegio, pero cuyo módulo todavía no se programa
 * (por ejemplo, la bitácora completa en M9). Al llegar su módulo, la ruta
 * pasa a su propio controlador y esta pantalla deja de usarse para ella.
 */
class PantallaPendienteController extends Controller
{
    public function __invoke(string $titulo): View
    {
        return view('pendiente', ['titulo' => $titulo]);
    }
}

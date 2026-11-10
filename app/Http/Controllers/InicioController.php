<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\InicioService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantalla de inicio (sección 10): saludo, contadores y, solo para quien
 * puede ver la bitácora (Superadmin), los últimos movimientos.
 */
class InicioController extends Controller
{
    public function __invoke(Request $request, InicioService $inicio): View
    {
        /** @var Usuario $cuenta */
        $cuenta = $request->user();
        $verBitacora = $cuenta->tienePrivilegio('ver_bitacora');
        $bitacora = $verBitacora ? $inicio->bitacoraReciente() : ['movimientos' => collect(), 'hayMas' => false];

        return view('inicio', [
            'nombre' => $cuenta->persona?->nombre_completo,
            'genero' => $cuenta->persona?->pers_genero,
            'perfil' => $cuenta->etiquetaPerfil(),
            'inicioAnterior' => $inicio->inicioDeSesionAnterior($cuenta),
            'contadores' => $inicio->contadores(),
            'verBitacora' => $verBitacora,
            'movimientos' => $bitacora['movimientos'],
            'hayMasMovimientos' => $bitacora['hayMas'],
        ]);
    }
}

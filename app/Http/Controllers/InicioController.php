<?php

namespace App\Http\Controllers;

use App\Enums\Privilegio;
use App\Models\Usuario;
use App\Services\InicioService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantalla de inicio (sección 10): saludo, contadores, pendientes por atender
 * y los últimos movimientos: de todos para quien ve la bitácora
 * (superadministrador); los propios para los demás ("Mi actividad").
 */
class InicioController extends Controller
{
    public function __invoke(Request $request, InicioService $inicio): View
    {
        /** @var Usuario $cuenta */
        $cuenta = $request->user();
        $verBitacora = $cuenta->can(Privilegio::VerBitacora->value);
        // Quien ve la bitácora ve la actividad de todos; los demás, la suya
        $bitacora = $inicio->bitacoraReciente($verBitacora ? null : $cuenta->usua_id_usuario);

        return view('inicio', [
            'nombre' => $cuenta->persona?->nombre_completo,
            'genero' => $cuenta->persona?->pers_genero,
            'perfil' => $cuenta->etiquetaPerfil(),
            'inicioAnterior' => $inicio->inicioDeSesionAnterior($cuenta),
            'contadores' => $inicio->contadores(),
            'verBitacora' => $verBitacora,
            'movimientos' => $bitacora['movimientos'],
            'hayMasMovimientos' => $bitacora['hayMas'],
            'porAtender' => $inicio->porAtender($cuenta),
        ]);
    }
}

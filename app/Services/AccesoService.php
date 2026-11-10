<?php

namespace App\Services;

use App\Enums\ResultadoAcceso;
use Illuminate\Support\Facades\DB;

/**
 * Registro de inicios y cierres de sesión en LOG_ACCESO.
 *
 * Nunca inserta directo en la tabla: llama a la función SQL
 * registrar_acceso. LOG_ACCESO no guarda correos, solo la cuenta (si se
 * conoce), el resultado, la IP y la hora.
 */
class AccesoService
{
    /**
     * @param  int|null  $idUsuario  null solo cuando el correo no existe (resultado Fallido)
     */
    public function registrar(?int $idUsuario, ResultadoAcceso $resultado, string $ip): void
    {
        DB::select('select registrar_acceso(?, ?, ?)', [$idUsuario, $resultado->value, $ip]);
    }
}

<?php

namespace App\Services;

use App\Enums\ResultadoAcceso;
use App\Models\Usuario;

/**
 * Resultado de evaluar un intento de inicio de sesión.
 * La cuenta solo viene cuando el intento fue exitoso.
 */
final readonly class IntentoInicioSesion
{
    public function __construct(
        public ResultadoAcceso $resultado,
        public ?Usuario $usuario = null,
    ) {}

    public function fueExitoso(): bool
    {
        return $this->resultado === ResultadoAcceso::Exitoso && $this->usuario !== null;
    }
}

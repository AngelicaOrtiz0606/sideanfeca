<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Texto de un catálogo sin nada que pueda usarse para inyectar:
 * - sin caracteres de control (saltos de línea, tabuladores, nulos…);
 * - sin < ni > (nada que parezca una etiqueta de HTML);
 * - sin empezar con = + - @ (en una hoja de cálculo se tomarían como fórmula
 *   al abrir el CSV de los directorios: "inyección de fórmulas").
 *
 * Es una defensa extra: la pantalla siempre escapa el texto y la base solo
 * recibe consultas con parámetros.
 */
class TextoSeguro implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (preg_match('/[\x{0000}-\x{001F}\x{007F}]/u', $value)) {
            $fail('El texto tiene caracteres que no se permiten (por ejemplo, saltos de línea).');

            return;
        }

        if (preg_match('/[<>]/u', $value)) {
            $fail('El texto no puede llevar los símbolos < ni >.');

            return;
        }

        if (preg_match('/^\s*[=+\-@]/u', $value)) {
            $fail('El texto no puede empezar con =, +, - ni @.');
        }
    }
}

<?php

namespace App\Support;

use Normalizer;

/**
 * Cómo se guardan y se comparan los nombres de los catálogos.
 *
 * - Se guardan tal como se escriben (respetando siglas como ANFECA), solo sin
 *   espacios de sobra.
 * - Se comparan sin importar mayúsculas, acentos ni espacios: "Presidente",
 *   "PRESIDENTE", "presidénte" y "  Presidente " son el mismo nombre.
 *
 * resources/js/modulos/nombres-unicos.js usa la misma regla mientras se escribe.
 */
final class TextoCatalogo
{
    /**
     * "  Coordinador   Nacional " → "Coordinador Nacional"
     */
    public static function limpiar(?string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $texto));
    }

    /**
     * "Presidénte  NACIONAL" → "presidente nacional". La ñ se conserva
     * (es otra letra); solo se quitan acentos, diéresis y circunflejos.
     */
    public static function clave(?string $texto): string
    {
        $separado = (string) Normalizer::normalize(self::limpiar($texto), Normalizer::FORM_D);
        $sinAcentos = (string) preg_replace('/[\x{0300}\x{0301}\x{0302}\x{0308}]/u', '', $separado);

        return mb_strtolower((string) Normalizer::normalize($sinAcentos, Normalizer::FORM_C));
    }
}

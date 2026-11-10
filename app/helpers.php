<?php

/*
 * Funciones de ayuda de SIDEANFECA, disponibles en todo el proyecto
 * (se cargan desde composer.json, sección autoload.files).
 */

if (! function_exists('imagen')) {
    /**
     * Dirección pública de una imagen registrada en config/sideanfeca.php.
     * Ejemplo: <img src="{{ imagen('logo') }}">
     */
    function imagen(string $nombre): string
    {
        $ruta = config("sideanfeca.imagenes.{$nombre}");

        if ($ruta === null) {
            throw new InvalidArgumentException("La imagen '{$nombre}' no está registrada en config/sideanfeca.php.");
        }

        return asset($ruta);
    }
}

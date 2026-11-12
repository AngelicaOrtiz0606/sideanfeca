<?php

namespace App\Rules;

use App\Support\TextoCatalogo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * El nombre no puede repetir uno que ya existe en el catálogo, sin importar
 * mayúsculas, acentos ni espacios (ver TextoCatalogo). Al editar, no se compara
 * consigo mismo.
 *
 * El mensaje dice cuál es el que ya existe, por ejemplo:
 *   new NoRepetidoEnCatalogo('cargo', 'carg_nombre_m', 'carg_id_cargo', $id, 'Ya existe el cargo «:existente».')
 */
class NoRepetidoEnCatalogo implements ValidationRule
{
    public function __construct(
        private readonly string $tabla,
        private readonly string $columna,
        private readonly string $columnaId,
        private readonly ?int $excepto,
        private readonly string $mensaje,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $clave = TextoCatalogo::clave(is_scalar($value) ? (string) $value : '');

        // Los catálogos son pequeños: se comparan en PHP con la misma regla que en la pantalla
        $existente = DB::table($this->tabla)
            ->when($this->excepto, fn ($q) => $q->where($this->columnaId, '!=', $this->excepto))
            ->pluck($this->columna)
            ->first(fn ($nombre) => TextoCatalogo::clave((string) $nombre) === $clave);

        if ($existente !== null) {
            $fail(str_replace(':existente', (string) $existente, $this->mensaje));
        }
    }
}

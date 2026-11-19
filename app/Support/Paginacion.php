<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Paginación de las listas: de 10 en 10 en todo el sistema (salvo la
 * actividad reciente del inicio).
 *
 * - Las listas que vienen de una consulta usan ->paginate(Paginacion::POR_PAGINA).
 * - Las que ya están en memoria (catálogos con datos calculados) usan
 *   Paginacion::de($coleccion). Los enlaces conservan los filtros, y si la
 *   página pedida ya no existe (por ejemplo, tras borrar el último registro
 *   de la última página) se muestra la última que sí existe.
 */
final class Paginacion
{
    public const POR_PAGINA = 10;

    /**
     * @template T
     *
     * @param  Collection<int, T>  $elementos
     * @return LengthAwarePaginator<int, T>
     */
    public static function de(Collection $elementos, string $nombre = 'page'): LengthAwarePaginator
    {
        $total = $elementos->count();
        $ultima = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min(max(1, (int) LengthAwarePaginator::resolveCurrentPage($nombre)), $ultima);

        return new LengthAwarePaginator(
            $elementos->forPage($pagina, self::POR_PAGINA)->values(),
            $total,
            self::POR_PAGINA,
            $pagina,
            ['path' => request()->url(), 'pageName' => $nombre, 'query' => request()->query()],
        );
    }

    /**
     * Elementos cuyo texto contiene lo buscado, sin importar mayúsculas,
     * acentos ni espacios de sobra (la ñ cuenta como letra distinta).
     *
     * @template T
     *
     * @param  Collection<int, T>  $elementos
     * @param  callable(T): string  $texto
     * @return Collection<int, T>
     */
    public static function buscar(Collection $elementos, ?string $buscado, callable $texto): Collection
    {
        $clave = TextoCatalogo::clave((string) $buscado);

        return $clave === '' ? $elementos
            : $elementos->filter(fn ($elemento) => str_contains(TextoCatalogo::clave($texto($elemento)), $clave))->values();
    }
}

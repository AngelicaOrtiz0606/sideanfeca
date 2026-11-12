<?php

namespace App\Services\Catalogos;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lectura del catálogo de SEPOMEX (municipios y colonias).
 *
 * El catálogo no se edita en el sistema: se carga y se actualiza solo con
 * php artisan catalogos:importar-correos (archivo CPdescarga.txt). Aquí solo
 * se consulta, para llenar un domicilio a partir del código postal (M4).
 */
class CodigoPostalService
{
    /**
     * Todo lo de un código postal: sus colonias, el municipio, la entidad y
     * la zona sugerida. Un código postal puede tener colonias de más de un
     * municipio (pasa en pocos casos), por eso se agrupa por municipio.
     *
     * @return Collection<int, array{municipio_id: int, municipio: string, entidad_id: int, entidad: string, zona_id: int, zona: string, colonias: array<int, array{id: int, nombre: string}>}>
     */
    public function buscar(string $codigo): Collection
    {
        return DB::table('colonia as c')
            ->join('alcaldia_municipio as m', 'm.almu_id_alcaldia_municipio', '=', 'c.colo_id_alcaldia_municipio')
            ->join('entidad_federativa as e', 'e.enfe_id_entidad_federativa', '=', 'm.almu_id_entidad_federativa')
            ->join('zona_regional as z', 'z.zore_id_zona_regional', '=', 'e.enfe_id_zona_regional')
            ->where('c.colo_codigo_postal', $codigo)
            ->orderBy('m.almu_nombre')
            ->orderBy('c.colo_nombre')
            ->get([
                'c.colo_id_colonia as id', 'c.colo_nombre as nombre',
                'm.almu_id_alcaldia_municipio as municipio_id', 'm.almu_nombre as municipio',
                'e.enfe_id_entidad_federativa as entidad_id', 'e.enfe_nombre as entidad',
                'z.zore_id_zona_regional as zona_id', 'z.zore_numero as zona_numero', 'z.zore_nombre as zona_nombre',
            ])
            ->groupBy('municipio_id')
            ->map(function (Collection $colonias): array {
                $primera = $colonias->first();

                return [
                    'municipio_id' => (int) $primera->municipio_id,
                    'municipio' => $primera->municipio,
                    'entidad_id' => (int) $primera->entidad_id,
                    'entidad' => $primera->entidad,
                    'zona_id' => (int) $primera->zona_id,
                    'zona' => "Zona {$primera->zona_numero} · {$primera->zona_nombre}",
                    'colonias' => $colonias->map(fn (object $c) => ['id' => (int) $c->id, 'nombre' => $c->nombre])->values()->all(),
                ];
            })
            ->values();
    }
}

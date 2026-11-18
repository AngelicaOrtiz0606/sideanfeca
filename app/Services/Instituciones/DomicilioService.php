<?php

namespace App\Services\Instituciones;

use Illuminate\Support\Facades\DB;

/**
 * Domicilios de instituciones y organizaciones (tabla DOMICILIO): calle,
 * números y la colonia del catálogo de SEPOMEX. La entidad y el municipio
 * salen de la colonia; no se capturan.
 *
 * Cada domicilio pertenece a un solo registro, así que se actualiza en su
 * lugar. Se llama siempre dentro de la transacción de quien lo usa.
 */
class DomicilioService
{
    /**
     * Crea o actualiza el domicilio y devuelve su identificador.
     *
     * @param  array{colonia: int, calle: string, num_ext: string, num_int: ?string}  $datos
     */
    public function guardar(?int $id, array $datos): int
    {
        $valores = [
            'domi_id_colonia' => $datos['colonia'],
            'domi_calle' => $datos['calle'],
            'domi_num_ext' => $datos['num_ext'],
            'domi_num_int' => $datos['num_int'],
        ];

        if ($id !== null) {
            DB::table('domicilio')->where('domi_id_domicilio', $id)->update($valores);

            return $id;
        }

        return (int) DB::table('domicilio')->insertGetId($valores, 'domi_id_domicilio');
    }

    public function borrar(int $id): void
    {
        DB::table('domicilio')->where('domi_id_domicilio', $id)->delete();
    }

    /**
     * Datos del domicilio para el formulario y la consulta, o null.
     */
    public function datos(?int $id): ?object
    {
        if ($id === null) {
            return null;
        }

        return DB::table('domicilio as d')
            ->join('colonia as c', 'c.colo_id_colonia', '=', 'd.domi_id_colonia')
            ->join('alcaldia_municipio as m', 'm.almu_id_alcaldia_municipio', '=', 'c.colo_id_alcaldia_municipio')
            ->join('entidad_federativa as e', 'e.enfe_id_entidad_federativa', '=', 'm.almu_id_entidad_federativa')
            ->where('d.domi_id_domicilio', $id)
            ->first([
                'd.domi_calle as calle', 'd.domi_num_ext as num_ext', 'd.domi_num_int as num_int',
                'c.colo_id_colonia as colonia_id', 'c.colo_nombre as colonia', 'c.colo_codigo_postal as codigo_postal',
                'm.almu_nombre as municipio', 'e.enfe_nombre as entidad', 'e.enfe_id_zona_regional as zona_sugerida',
            ]);
    }

    /**
     * "Av. Universidad 3000 int. 2, Ciudad Universitaria, C.P. 04510, Coyoacán, Ciudad de México"
     * (para la bitácora y la consulta).
     */
    public function texto(?int $id): ?string
    {
        $d = $this->datos($id);

        if ($d === null) {
            return null;
        }

        $numero = $d->num_ext.($d->num_int ? " int. {$d->num_int}" : '');

        return "{$d->calle} {$numero}, {$d->colonia}, C.P. {$d->codigo_postal}, {$d->municipio}, {$d->entidad}";
    }
}

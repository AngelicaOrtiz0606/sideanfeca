<?php

namespace App\Services\Catalogos;

use App\Models\CoordinacionNacional;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Coordinaciones nacionales: registro, edición, activar o desactivar y orden.
 *
 * - El orden se cambia SOLO con la función SQL reordenar_coordinaciones, que
 *   revisa el privilegio, no deja huecos ni repetidos y registra la bitácora.
 * - Para lo demás no hay función SQL: se hace aquí y la bitácora se registra
 *   en la misma transacción.
 * - Desactivar no borra nada: los puestos y sus designaciones se conservan.
 * - Solo se borra si nunca tuvo puestos (por ejemplo, un error de captura), con
 *   motivo. Las que siguen suben un lugar con reordenar_coordinaciones, así el
 *   orden no queda con huecos.
 */
class CoordinacionService
{
    use BitacoraCatalogo;

    /**
     * Todas las coordinaciones en su orden, con cuántos puestos tiene cada una.
     *
     * @return Collection<int, object>
     */
    public function listado(): Collection
    {
        return DB::table('coordinacion_nacional as c')
            ->select('c.cona_id_coordinacion_nacional', 'c.cona_nombre', 'c.cona_descripcion', 'c.cona_orden', 'c.cona_activo')
            ->selectSub(fn ($q) => $q->from('puesto')->whereColumn('pues_id_coordinacion_nacional', 'c.cona_id_coordinacion_nacional')->selectRaw('count(*)'), 'puestos')
            ->orderBy('c.cona_orden')
            ->get()
            ->each(function (object $coordinacion): void {
                $coordinacion->motivo_para_no_borrar = $this->explicar((int) $coordinacion->puestos);
            });
    }

    /**
     * Puestos de la coordinación, con la persona que lo ocupa hoy (si hay).
     *
     * @return Collection<int, object>
     */
    public function puestos(CoordinacionNacional $coordinacion): Collection
    {
        return DB::table('puesto as p')
            ->join('cargo as c', 'c.carg_id_cargo', '=', 'p.pues_id_cargo')
            ->leftJoin('zona_regional as z', 'z.zore_id_zona_regional', '=', 'p.pues_id_zona_regional')
            ->leftJoin('designacion as d', function ($join) {
                $join->on('d.desi_id_puesto', '=', 'p.pues_id_puesto')
                    ->where('d.desi_fecha_inicio', '<=', DB::raw('current_date'))
                    ->where(fn ($q) => $q->whereNull('d.desi_fecha_fin')->orWhere('d.desi_fecha_fin', '>=', DB::raw('current_date')));
            })
            ->leftJoin('persona as per', 'per.pers_id_persona', '=', 'd.desi_id_persona')
            ->where('p.pues_id_coordinacion_nacional', $coordinacion->cona_id_coordinacion_nacional)
            ->orderBy('c.carg_nivel')
            ->orderBy('z.zore_numero')
            ->get([
                'p.pues_id_puesto as id',
                'p.pues_activo as activo',
                'c.carg_nombre_m',
                'c.carg_nombre_f',
                'z.zore_numero',
                'z.zore_nombre',
                'per.pers_genero',
                DB::raw("concat_ws(' ', per.pers_nombre, per.pers_primer_apellido, per.pers_segundo_apellido) as persona"),
                'd.desi_fecha_inicio as desde',
            ]);
    }

    public function registrar(Usuario $actor, string $nombre, ?string $descripcion): CoordinacionNacional
    {
        return DB::transaction(function () use ($actor, $nombre, $descripcion): CoordinacionNacional {
            // Bloquea la tabla para que dos registros al mismo tiempo no tomen el mismo lugar
            DB::statement('lock table coordinacion_nacional in share row exclusive mode');

            $orden = (int) DB::table('coordinacion_nacional')->max('cona_orden') + 1;

            $id = (int) DB::table('coordinacion_nacional')->insertGetId([
                'cona_nombre' => $nombre,
                'cona_descripcion' => $descripcion,
                'cona_orden' => $orden,
                'cona_activo' => true,
            ], 'cona_id_coordinacion_nacional');

            $this->bitacora($actor, 'Registro', 'COORDINACION_NACIONAL', $id);

            return CoordinacionNacional::findOrFail($id);
        });
    }

    public function actualizar(Usuario $actor, CoordinacionNacional $coordinacion, string $nombre, ?string $descripcion): void
    {
        DB::transaction(function () use ($actor, $coordinacion, $nombre, $descripcion): void {
            $actual = DB::table('coordinacion_nacional')
                ->where('cona_id_coordinacion_nacional', $coordinacion->cona_id_coordinacion_nacional)
                ->lockForUpdate()
                ->first();

            $cambios = array_filter([
                'Nombre de la coordinación' => $actual->cona_nombre !== $nombre ? [$actual->cona_nombre, $nombre] : null,
                'Descripción' => $actual->cona_descripcion !== $descripcion ? [$actual->cona_descripcion, $descripcion] : null,
            ]);

            if ($cambios === []) {
                return;
            }

            DB::table('coordinacion_nacional')
                ->where('cona_id_coordinacion_nacional', $coordinacion->cona_id_coordinacion_nacional)
                ->update(['cona_nombre' => $nombre, 'cona_descripcion' => $descripcion]);

            $idBitacora = $this->bitacora($actor, 'Modificacion', 'COORDINACION_NACIONAL', $coordinacion->cona_id_coordinacion_nacional);

            foreach ($cambios as $campo => [$anterior, $nuevo]) {
                $this->detalle($idBitacora, $coordinacion->cona_id_coordinacion_nacional, $campo, $anterior, $nuevo);
            }
        });
    }

    /**
     * Activa o desactiva. Solo cambia la marca: no toca sus puestos ni designaciones.
     */
    public function cambiarEstado(Usuario $actor, CoordinacionNacional $coordinacion, bool $activa): void
    {
        DB::transaction(function () use ($actor, $coordinacion, $activa): void {
            $actual = (bool) DB::table('coordinacion_nacional')
                ->where('cona_id_coordinacion_nacional', $coordinacion->cona_id_coordinacion_nacional)
                ->lockForUpdate()
                ->value('cona_activo');

            if ($actual === $activa) {
                return;
            }

            DB::table('coordinacion_nacional')
                ->where('cona_id_coordinacion_nacional', $coordinacion->cona_id_coordinacion_nacional)
                ->update(['cona_activo' => $activa]);

            $this->bitacora($actor, $activa ? 'Activacion' : 'Desactivacion', 'COORDINACION_NACIONAL', $coordinacion->cona_id_coordinacion_nacional);
        });
    }

    /**
     * Por qué no se puede borrar, o null si sí se puede.
     */
    public function motivoParaNoBorrar(CoordinacionNacional $coordinacion): ?string
    {
        return $this->explicar(DB::table('puesto')->where('pues_id_coordinacion_nacional', $coordinacion->cona_id_coordinacion_nacional)->count());
    }

    private function explicar(int $puestos): ?string
    {
        return match (true) {
            $puestos === 0 => null,
            $puestos === 1 => 'No se puede borrar porque tiene 1 puesto. Si ya no se usa, desactívala: su historial se conserva.',
            default => "No se puede borrar porque tiene {$puestos} puestos. Si ya no se usa, desactívala: su historial se conserva.",
        };
    }

    /**
     * Borra la coordinación si no tiene puestos. Devuelve false si no se pudo.
     */
    public function eliminar(Usuario $actor, CoordinacionNacional $coordinacion, string $motivo): bool
    {
        return DB::transaction(function () use ($actor, $coordinacion, $motivo): bool {
            $id = $coordinacion->cona_id_coordinacion_nacional;
            $actual = DB::table('coordinacion_nacional')->where('cona_id_coordinacion_nacional', $id)->lockForUpdate()->first();

            if ($actual === null || $this->motivoParaNoBorrar($coordinacion) !== null) {
                return false;
            }

            $idBitacora = $this->bitacora($actor, 'Eliminacion', 'COORDINACION_NACIONAL', $id, $motivo);
            $this->detalle($idBitacora, $id, 'Nombre de la coordinación', $actual->cona_nombre, null);
            $this->detalle($idBitacora, $id, 'Orden', (string) $actual->cona_orden, null);

            DB::table('coordinacion_nacional')->where('cona_id_coordinacion_nacional', $id)->delete();

            // Las que seguían suben un lugar (sin huecos), con la función SQL
            $restantes = DB::table('coordinacion_nacional')->orderBy('cona_orden')
                ->pluck('cona_id_coordinacion_nacional')->map(fn ($c) => (int) $c)->all();
            $this->reordenar($actor, $restantes);

            return true;
        });
    }

    /**
     * Guarda el orden nuevo con reordenar_coordinaciones.
     * Devuelve false si el orden no cambió (no se llama a la función).
     *
     * @param  array<int, int>  $ids  todas las coordinaciones, en el orden nuevo
     */
    public function reordenar(Usuario $actor, array $ids): bool
    {
        return DB::transaction(function () use ($actor, $ids): bool {
            // Lugar actual de cada coordinación: id => orden
            $lugares = DB::table('coordinacion_nacional')->lockForUpdate()
                ->pluck('cona_orden', 'cona_id_coordinacion_nacional')
                ->mapWithKeys(fn ($orden, $id) => [(int) $id => (int) $orden])->all();

            // La primera que queda en un lugar distinto es la que se registra como "movida"
            $movida = null;
            foreach ($ids as $posicion => $id) {
                if (($lugares[$id] ?? null) !== $posicion + 1) {
                    $movida = $id;
                    break;
                }
            }

            // Nadie cambia de lugar: no hay nada que guardar
            if ($ids === [] || ($movida === null && count($ids) === count($lugares))) {
                return false;
            }

            DB::select('select reordenar_coordinaciones(?, ?, ?::int[])', [
                $actor->usua_id_usuario,
                $movida ?? $ids[0],
                '{'.implode(',', array_map('intval', $ids)).'}',
            ]);

            return true;
        });
    }
}

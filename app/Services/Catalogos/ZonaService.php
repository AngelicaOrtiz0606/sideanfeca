<?php

namespace App\Services\Catalogos;

use App\Models\EntidadFederativa;
use App\Models\Usuario;
use App\Models\ZonaRegional;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Zonas regionales y la zona de cada entidad federativa.
 *
 * El esquema no tiene funciones SQL para las zonas, así que aquí se hacen los
 * cambios y, en la misma transacción, se registra la bitácora con
 * bitacora_registrar y bitacora_detalle_registrar.
 *
 * - Cada entidad siempre pertenece a una zona: se mueve de una zona a otra,
 *   nunca se queda sin zona.
 * - Una zona solo se borra si no tiene entidades, instituciones ni puestos, y
 *   con un motivo (la bitácora lo exige para las eliminaciones).
 */
class ZonaService
{
    /**
     * Zonas con cuántas entidades, instituciones y puestos tiene cada una.
     *
     * @return Collection<int, object>
     */
    public function listado(): Collection
    {
        return DB::table('zona_regional as z')
            ->select('z.zore_id_zona_regional', 'z.zore_numero', 'z.zore_nombre')
            ->selectSub(fn ($q) => $q->from('entidad_federativa')->whereColumn('enfe_id_zona_regional', 'z.zore_id_zona_regional')->selectRaw('count(*)'), 'entidades')
            ->selectSub(fn ($q) => $q->from('institucion_educativa')->whereColumn('ined_id_zona_regional', 'z.zore_id_zona_regional')->selectRaw('count(*)'), 'instituciones')
            ->orderBy('z.zore_numero')
            ->get();
    }

    /**
     * Instituciones de la zona con su razón social vigente.
     *
     * @return Collection<int, object>
     */
    public function instituciones(ZonaRegional $zona): Collection
    {
        return DB::table('institucion_educativa as i')
            ->leftJoin('institucion_nombre as n', function ($join) {
                $join->on('n.instn_id_institucion', '=', 'i.ined_id_institucion_educativa')->whereNull('n.instn_fecha_fin');
            })
            ->where('i.ined_id_zona_regional', $zona->zore_id_zona_regional)
            ->orderBy('n.instn_nombre')
            ->get(['i.ined_id_institucion_educativa as id', 'n.instn_nombre as nombre', 'i.ined_participacion as participacion', 'i.ined_activo as activa']);
    }

    /**
     * Números del 1 al 7 que todavía no usa ninguna zona.
     *
     * @return array<int, int>
     */
    public function numerosLibres(): array
    {
        $usados = DB::table('zona_regional')->pluck('zore_numero')->map(fn ($n) => (int) $n)->all();

        return array_values(array_diff(range(1, 7), $usados));
    }

    /**
     * Por qué no se puede borrar la zona, o null si sí se puede.
     */
    public function motivoParaNoBorrar(ZonaRegional $zona): ?string
    {
        $id = $zona->zore_id_zona_regional;
        $partes = array_filter([
            $this->contar('entidad_federativa', 'enfe_id_zona_regional', $id, 'entidad federativa', 'entidades federativas'),
            $this->contar('institucion_educativa', 'ined_id_zona_regional', $id, 'institución', 'instituciones'),
            $this->contar('puesto', 'pues_id_zona_regional', $id, 'puesto', 'puestos'),
        ]);

        if ($partes === []) {
            return null;
        }

        return 'No se puede borrar porque tiene '.$this->unir($partes).'.';
    }

    /**
     * @param  array<int, int>  $entidades  entidades que pasan a esta zona
     */
    public function registrar(Usuario $actor, int $numero, string $nombre, array $entidades): ZonaRegional
    {
        return DB::transaction(function () use ($actor, $numero, $nombre, $entidades): ZonaRegional {
            $id = (int) DB::table('zona_regional')->insertGetId([
                'zore_numero' => $numero,
                'zore_nombre' => $nombre,
            ], 'zore_id_zona_regional');

            $this->bitacora($actor, 'Registro', 'ZONA_REGIONAL', $id);

            $zona = ZonaRegional::findOrFail($id);
            $this->moverEntidades($actor, $zona, $entidades);

            return $zona;
        });
    }

    /**
     * @param  array<int, int>  $entidades  entidades que pasan a esta zona
     * @return array<int, string> cada entidad movida, con la zona de la que salió
     */
    public function actualizar(Usuario $actor, ZonaRegional $zona, int $numero, string $nombre, array $entidades): array
    {
        return DB::transaction(function () use ($actor, $zona, $numero, $nombre, $entidades): array {
            $actual = DB::table('zona_regional')
                ->where('zore_id_zona_regional', $zona->zore_id_zona_regional)
                ->lockForUpdate()
                ->first();

            $cambios = array_filter([
                'Número' => (int) $actual->zore_numero !== $numero ? [(string) $actual->zore_numero, (string) $numero] : null,
                'Nombre de la zona' => $actual->zore_nombre !== $nombre ? [$actual->zore_nombre, $nombre] : null,
            ]);

            if ($cambios !== []) {
                DB::table('zona_regional')
                    ->where('zore_id_zona_regional', $zona->zore_id_zona_regional)
                    ->update(['zore_numero' => $numero, 'zore_nombre' => $nombre]);

                $idBitacora = $this->bitacora($actor, 'Modificacion', 'ZONA_REGIONAL', $zona->zore_id_zona_regional);

                foreach ($cambios as $campo => [$anterior, $nuevo]) {
                    $this->detalle($idBitacora, $zona->zore_id_zona_regional, $campo, $anterior, $nuevo);
                }
            }

            return $this->moverEntidades($actor, $zona->refresh(), $entidades);
        });
    }

    public function eliminar(Usuario $actor, ZonaRegional $zona, string $motivo): bool
    {
        return DB::transaction(function () use ($actor, $zona, $motivo): bool {
            DB::table('zona_regional')
                ->where('zore_id_zona_regional', $zona->zore_id_zona_regional)
                ->lockForUpdate()
                ->first();

            if ($this->motivoParaNoBorrar($zona) !== null) {
                return false;
            }

            $idBitacora = $this->bitacora($actor, 'Eliminacion', 'ZONA_REGIONAL', $zona->zore_id_zona_regional, $motivo);
            $this->detalle($idBitacora, $zona->zore_id_zona_regional, 'Número', (string) $zona->zore_numero, null);
            $this->detalle($idBitacora, $zona->zore_id_zona_regional, 'Nombre de la zona', $zona->zore_nombre, null);

            DB::table('zona_regional')->where('zore_id_zona_regional', $zona->zore_id_zona_regional)->delete();

            return true;
        });
    }

    /**
     * Pasa a la zona las entidades indicadas que hoy están en otra.
     * Cada entidad movida queda en la bitácora con su zona anterior y la nueva.
     *
     * @param  array<int, int>  $entidades
     * @return array<int, string> "Sonora (antes en la Zona 2 · Norte)"
     */
    private function moverEntidades(Usuario $actor, ZonaRegional $zona, array $entidades): array
    {
        $movidas = [];

        $porMover = EntidadFederativa::query()
            ->with('zona')
            ->whereIn('enfe_id_entidad_federativa', $entidades)
            ->where('enfe_id_zona_regional', '!=', $zona->zore_id_zona_regional)
            ->lockForUpdate()
            ->get();

        foreach ($porMover as $entidad) {
            $anterior = $entidad->zona?->etiqueta();

            DB::table('entidad_federativa')
                ->where('enfe_id_entidad_federativa', $entidad->enfe_id_entidad_federativa)
                ->update(['enfe_id_zona_regional' => $zona->zore_id_zona_regional]);

            $idBitacora = $this->bitacora($actor, 'Modificacion', 'ENTIDAD_FEDERATIVA', $entidad->enfe_id_entidad_federativa);
            $this->detalle($idBitacora, $entidad->enfe_id_entidad_federativa, 'Zona regional', $anterior, $zona->etiqueta());

            $movidas[] = "{$entidad->enfe_nombre} (antes en la {$anterior})";
        }

        return $movidas;
    }

    private function bitacora(Usuario $actor, string $accion, string $tabla, int $referencia, ?string $motivo = null): int
    {
        return (int) DB::selectOne(
            'select bitacora_registrar(?, ?, ?, ?, ?) as id',
            [$actor->usua_id_usuario, $accion, $tabla, $referencia, $motivo],
        )->id;
    }

    private function detalle(int $idBitacora, int $referencia, string $campo, ?string $anterior, ?string $nuevo): void
    {
        DB::select(
            'select bitacora_detalle_registrar(?, ?, ?, ?, ?)',
            [$idBitacora, $referencia, $campo, $anterior, $nuevo],
        );
    }

    private function contar(string $tabla, string $columna, int $id, string $singular, string $plural): ?string
    {
        $total = DB::table($tabla)->where($columna, $id)->count();

        return match (true) {
            $total === 0 => null,
            $total === 1 => "1 {$singular}",
            default => "{$total} {$plural}",
        };
    }

    /**
     * @param  array<int, string>  $partes
     */
    private function unir(array $partes): string
    {
        $ultima = array_pop($partes);

        return $partes === [] ? $ultima : implode(', ', $partes).' y '.$ultima;
    }
}

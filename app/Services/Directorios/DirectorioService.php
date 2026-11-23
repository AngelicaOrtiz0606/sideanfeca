<?php

namespace App\Services\Directorios;

use App\Models\Usuario;
use App\Services\Catalogos\BitacoraCatalogo;
use App\Services\Puestos\PuestoService;
use App\Support\FuncionSql;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Directorios de puestos (M7): qué puestos aparecen en cada uno y en qué
 * orden (PUESTO_DIRECTORIO). Son el Consejo Nacional Directivo, los
 * Consejos Regionales y las Coordinaciones Nacionales; el de Instituciones
 * no es una lista de puestos (se arma con las instituciones y sus titulares).
 *
 * - Agregar: el puesto va al final (orden máximo + 1).
 * - Quitar: los que seguían suben un lugar (sin huecos).
 * - Reordenar: reordenar_puestos_directorio de la base, con su bitácora.
 * Los puestos institucionales no van en directorios (lo revisa la base).
 */
class DirectorioService
{
    use BitacoraCatalogo;

    /** El directorio que no es una lista de puestos. */
    public const INSTITUCIONES = 'Instituciones';

    public function __construct(private readonly PuestoService $puestos) {}

    /**
     * Los directorios de puestos, en su orden.
     *
     * @return Collection<int, object>
     */
    public function tipos(): Collection
    {
        return DB::table('tipo_directorio')
            ->where('tidi_nombre', '!=', self::INSTITUCIONES)
            ->orderBy('tidi_id_tipo_directorio')
            ->get(['tidi_id_tipo_directorio as id', 'tidi_nombre as nombre', 'tidi_descripcion as descripcion']);
    }

    /**
     * Los puestos del directorio, en su orden, con quién lo ocupa hoy (o "Vacante").
     * Una persona oculta no se muestra: su puesto se ve vacante.
     *
     * @return Collection<int, object>
     */
    public function puestosDe(int $tipo): Collection
    {
        return DB::table('puesto_directorio')
            ->where('pudi_id_tipo_directorio', $tipo)
            ->orderBy('pudi_orden')
            ->get(['pudi_id_puesto as id', 'pudi_orden as orden'])
            ->map(function (object $fila): object {
                $puesto = $this->puestos->buscar((int) $fila->id);
                $ocupante = $this->ocupanteHoy((int) $fila->id);

                return (object) [
                    'id' => (int) $fila->id,
                    'orden' => (int) $fila->orden,
                    'nombre' => $ocupante ? $this->puestos->nombre($puesto, $ocupante->genero) : $this->puestos->nombre($puesto),
                    'ocupante' => $ocupante?->nombre,
                    'activo' => (bool) $puesto->activo,
                ];
            });
    }

    /**
     * Puestos que se pueden agregar al directorio: activos, no institucionales,
     * que aún no están en él y que corresponden a ese directorio
     * (nacionales al Consejo Nacional; regionales a los Consejos Regionales;
     * con coordinación a las Coordinaciones Nacionales).
     *
     * @return Collection<int, object>
     */
    public function disponibles(int $tipo): Collection
    {
        $nombre = DB::table('tipo_directorio')->where('tidi_id_tipo_directorio', $tipo)->value('tidi_nombre');

        return DB::table('puesto as p')
            ->join('cargo as c', 'c.carg_id_cargo', '=', 'p.pues_id_cargo')
            ->where('p.pues_activo', true)
            ->where('c.carg_nivel', '!=', 'Institucional')
            ->whereNotExists(fn ($q) => $q->from('puesto_directorio')->whereColumn('pudi_id_puesto', 'p.pues_id_puesto')->where('pudi_id_tipo_directorio', $tipo))
            ->when($nombre === 'Consejo Nacional Directivo', fn ($q) => $q->where('c.carg_nivel', 'Nacional'))
            ->when($nombre === 'Consejos Regionales', fn ($q) => $q->where('c.carg_nivel', 'Regional'))
            ->when($nombre === 'Coordinaciones Nacionales', fn ($q) => $q->whereNotNull('p.pues_id_coordinacion_nacional'))
            ->pluck('p.pues_id_puesto')
            ->map(fn ($id) => (object) ['id' => (int) $id, 'nombre' => $this->puestos->nombre($this->puestos->buscar((int) $id))])
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    public function agregar(Usuario $actor, int $tipo, int $puesto): ?string
    {
        try {
            DB::transaction(function () use ($actor, $tipo, $puesto): void {
                // Se bloquean los lugares del directorio para que dos personas no tomen el mismo
                $ordenes = DB::table('puesto_directorio')->where('pudi_id_tipo_directorio', $tipo)->lockForUpdate()->pluck('pudi_orden');
                DB::table('puesto_directorio')->insert(['pudi_id_puesto' => $puesto, 'pudi_id_tipo_directorio' => $tipo, 'pudi_orden' => (int) $ordenes->max() + 1]);

                $idBitacora = $this->bitacora($actor, 'Modificacion', 'PUESTO', $puesto);
                $this->detalle($idBitacora, $puesto, 'Agregado al directorio', null, $this->nombreTipo($tipo));
            });
        } catch (QueryException $error) {
            // Por ejemplo, un puesto institucional (lo rechaza la base)
            return FuncionSql::mensaje($error);
        }

        return null;
    }

    public function quitar(Usuario $actor, int $tipo, int $puesto): void
    {
        DB::transaction(function () use ($actor, $tipo, $puesto): void {
            $orden = DB::table('puesto_directorio')->where('pudi_id_tipo_directorio', $tipo)->where('pudi_id_puesto', $puesto)->lockForUpdate()->value('pudi_orden');
            if ($orden === null) {
                return;
            }
            DB::table('puesto_directorio')->where('pudi_id_tipo_directorio', $tipo)->where('pudi_id_puesto', $puesto)->delete();
            // Los que seguían suben un lugar (el orden único es diferible: se revisa al terminar)
            DB::table('puesto_directorio')->where('pudi_id_tipo_directorio', $tipo)->where('pudi_orden', '>', $orden)->decrement('pudi_orden');

            $idBitacora = $this->bitacora($actor, 'Modificacion', 'PUESTO', $puesto);
            $this->detalle($idBitacora, $puesto, 'Quitado del directorio', null, $this->nombreTipo($tipo));
        });
    }

    /**
     * Guarda el nuevo orden (la lista completa) con la función de la base.
     * Devuelve su error en palabras, o null.
     *
     * @param  array<int, int>  $puestos
     */
    public function reordenar(Usuario $actor, int $tipo, int $movido, array $puestos): ?string
    {
        try {
            // En su propia transacción: si la base rechaza la lista, se deshace solo esto
            DB::transaction(fn () => DB::selectOne('select reordenar_puestos_directorio(?, ?, ?, ?::int4[]) as resultado',
                [$actor->usua_id_usuario, $tipo, $movido, '{'.implode(',', array_map('intval', $puestos)).'}']));
        } catch (QueryException $error) {
            return FuncionSql::mensaje($error);
        }

        return null;
    }

    public function nombreTipo(int $tipo): ?string
    {
        return DB::table('tipo_directorio')->where('tidi_id_tipo_directorio', $tipo)->value('tidi_nombre');
    }

    /**
     * Quién ocupa hoy el puesto (designación vigente de una persona visible).
     */
    private function ocupanteHoy(int $puesto): ?object
    {
        $persona = DB::table('designacion as d')
            ->join('persona as p', 'p.pers_id_persona', '=', 'd.desi_id_persona')
            ->where('d.desi_id_puesto', $puesto)
            ->where('p.pers_oculta_admin', false)
            ->where('d.desi_fecha_inicio', '<=', DB::raw('current_date'))
            ->where(fn ($q) => $q->whereNull('d.desi_fecha_fin')->orWhere('d.desi_fecha_fin', '>=', DB::raw('current_date')))
            ->first(['p.pers_nombre', 'p.pers_primer_apellido', 'p.pers_segundo_apellido', 'p.pers_genero']);

        return $persona === null ? null : (object) [
            'nombre' => trim("{$persona->pers_nombre} {$persona->pers_primer_apellido} ".($persona->pers_segundo_apellido ?? '')),
            'genero' => $persona->pers_genero,
        ];
    }
}

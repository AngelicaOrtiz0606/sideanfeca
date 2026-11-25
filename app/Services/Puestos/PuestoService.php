<?php

namespace App\Services\Puestos;

use App\Models\Usuario;
use App\Services\Catalogos\BitacoraCatalogo;
use App\Support\TextoCatalogo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Puestos: un cargo en un ámbito concreto (tabla PUESTO).
 * - Institucional: lleva institución; puede ser el titular de la institución.
 * - Regional: lleva zona (obligatoria) y coordinación (opcional).
 * - Nacional: sin zona ni institución; coordinación u organización opcional
 *   (nunca las dos). Quien ocupa el puesto de una organización pertenece a ella.
 *
 * - No se repite la combinación cargo + zona + coordinación + institución.
 * - Una institución tiene a lo más un puesto titular (el que aparece en el
 *   directorio de Instituciones).
 * - Con designaciones, el cargo y el ámbito ya no cambian (sería reescribir
 *   la historia): si cambió, se desactiva y se crea otro.
 * - Solo se borra si nunca tuvo designaciones.
 * El trigger validar_puesto de la base revisa también las reglas de ámbito.
 */
class PuestoService
{
    use BitacoraCatalogo;

    public const NIVELES = ['Nacional', 'Regional', 'Institucional'];

    /** Cómo se puede ordenar la lista (valor en la dirección => texto). */
    public const ORDENES = [
        'puesto' => 'Puesto',
        'coordinacion' => 'Coordinación',
        'zona' => 'Zona',
        'ocupante' => 'Ocupado por',
    ];

    /**
     * Lista con su ámbito, quién lo ocupa hoy y cuántas designaciones tiene.
     * Las personas ocultas no se muestran: su puesto aparece como vacante.
     *
     * @return Collection<int, object>
     */
    public function listado(): Collection
    {
        return $this->consulta()
            ->addSelect([
                'ocupante' => DB::table('designacion as d')
                    ->join('persona as per', 'per.pers_id_persona', '=', 'd.desi_id_persona')
                    ->whereColumn('d.desi_id_puesto', 'p.pues_id_puesto')
                    ->where('per.pers_oculta_admin', false)
                    ->where('d.desi_fecha_inicio', '<=', DB::raw('current_date'))
                    ->where(fn (Builder $q) => $q->whereNull('d.desi_fecha_fin')->orWhere('d.desi_fecha_fin', '>=', DB::raw('current_date')))
                    ->selectRaw("concat_ws(' ', per.pers_nombre, per.pers_primer_apellido, per.pers_segundo_apellido)")
                    ->limit(1),
                'designaciones' => DB::table('designacion')->whereColumn('desi_id_puesto', 'p.pues_id_puesto')->selectRaw('count(*)'),
            ])
            ->orderByRaw("array_position(array['Nacional','Regional','Institucional']::varchar[], c.carg_nivel)")
            ->orderBy('z.zore_numero')
            ->orderBy('c.carg_nombre_m')
            ->orderBy('cn.cona_orden')
            ->orderBy('n.instn_nombre')
            ->get()
            ->each(function (object $puesto): void {
                $puesto->motivo_para_no_borrar = $this->explicar((int) $puesto->designaciones);
                $puesto->ambito = $this->ambito($puesto);
            });
    }

    /**
     * La lista en otro orden. "puesto" es el del listado (nivel, zona, cargo,
     * coordinación); los demás dejan al final los puestos sin ese dato.
     *
     * @param  Collection<int, object>  $puestos
     * @return Collection<int, object>
     */
    public function ordenar(Collection $puestos, string $orden): Collection
    {
        $nivel = fn (object $p) => array_search($p->nivel, self::NIVELES, true);
        $texto = fn (?string $t) => mb_strtolower(TextoCatalogo::clave($t));

        $criterios = match ($orden) {
            'coordinacion' => [
                fn ($a, $b) => [$a->coordinacion === null, $a->coordinacion_orden, $nivel($a), $a->zona_numero, $texto($a->cargo)]
                    <=> [$b->coordinacion === null, $b->coordinacion_orden, $nivel($b), $b->zona_numero, $texto($b->cargo)],
            ],
            'zona' => [
                fn ($a, $b) => [$a->zona_numero === null, $a->zona_numero, $a->coordinacion_orden ?? 0, $texto($a->cargo), $texto($a->institucion)]
                    <=> [$b->zona_numero === null, $b->zona_numero, $b->coordinacion_orden ?? 0, $texto($b->cargo), $texto($b->institucion)],
            ],
            'ocupante' => [
                fn ($a, $b) => [$a->ocupante === null, $texto($a->ocupante), $nivel($a)] <=> [$b->ocupante === null, $texto($b->ocupante), $nivel($b)],
            ],
            default => [],
        };

        return $criterios === [] ? $puestos->values() : $puestos->sort($criterios[0])->values();
    }

    public function buscar(int $id): ?object
    {
        $puesto = $this->consulta()->where('p.pues_id_puesto', $id)->first();
        if ($puesto !== null) {
            $puesto->designaciones = DB::table('designacion')->where('desi_id_puesto', $id)->count();
            $puesto->ambito = $this->ambito($puesto);
        }

        return $puesto;
    }

    /**
     * Quiénes han ocupado el puesto, de la más reciente a la más antigua.
     * Una persona oculta se lista sin su nombre.
     *
     * @return Collection<int, object> {persona, nombre, oculta, desde, hasta}
     */
    public function historial(int $id): Collection
    {
        return DB::table('designacion as d')
            ->join('persona as per', 'per.pers_id_persona', '=', 'd.desi_id_persona')
            ->where('d.desi_id_puesto', $id)
            ->orderByDesc('d.desi_fecha_inicio')
            ->orderByDesc('d.desi_id_designacion')
            ->get([
                'per.pers_id_persona as persona', 'per.pers_oculta_admin as oculta',
                DB::raw("concat_ws(' ', per.pers_nombre, per.pers_primer_apellido, per.pers_segundo_apellido) as nombre"),
                'd.desi_fecha_inicio as desde', 'd.desi_fecha_fin as hasta',
            ]);
    }

    /**
     * Los directorios en los que aparece el puesto.
     *
     * @return array<int, string>
     */
    public function directoriosDe(int $id): array
    {
        return DB::table('puesto_directorio as pd')
            ->join('tipo_directorio as t', 't.tidi_id_tipo_directorio', '=', 'pd.pudi_id_tipo_directorio')
            ->where('pd.pudi_id_puesto', $id)
            ->orderBy('t.tidi_id_tipo_directorio')
            ->pluck('t.tidi_nombre')
            ->all();
    }

    /**
     * Cargos activos para elegir (con su nivel), más el del puesto que se edita.
     *
     * @return Collection<int, object>
     */
    public function cargos(): Collection
    {
        return DB::table('cargo')
            ->select('carg_id_cargo as id', 'carg_nombre_m as nombre', 'carg_nombre_f as nombre_f', 'carg_nivel as nivel', 'carg_activo as activo')
            ->orderByRaw("array_position(array['Nacional','Regional','Institucional']::varchar[], carg_nivel)")
            ->orderBy('carg_nombre_m')
            ->get();
    }

    /**
     * Coordinaciones para elegir, en su orden (las inactivas no se ofrecen para puestos nuevos).
     *
     * @return Collection<int, object>
     */
    public function coordinaciones(): Collection
    {
        return DB::table('coordinacion_nacional')
            ->orderBy('cona_orden')
            ->get(['cona_id_coordinacion_nacional as id', 'cona_nombre as nombre', 'cona_activo as activo']);
    }

    /**
     * Organizaciones para un puesto nacional de una organización (las
     * inactivas no se ofrecen para puestos nuevos, pero se conservan al editar).
     *
     * @return Collection<int, object>
     */
    public function organizaciones(): Collection
    {
        return DB::table('organizacion')->orderBy('orga_nombre')
            ->get(['orga_id_organizacion as id', 'orga_nombre as nombre', 'orga_activo as activo']);
    }

    /**
     * @return Collection<int, object>
     */
    public function zonas(): Collection
    {
        return DB::table('zona_regional')->orderBy('zore_numero')->get(['zore_id_zona_regional as id', 'zore_numero as numero', 'zore_nombre as nombre']);
    }

    /**
     * Instituciones activas (para un puesto institucional o la pertenencia de una persona), con su tipo,
     * universidad, zona y entidad (para distinguir nombres iguales).
     *
     * @return Collection<int, object>
     */
    public function instituciones(?int $incluir = null): Collection
    {
        return DB::table('institucion_educativa as i')
            ->join('tipo_institucion_educativa as t', 't.tiie_id_tipo_institucion', '=', 'i.ined_id_tipo_institucion')
            ->join('institucion_nombre as n', fn ($j) => $j->on('n.instn_id_institucion', '=', 'i.ined_id_institucion_educativa')->whereNull('n.instn_fecha_fin'))
            ->leftJoin('institucion_nombre as np', fn ($j) => $j->on('np.instn_id_institucion', '=', 'i.ined_id_institucion_padre')->whereNull('np.instn_fecha_fin'))
            ->leftJoin('zona_regional as z', 'z.zore_id_zona_regional', '=', 'i.ined_id_zona_regional')
            ->leftJoin('domicilio as d', 'd.domi_id_domicilio', '=', 'i.ined_id_domicilio')
            ->leftJoin('colonia as c', 'c.colo_id_colonia', '=', 'd.domi_id_colonia')
            ->leftJoin('alcaldia_municipio as m', 'm.almu_id_alcaldia_municipio', '=', 'c.colo_id_alcaldia_municipio')
            ->leftJoin('entidad_federativa as e', 'e.enfe_id_entidad_federativa', '=', 'm.almu_id_entidad_federativa')
            // Activas, más la que ya tiene el registro que se edita (aunque esté desactivada)
            ->where(fn (Builder $q) => $q->where('i.ined_activo', true)->when($incluir, fn (Builder $w, int $id) => $w->orWhere('i.ined_id_institucion_educativa', $id)))
            ->orderBy('n.instn_nombre')
            ->get([
                'i.ined_id_institucion_educativa as id', 'n.instn_nombre as nombre', 't.tiie_nombre as tipo', 'np.instn_nombre as universidad',
                'z.zore_numero as zona_numero', 'z.zore_nombre as zona_nombre', 'e.enfe_nombre as entidad', 'i.ined_participacion as participacion',
            ]);
    }

    /**
     * @param  array{cargo: int, zona: ?int, coordinacion: ?int, institucion: ?int, titular: bool}  $datos
     */
    public function registrar(Usuario $actor, array $datos): int
    {
        return DB::transaction(function () use ($actor, $datos): int {
            // Pasa a ser el titular: el anterior titular de la institución deja de serlo
            if ($datos['titular']) {
                $this->quitarTitularidad($actor, (int) $datos['institucion'], null);
            }

            $id = (int) DB::table('puesto')->insertGetId([
                'pues_id_cargo' => $datos['cargo'],
                'pues_id_zona_regional' => $datos['zona'],
                'pues_id_coordinacion_nacional' => $datos['coordinacion'],
                'pues_id_institucion_educativa' => $datos['institucion'],
                'pues_id_organizacion' => $datos['organizacion'] ?? null,
                'pues_titularidad' => $datos['titular'],
                'pues_activo' => true,
            ], 'pues_id_puesto');

            $this->bitacora($actor, 'Registro', 'PUESTO', $id);

            return $id;
        });
    }

    /**
     * @param  array{cargo: int, zona: ?int, coordinacion: ?int, institucion: ?int, organizacion?: ?int, titular: bool}  $datos
     */
    public function actualizar(Usuario $actor, int $id, array $datos): void
    {
        DB::transaction(function () use ($actor, $id, $datos): void {
            $actual = DB::table('puesto')->where('pues_id_puesto', $id)->lockForUpdate()->first();

            $cambios = array_filter([
                'Cargo' => [$this->nombreCargo($actual->pues_id_cargo), $this->nombreCargo($datos['cargo'])],
                'Zona regional' => [$this->nombreZona($actual->pues_id_zona_regional), $this->nombreZona($datos['zona'])],
                'Coordinación nacional' => [$this->nombreCoordinacion($actual->pues_id_coordinacion_nacional), $this->nombreCoordinacion($datos['coordinacion'])],
                'Institución' => [$this->nombreInstitucion($actual->pues_id_institucion_educativa), $this->nombreInstitucion($datos['institucion'])],
                'Organización' => [$this->nombreOrganizacion($actual->pues_id_organizacion), $this->nombreOrganizacion($datos['organizacion'] ?? null)],
                'Titular de la institución' => [$actual->pues_titularidad ? 'Sí' : 'No', $datos['titular'] ? 'Sí' : 'No'],
            ], fn (array $par) => $par[0] !== $par[1]);

            if ($cambios === []) {
                return;
            }

            // Pasa a ser el titular: el anterior titular de la institución deja de serlo
            if ($datos['titular'] && ! $actual->pues_titularidad) {
                $this->quitarTitularidad($actor, (int) $datos['institucion'], $id);
            }

            DB::table('puesto')->where('pues_id_puesto', $id)->update([
                'pues_id_cargo' => $datos['cargo'],
                'pues_id_zona_regional' => $datos['zona'],
                'pues_id_coordinacion_nacional' => $datos['coordinacion'],
                'pues_id_institucion_educativa' => $datos['institucion'],
                'pues_id_organizacion' => $datos['organizacion'] ?? null,
                'pues_titularidad' => $datos['titular'],
            ]);

            $idBitacora = $this->bitacora($actor, 'Modificacion', 'PUESTO', $id);
            foreach ($cambios as $campo => [$anterior, $nuevo]) {
                $this->detalle($idBitacora, $id, $campo, $anterior, $nuevo);
            }
        });
    }

    /**
     * El puesto que era titular de la institución deja de serlo (quien lo
     * ocupa conserva su cargo); queda en la bitácora de ese puesto.
     */
    private function quitarTitularidad(Usuario $actor, int $institucion, ?int $excepto): void
    {
        $anteriores = DB::table('puesto')->where('pues_id_institucion_educativa', $institucion)->where('pues_titularidad', true)
            ->when($excepto, fn (Builder $q, int $id) => $q->where('pues_id_puesto', '!=', $id))->lockForUpdate()->pluck('pues_id_puesto');

        foreach ($anteriores as $anterior) {
            DB::table('puesto')->where('pues_id_puesto', $anterior)->update(['pues_titularidad' => false]);
            $this->detalle($this->bitacora($actor, 'Modificacion', 'PUESTO', (int) $anterior), (int) $anterior, 'Titular de la institución', 'Sí', 'No');
        }
    }

    public function cambiarEstado(Usuario $actor, int $id, bool $activo): void
    {
        DB::transaction(function () use ($actor, $id, $activo): void {
            $actual = (bool) DB::table('puesto')->where('pues_id_puesto', $id)->lockForUpdate()->value('pues_activo');

            if ($actual === $activo) {
                return;
            }

            DB::table('puesto')->where('pues_id_puesto', $id)->update(['pues_activo' => $activo]);
            $this->bitacora($actor, $activo ? 'Activacion' : 'Desactivacion', 'PUESTO', $id);
        });
    }

    public function motivoParaNoBorrar(int $id): ?string
    {
        return $this->explicar(DB::table('designacion')->where('desi_id_puesto', $id)->count());
    }

    /**
     * Por qué el cargo y el ámbito ya no se pueden cambiar, o null.
     */
    public function motivoParaNoCambiarAmbito(int $designaciones): ?string
    {
        return $designaciones === 0 ? null
            : 'Ya tiene designaciones, así que el cargo y el ámbito no cambian (sería cambiar su historia). Si el puesto cambió, desactívalo y registra otro.';
    }

    /**
     * Borra un puesto que nunca tuvo designaciones (se quita también de los directorios).
     */
    public function eliminar(Usuario $actor, int $id, string $motivo): bool
    {
        return DB::transaction(function () use ($actor, $id, $motivo): bool {
            $actual = DB::table('puesto')->where('pues_id_puesto', $id)->lockForUpdate()->first();

            if ($actual === null || $this->motivoParaNoBorrar($id) !== null) {
                return false;
            }

            $idBitacora = $this->bitacora($actor, 'Eliminacion', 'PUESTO', $id, $motivo);
            $this->detalle($idBitacora, $id, 'Puesto', $this->ambito($this->consulta()->where('p.pues_id_puesto', $id)->first(), true), null);

            DB::table('puesto_directorio')->where('pudi_id_puesto', $id)->delete();
            DB::table('puesto')->where('pues_id_puesto', $id)->delete();

            return true;
        });
    }

    /**
     * Ámbito en palabras: la institución, la zona (y coordinación) o "Nacional".
     */
    public function ambito(object $puesto, bool $conCargo = false): string
    {
        $organizacion = $puesto->organizacion ?? null;
        $partes = match ($puesto->nivel) {
            'Institucional' => [$puesto->institucion],
            'Regional' => ["Zona {$puesto->zona_numero} · {$puesto->zona_nombre}", $puesto->coordinacion],
            default => [$puesto->coordinacion ?? $organizacion ?? 'Nacional'],
        };

        // "Presidente del Consejo de … (CACECA)" ya dice su organización: no se repite
        if ($conCargo && $organizacion !== null && str_contains(TextoCatalogo::clave($puesto->cargo), TextoCatalogo::clave($organizacion))) {
            return $puesto->cargo;
        }

        return implode(' · ', array_filter([$conCargo ? $puesto->cargo : null, ...$partes]));
    }

    /**
     * El puesto dicho completo, para la bitácora y las fichas: "Presidenta · Nacional",
     * "Coordinador Regional · Zona 2 · Noreste". Con género, el cargo va en ese género.
     */
    public function nombre(object $puesto, ?string $genero = null): string
    {
        $conGenero = clone $puesto;
        if ($genero === 'F' && filled($puesto->cargo_f ?? null)) {
            $conGenero->cargo = $puesto->cargo_f;
        }

        return $this->ambito($conGenero, true);
    }

    private function consulta(): Builder
    {
        return DB::table('puesto as p')
            ->join('cargo as c', 'c.carg_id_cargo', '=', 'p.pues_id_cargo')
            ->leftJoin('zona_regional as z', 'z.zore_id_zona_regional', '=', 'p.pues_id_zona_regional')
            ->leftJoin('coordinacion_nacional as cn', 'cn.cona_id_coordinacion_nacional', '=', 'p.pues_id_coordinacion_nacional')
            ->leftJoin('institucion_nombre as n', fn ($j) => $j->on('n.instn_id_institucion', '=', 'p.pues_id_institucion_educativa')->whereNull('n.instn_fecha_fin'))
            ->leftJoin('organizacion as o', 'o.orga_id_organizacion', '=', 'p.pues_id_organizacion')
            ->select([
                'p.pues_id_puesto as id',
                'p.pues_id_cargo as cargo_id',
                'c.carg_nombre_m as cargo',
                'c.carg_nombre_f as cargo_f',
                'c.carg_nivel as nivel',
                'p.pues_id_zona_regional as zona_id',
                'z.zore_numero as zona_numero',
                'z.zore_nombre as zona_nombre',
                'p.pues_id_coordinacion_nacional as coordinacion_id',
                'cn.cona_nombre as coordinacion',
                'cn.cona_orden as coordinacion_orden',
                'p.pues_id_institucion_educativa as institucion_id',
                'n.instn_nombre as institucion',
                'p.pues_id_organizacion as organizacion_id',
                'o.orga_nombre as organizacion',
                'p.pues_titularidad as titular',
                'p.pues_activo as activo',
            ]);
    }

    private function explicar(int $designaciones): ?string
    {
        return match (true) {
            $designaciones === 0 => null,
            $designaciones === 1 => 'No se puede borrar porque ya tuvo 1 designación. Si ya no se usa, desactívalo: su historia se conserva.',
            default => "No se puede borrar porque ya tuvo {$designaciones} designaciones. Si ya no se usa, desactívalo: su historia se conserva.",
        };
    }

    private function nombreCargo(?int $id): ?string
    {
        return $id === null ? null : DB::table('cargo')->where('carg_id_cargo', $id)->value('carg_nombre_m');
    }

    private function nombreZona(?int $id): ?string
    {
        $zona = $id === null ? null : DB::table('zona_regional')->where('zore_id_zona_regional', $id)->first();

        return $zona === null ? null : "Zona {$zona->zore_numero} · {$zona->zore_nombre}";
    }

    private function nombreCoordinacion(?int $id): ?string
    {
        return $id === null ? null : DB::table('coordinacion_nacional')->where('cona_id_coordinacion_nacional', $id)->value('cona_nombre');
    }

    private function nombreOrganizacion(?int $id): ?string
    {
        return $id === null ? null : DB::table('organizacion')->where('orga_id_organizacion', $id)->value('orga_nombre');
    }

    private function nombreInstitucion(?int $id): ?string
    {
        return $id === null ? null : DB::table('institucion_nombre')->where('instn_id_institucion', $id)->whereNull('instn_fecha_fin')->value('instn_nombre');
    }
}

<?php

namespace App\Services\Directorios;

use App\Services\Puestos\PuestoService;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultar directorios (M10, en la administración): arma cada directorio
 * tal como estaba en un año.
 *
 * - Directorios de puestos (Consejo Nacional Directivo, Consejos Regionales,
 *   Coordinaciones Nacionales): sus puestos en el orden guardado; en cada uno,
 *   quien lo ocupó al final de ese año (o hasta hoy, si es el año en curso).
 *   Si lo ocupó otra persona antes en ese año, se muestra la más reciente.
 * - Instituciones: las que ese año fueron afiliadas u observadoras, con su
 *   titular (el puesto marcado como titular de la institución).
 *
 * Reglas del sitio público que se respetan aquí y en el CSV:
 * - Una persona oculta no aparece: su puesto se ve como "Vacante".
 * - Solo se muestran los contactos marcados como visibles.
 */
class ConsultaDirectorioService
{
    public const INSTITUCIONES = 'Instituciones';

    /** Participaciones que forman el directorio de Instituciones (la matriz no es miembro). */
    public const PARTICIPACIONES = ['Afiliada', 'Observadora'];

    public function __construct(private readonly PuestoService $puestos) {}

    /**
     * Los cuatro directorios, en su orden.
     *
     * @return Collection<int, object>
     */
    public function tipos(): Collection
    {
        return DB::table('tipo_directorio')->orderBy('tidi_id_tipo_directorio')
            ->get(['tidi_id_tipo_directorio as id', 'tidi_nombre as nombre'])
            ->map(fn (object $t) => (object) ['id' => (int) $t->id, 'nombre' => $t->nombre]);
    }

    /**
     * Años que se pueden consultar: del primero con alguna designación o
     * participación al actual (del más reciente al más antiguo).
     *
     * @return array<int, int>
     */
    public function anios(): array
    {
        $actual = (int) now()->format('Y');
        $primero = collect([
            DB::table('designacion')->min('desi_fecha_inicio'),
            DB::table('institucion_participacion')->min('inpa_fecha_inicio'),
        ])->filter()->map(fn ($fecha) => (int) substr((string) $fecha, 0, 4))->min() ?? $actual;

        return range($actual, min($primero, $actual));
    }

    /**
     * Los puestos del directorio con quien los ocupó ese año.
     *
     * @return Collection<int, object> {puesto, nombre, grado, pertenencia, telefonos[], correos[], vacante}
     */
    public function puestos(int $tipo, int $anio, ?int $zona = null, ?int $coordinacion = null): Collection
    {
        [$inicio, $corte] = $this->periodo($anio);

        $filas = DB::table('puesto_directorio as pd')
            ->join('puesto as p', 'p.pues_id_puesto', '=', 'pd.pudi_id_puesto')
            ->join('cargo as c', 'c.carg_id_cargo', '=', 'p.pues_id_cargo')
            ->leftJoin('zona_regional as z', 'z.zore_id_zona_regional', '=', 'p.pues_id_zona_regional')
            ->leftJoin('coordinacion_nacional as cn', 'cn.cona_id_coordinacion_nacional', '=', 'p.pues_id_coordinacion_nacional')
            ->leftJoin('organizacion as org', 'org.orga_id_organizacion', '=', 'p.pues_id_organizacion')
            ->leftJoinLateral($this->ultimaDesignacion('p.pues_id_puesto', $inicio, $corte), 'o')
            ->where('pd.pudi_id_tipo_directorio', $tipo)
            ->when($zona, fn (Builder $q, int $id) => $q->where('p.pues_id_zona_regional', $id))
            ->when($coordinacion, fn (Builder $q, int $id) => $q->where('p.pues_id_coordinacion_nacional', $id))
            // Un puesto desactivado solo aparece en los años en que alguien lo ocupó
            ->where(fn (Builder $q) => $q->where('p.pues_activo', true)->orWhereNotNull('o.persona'))
            ->orderBy('pd.pudi_orden')
            ->get([
                'c.carg_nombre_m as cargo', 'c.carg_nombre_f as cargo_f', 'c.carg_nivel as nivel',
                'z.zore_numero as zona_numero', 'z.zore_nombre as zona_nombre', 'cn.cona_nombre as coordinacion', 'org.orga_nombre as organizacion',
                DB::raw('null as institucion'), 'o.persona',
            ]);

        $personas = $this->personas($filas->pluck('persona')->filter()->all());

        return $filas->map(function (object $fila) use ($personas): object {
            $persona = $personas[$fila->persona] ?? null;

            // Como en el sitio: "Presidenta", sin "· Nacional"; los demás con su zona o coordinación
            $nombre = $this->puestos->nombre($fila, $persona?->genero);

            return (object) [
                'puesto' => $fila->nivel === 'Nacional' && $fila->coordinacion === null ? explode(' · ', $nombre)[0] : $nombre,
                ...$this->datosDe($persona),
            ];
        });
    }

    /**
     * Las instituciones que ese año fueron afiliadas u observadoras, con su titular.
     *
     * @return Collection<int, object> {numero, institucion, universidad, participacion, zona, entidad, puesto, nombre, grado, telefonos[], correos[], vacante}
     */
    public function instituciones(int $anio, ?int $zona = null, ?int $entidad = null, ?string $participacion = null): Collection
    {
        [$inicio, $corte] = $this->periodo($anio);

        $filas = DB::table('institucion_educativa as i')
            // Su participación ese año (el periodo más reciente que toca el año)
            ->joinLateral(
                DB::table('institucion_participacion as ip')
                    ->whereColumn('ip.inpa_id_institucion', 'i.ined_id_institucion_educativa')
                    ->where('ip.inpa_fecha_inicio', '<=', $corte)
                    ->where(fn (Builder $q) => $q->whereNull('ip.inpa_fecha_fin')->orWhere('ip.inpa_fecha_fin', '>=', $inicio))
                    ->orderByDesc('ip.inpa_fecha_inicio')
                    ->limit(1)
                    ->select('ip.inpa_tipo as tipo'),
                'pa',
            )
            // Su nombre de ese año (y el de su universidad, si es facultad o campus)
            ->joinLateral($this->nombreEn('i.ined_id_institucion_educativa', $corte), 'n')
            ->leftJoinLateral($this->nombreEn('i.ined_id_institucion_padre', $corte), 'np')
            ->leftJoin('zona_regional as z', 'z.zore_id_zona_regional', '=', 'i.ined_id_zona_regional')
            ->leftJoin('domicilio as d', 'd.domi_id_domicilio', '=', 'i.ined_id_domicilio')
            ->leftJoin('colonia as col', 'col.colo_id_colonia', '=', 'd.domi_id_colonia')
            ->leftJoin('alcaldia_municipio as m', 'm.almu_id_alcaldia_municipio', '=', 'col.colo_id_alcaldia_municipio')
            ->leftJoin('entidad_federativa as e', 'e.enfe_id_entidad_federativa', '=', 'm.almu_id_entidad_federativa')
            // Su titular: el puesto titular de la institución y quien lo ocupó ese año
            ->leftJoin('puesto as p', fn (JoinClause $j) => $j->on('p.pues_id_institucion_educativa', '=', 'i.ined_id_institucion_educativa')->where('p.pues_titularidad', true))
            ->leftJoin('cargo as c', 'c.carg_id_cargo', '=', 'p.pues_id_cargo')
            ->leftJoinLateral($this->ultimaDesignacion('p.pues_id_puesto', $inicio, $corte), 'o')
            ->whereIn('pa.tipo', $participacion ? [$participacion] : self::PARTICIPACIONES)
            ->when($zona, fn (Builder $q, int $id) => $q->where('i.ined_id_zona_regional', $id))
            ->when($entidad, fn (Builder $q, int $id) => $q->where('e.enfe_id_entidad_federativa', $id))
            ->orderByRaw('i.ined_num_afiliacion is null, i.ined_num_afiliacion')
            ->orderBy('n.nombre')
            ->get([
                'i.ined_num_afiliacion as numero', 'n.nombre as institucion', 'np.nombre as universidad', 'pa.tipo as participacion',
                'z.zore_numero as zona_numero', 'z.zore_nombre as zona_nombre', 'e.enfe_nombre as entidad',
                'c.carg_nombre_m as cargo', 'c.carg_nombre_f as cargo_f', 'o.persona',
            ]);

        $personas = $this->personas($filas->pluck('persona')->filter()->all());

        return $filas->map(function (object $fila) use ($personas): object {
            $persona = $personas[$fila->persona] ?? null;
            $cargo = $persona?->genero === 'F' && filled($fila->cargo_f) ? $fila->cargo_f : $fila->cargo;

            return (object) [
                'numero' => $fila->numero,
                'institucion' => $fila->institucion,
                'universidad' => $fila->universidad,
                'participacion' => $fila->participacion,
                'zona' => $fila->zona_numero ? "Zona {$fila->zona_numero} · {$fila->zona_nombre}" : null,
                'entidad' => $fila->entidad,
                'puesto' => $cargo,
                ...$this->datosDe($persona),
            ];
        });
    }

    /**
     * Los correos visibles de las filas, sin repetir, en el orden del directorio.
     *
     * @param  Collection<int, object>  $filas
     * @return array<int, string>
     */
    public function correos(Collection $filas): array
    {
        return $filas->flatMap(fn (object $fila) => $fila->correos)
            ->unique(fn (string $correo) => mb_strtolower($correo))
            ->values()
            ->all();
    }

    /**
     * Del 1 de enero del año a su último día (o a hoy, si es el año en curso).
     *
     * @return array{0: string, 1: string}
     */
    private function periodo(int $anio): array
    {
        $fin = Carbon::create($anio, 12, 31);

        return ["{$anio}-01-01", ($fin->isFuture() ? now() : $fin)->toDateString()];
    }

    /** La designación más reciente del puesto que toca el periodo. */
    private function ultimaDesignacion(string $puesto, string $inicio, string $corte): Builder
    {
        return DB::table('designacion as dd')
            ->whereColumn('dd.desi_id_puesto', $puesto)
            ->where('dd.desi_fecha_inicio', '<=', $corte)
            ->where(fn (Builder $q) => $q->whereNull('dd.desi_fecha_fin')->orWhere('dd.desi_fecha_fin', '>=', $inicio))
            ->orderByDesc('dd.desi_fecha_inicio')
            ->limit(1)
            ->select('dd.desi_id_persona as persona');
    }

    /** El nombre que tenía la institución en esa fecha (o el primero que tuvo, si es posterior). */
    private function nombreEn(string $institucion, string $fecha): Builder
    {
        return DB::table('institucion_nombre as nn')
            ->whereColumn('nn.instn_id_institucion', $institucion)
            ->orderByRaw('nn.instn_fecha_inicio <= ? desc, nn.instn_fecha_inicio desc', [$fecha])
            ->limit(1)
            ->select('nn.instn_nombre as nombre');
    }

    /**
     * Lo que se muestra de la persona; una persona oculta (o ninguna) es "Vacante".
     *
     * @return array{nombre: ?string, grado: ?string, pertenencia: ?string, telefonos: array<int, string>, correos: array<int, string>, vacante: bool}
     */
    private function datosDe(?object $persona): array
    {
        return [
            'nombre' => $persona?->nombre,
            'grado' => $persona?->grado,
            'pertenencia' => $persona?->pertenencia,
            'telefonos' => $persona?->telefonos ?? [],
            'correos' => $persona?->correos ?? [],
            'vacante' => $persona === null,
        ];
    }

    /**
     * Nombre, grado (el más alto, en su género), pertenencia y contactos
     * visibles de las personas que no están ocultas.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, object>
     */
    private function personas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $personas = DB::table('persona as per')
            ->leftJoinLateral($this->nombreEn('per.pers_id_institucion_educativa', now()->toDateString()), 'n')
            ->leftJoin('institucion_educativa as i', 'i.ined_id_institucion_educativa', '=', 'per.pers_id_institucion_educativa')
            ->leftJoinLateral($this->nombreEn('i.ined_id_institucion_padre', now()->toDateString()), 'np')
            ->leftJoin('organizacion as org', 'org.orga_id_organizacion', '=', 'per.pers_id_organizacion')
            ->whereIn('per.pers_id_persona', array_unique($ids))
            ->where('per.pers_oculta_admin', false)
            ->select([
                'per.pers_id_persona as id', 'per.pers_genero as genero',
                DB::raw("concat_ws(' ', per.pers_nombre, per.pers_primer_apellido, per.pers_segundo_apellido) as nombre"),
                'n.nombre as institucion', 'np.nombre as universidad', 'org.orga_nombre as organizacion',
                'grado' => DB::table('persona_nivel_academico as pna')
                    ->join('nivel_academico as na', 'na.niac_id_nivel_academico', '=', 'pna.pena_id_nivel_academico')
                    ->whereColumn('pna.pena_id_persona', 'per.pers_id_persona')
                    ->orderByRaw("case na.niac_nombre when 'Doctorado' then 3 when 'Maestría' then 2 when 'Licenciatura' then 1 else 0 end desc")
                    ->orderByDesc('na.niac_id_nivel_academico')
                    ->limit(1)
                    ->selectRaw("case when per.pers_genero = 'F' then na.niac_abr_f else na.niac_abr_m end"),
            ])
            ->get()
            ->keyBy('id');

        $contactos = DB::table('contacto as ct')
            ->join('tipo_contacto as t', 't.tico_id_tipo_contacto', '=', 'ct.cont_id_tipo_contacto')
            ->whereIn('ct.cont_id_persona', $personas->keys())
            ->where('ct.cont_visible', true)
            ->orderBy('ct.cont_id_contacto')
            ->get(['ct.cont_id_persona as persona', 't.tico_nombre as tipo', 'ct.cont_valor as valor', 'ct.cont_lada as lada', 'ct.cont_extension as extension'])
            ->groupBy('persona');

        return $personas->map(function (object $p) use ($contactos): object {
            $suyos = $contactos->get($p->id, collect());
            $p->pertenencia = $p->organizacion ?? collect([$p->institucion, $p->universidad])->filter()->implode(' · ') ?: null;
            $p->correos = $suyos->where('tipo', 'Correo')->pluck('valor')->values()->all();
            $p->telefonos = $suyos->where('tipo', '!=', 'Correo')
                ->map(fn (object $c) => trim(($c->lada ? "({$c->lada}) " : '').$c->valor.($c->extension ? " ext. {$c->extension}" : '')))
                ->values()->all();

            return $p;
        })->all();
    }
}

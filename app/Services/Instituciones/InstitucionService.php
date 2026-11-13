<?php

namespace App\Services\Instituciones;

use App\Models\Usuario;
use App\Services\Catalogos\BitacoraCatalogo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Instituciones educativas: matrices, afiliadas y observadoras; facultades
 * y campus bajo una universidad.
 *
 * Reglas que respeta (sección 4 y el esquema):
 * - Las instituciones no se borran: se desactivan y conservan su número.
 * - Al registrar se guardan, en la misma transacción, la institución, su
 *   razón social vigente (INSTITUCION_NOMBRE) y su participación actual con
 *   su historial (INSTITUCION_PARTICIPACION), más la bitácora.
 * - Editar CORRIGE la razón social vigente (sin historial). El cambio de
 *   razón social y el de participación con historial son acciones aparte.
 * - Los triggers de la base revisan matriz/universidad y facultad/campus.
 */
class InstitucionService
{
    use BitacoraCatalogo;

    public const POR_PAGINA = 25;

    public const PARTICIPACIONES = ['Matriz', 'Afiliada', 'Observadora'];

    public const TIPOS = ['Universidad', 'Facultad', 'Campus'];

    public const SECTORES = ['Publica' => 'Pública', 'Privada' => 'Privada'];

    public function __construct(private readonly DomicilioService $domicilios) {}

    /**
     * Lista con filtros: texto (nombre o número), participación, zona,
     * estado y año de participación (según el historial).
     *
     * @param  array{texto?: ?string, participacion?: ?string, zona?: ?int, estado?: ?string, anio?: ?int}  $filtros
     */
    public function listado(array $filtros): LengthAwarePaginator
    {
        return $this->consulta()
            ->when($filtros['texto'] ?? null, function (Builder $q, string $texto) {
                $q->where(fn (Builder $w) => $w
                    ->whereRaw('n.instn_nombre ilike ?', ['%'.addcslashes($texto, '%_\\').'%'])
                    ->orWhere('i.ined_num_afiliacion', 'like', addcslashes($texto, '%_\\').'%'));
            })
            ->when($filtros['zona'] ?? null, fn (Builder $q, int $zona) => $q->where('i.ined_id_zona_regional', $zona))
            ->when(($filtros['estado'] ?? null) === 'activas', fn (Builder $q) => $q->where('i.ined_activo', true))
            ->when(($filtros['estado'] ?? null) === 'desactivadas', fn (Builder $q) => $q->where('i.ined_activo', false))
            ->when($filtros['anio'] ?? null, function (Builder $q, int $anio) use ($filtros) {
                // Participó ese año: algún periodo de su historial toca el año
                $q->whereExists(fn (Builder $e) => $e->from('institucion_participacion as p')
                    ->whereColumn('p.inpa_id_institucion', 'i.ined_id_institucion_educativa')
                    ->when($filtros['participacion'] ?? null, fn (Builder $w, string $tipo) => $w->where('p.inpa_tipo', $tipo))
                    ->where('p.inpa_fecha_inicio', '<=', "{$anio}-12-31")
                    ->where(fn (Builder $w) => $w->whereNull('p.inpa_fecha_fin')->orWhere('p.inpa_fecha_fin', '>=', "{$anio}-01-01")));
            }, function (Builder $q) use ($filtros) {
                $q->when($filtros['participacion'] ?? null, fn (Builder $w, string $tipo) => $w->where('i.ined_participacion', $tipo));
            })
            ->orderBy('n.instn_nombre')
            ->paginate(self::POR_PAGINA)
            ->withQueryString()
            ->through(function (object $institucion): object {
                $institucion->motivo_para_no_borrar = $this->motivoParaNoBorrar((int) $institucion->id);
                $institucion->periodo_desde = DB::table('institucion_participacion')
                    ->where('inpa_id_institucion', $institucion->id)->whereNull('inpa_fecha_fin')->value('inpa_fecha_inicio');
                $institucion->ultimo_fin = DB::table('institucion_participacion')
                    ->where('inpa_id_institucion', $institucion->id)->max('inpa_fecha_fin');

                return $institucion;
            });
    }

    /**
     * Años con algún periodo de participación, para el filtro (del más reciente al más antiguo).
     *
     * @return array<int, int>
     */
    public function anios(): array
    {
        $primero = DB::table('institucion_participacion')->min('inpa_fecha_inicio');
        $actual = (int) now()->format('Y');

        return $primero === null ? [$actual] : range($actual, (int) substr((string) $primero, 0, 4));
    }

    /**
     * Universidades que pueden ser padre de una facultad o campus.
     *
     * @return Collection<int, object>
     */
    public function universidades(): Collection
    {
        return $this->consulta()
            ->leftJoin('domicilio as d', 'd.domi_id_domicilio', '=', 'i.ined_id_domicilio')
            ->leftJoin('colonia as c', 'c.colo_id_colonia', '=', 'd.domi_id_colonia')
            ->leftJoin('alcaldia_municipio as m', 'm.almu_id_alcaldia_municipio', '=', 'c.colo_id_alcaldia_municipio')
            ->leftJoin('entidad_federativa as e', 'e.enfe_id_entidad_federativa', '=', 'm.almu_id_entidad_federativa')
            ->addSelect('e.enfe_nombre as entidad')
            ->where('t.tiie_nombre', 'Universidad')
            ->orderBy('n.instn_nombre')
            ->get();
    }

    /**
     * Números de afiliación ya usados: [id, número, nombre], para
     * avisar mientras se escribe si uno ya existe.
     *
     * @return array<int, array{0: int, 1: string, 2: string}>
     */
    public function numerosUsados(): array
    {
        return $this->consulta()
            ->whereNotNull('i.ined_num_afiliacion')
            ->get()
            ->map(fn (object $i) => [(int) $i->id, $i->numero, $i->nombre])
            ->all();
    }

    public function buscar(int $id): ?object
    {
        $institucion = $this->consulta()->where('i.ined_id_institucion_educativa', $id)->first();

        if ($institucion !== null) {
            $institucion->domicilio = $this->domicilios->datos($institucion->domicilio_id);
        }

        return $institucion;
    }

    /**
     * @param  array<string, mixed>  $datos  validados por InstitucionRequest
     */
    public function registrar(Usuario $actor, array $datos): int
    {
        return DB::transaction(function () use ($actor, $datos): int {
            $domicilio = $datos['domicilio'] !== null ? $this->domicilios->guardar(null, $datos['domicilio']) : null;

            $id = (int) DB::table('institucion_educativa')->insertGetId([
                'ined_id_tipo_institucion' => $this->idTipo($datos['tipo']),
                'ined_id_institucion_padre' => $datos['universidad'],
                'ined_participacion' => $datos['participacion'],
                'ined_num_afiliacion' => $datos['numero'],
                'ined_sector' => $datos['sector'],
                'ined_sitio_web' => $datos['sitio_web'],
                'ined_id_domicilio' => $domicilio,
                'ined_id_zona_regional' => $datos['zona'],
                'ined_activo' => true,
            ], 'ined_id_institucion_educativa');

            DB::table('institucion_nombre')->insert([
                'instn_id_institucion' => $id,
                'instn_nombre' => $datos['razon_social'],
                'instn_fecha_inicio' => $datos['desde'],
            ]);

            DB::table('institucion_participacion')->insert([
                'inpa_id_institucion' => $id,
                'inpa_tipo' => $datos['participacion'],
                'inpa_fecha_inicio' => $datos['desde'],
            ]);

            $this->bitacora($actor, 'Registro', 'INSTITUCION_EDUCATIVA', $id);

            return $id;
        });
    }

    /**
     * Guarda los cambios de Editar. La participación no cambia aquí (tiene su
     * propia acción con historial); la razón social se corrige sin historial.
     *
     * @param  array<string, mixed>  $datos  validados por InstitucionRequest
     */
    public function actualizar(Usuario $actor, int $id, array $datos): void
    {
        DB::transaction(function () use ($actor, $id, $datos): void {
            $actual = DB::table('institucion_educativa as i')
                ->join('tipo_institucion_educativa as t', 't.tiie_id_tipo_institucion', '=', 'i.ined_id_tipo_institucion')
                ->where('i.ined_id_institucion_educativa', $id)
                ->lockForUpdate()
                ->first(['i.*', 't.tiie_nombre']);
            $nombre = DB::table('institucion_nombre')->where('instn_id_institucion', $id)->whereNull('instn_fecha_fin')->lockForUpdate()->first();
            $domicilioAntes = $this->domicilios->texto($actual->ined_id_domicilio);

            // Domicilio: se crea, se actualiza o (solo una matriz) se quita
            $domicilio = $actual->ined_id_domicilio;
            if ($datos['domicilio'] !== null) {
                $domicilio = $this->domicilios->guardar($domicilio, $datos['domicilio']);
            }

            DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $id)->update([
                'ined_id_tipo_institucion' => $this->idTipo($datos['tipo']),
                'ined_id_institucion_padre' => $datos['universidad'],
                'ined_num_afiliacion' => $actual->ined_participacion === 'Afiliada' ? $datos['numero'] : null,
                'ined_sector' => $datos['sector'],
                'ined_sitio_web' => $datos['sitio_web'],
                'ined_id_domicilio' => $datos['domicilio'] !== null ? $domicilio : null,
                'ined_id_zona_regional' => $datos['zona'],
            ]);

            if ($datos['domicilio'] === null && $actual->ined_id_domicilio !== null) {
                $this->domicilios->borrar($actual->ined_id_domicilio);
            }

            if ($nombre !== null && $nombre->instn_nombre !== $datos['razon_social']) {
                DB::table('institucion_nombre')->where('instn_id_institucion_nombre', $nombre->instn_id_institucion_nombre)
                    ->update(['instn_nombre' => $datos['razon_social']]);
            }

            $cambios = array_filter([
                'Razón social (corrección)' => [$nombre?->instn_nombre, $datos['razon_social']],
                'Tipo' => [$actual->tiie_nombre, $datos['tipo']],
                'Universidad' => [$this->nombreDe($actual->ined_id_institucion_padre), $this->nombreDe($datos['universidad'])],
                'Número de afiliación' => [$actual->ined_num_afiliacion, $actual->ined_participacion === 'Afiliada' ? $datos['numero'] : null],
                'Sector' => [self::SECTORES[$actual->ined_sector] ?? $actual->ined_sector, self::SECTORES[$datos['sector']]],
                'Sitio web' => [$actual->ined_sitio_web, $datos['sitio_web']],
                'Zona regional' => [$this->zonaDe($actual->ined_id_zona_regional), $this->zonaDe($datos['zona'])],
                'Domicilio' => [$domicilioAntes, $this->domicilios->texto($datos['domicilio'] !== null ? $domicilio : null)],
            ], fn (array $par) => $par[0] !== $par[1]);

            if ($cambios === []) {
                return;
            }

            $idBitacora = $this->bitacora($actor, 'Modificacion', 'INSTITUCION_EDUCATIVA', $id);
            foreach ($cambios as $campo => [$anterior, $nuevo]) {
                $this->detalle($idBitacora, $id, $campo, $anterior, $nuevo);
            }
        });
    }

    /**
     * Desactivar: deja de participar en esa fecha (se cierra su periodo de
     * participación) y conserva su número. Activar: vuelve a participar desde
     * esa fecha (se abre un periodo nuevo con la misma participación; si vuelve
     * el mismo día o el siguiente, se reabre el periodo que se había cerrado).
     * Así el historial y el filtro por año quedan exactos.
     */
    public function cambiarEstado(Usuario $actor, int $id, bool $activa, string $fecha): void
    {
        DB::transaction(function () use ($actor, $id, $activa, $fecha): void {
            $actual = DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $id)->lockForUpdate()->first();

            if ((bool) $actual->ined_activo === $activa) {
                return;
            }

            DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $id)->update(['ined_activo' => $activa]);

            if ($activa) {
                $ultimo = DB::table('institucion_participacion')->where('inpa_id_institucion', $id)
                    ->whereNotNull('inpa_fecha_fin')->orderByDesc('inpa_fecha_fin')->first();

                if ($ultimo !== null && $fecha <= date('Y-m-d', strtotime($ultimo->inpa_fecha_fin.' +1 day'))) {
                    // Vuelve el mismo día o al día siguiente (por ejemplo, se desactivó por error):
                    // se reabre el mismo periodo, como si no hubiera dejado de participar
                    DB::table('institucion_participacion')->where('inpa_id_participacion', $ultimo->inpa_id_participacion)->update(['inpa_fecha_fin' => null]);
                } else {
                    DB::table('institucion_participacion')->insert([
                        'inpa_id_institucion' => $id,
                        'inpa_tipo' => $actual->ined_participacion,
                        'inpa_fecha_inicio' => $fecha,
                    ]);
                }
            } else {
                DB::table('institucion_participacion')
                    ->where('inpa_id_institucion', $id)->whereNull('inpa_fecha_fin')
                    ->update(['inpa_fecha_fin' => $fecha]);
            }

            $idBitacora = $this->bitacora($actor, $activa ? 'Activacion' : 'Desactivacion', 'INSTITUCION_EDUCATIVA', $id);
            $this->detalle($idBitacora, $id, $activa ? 'Vuelve a participar desde' : 'Deja de participar el', null, $fecha);
        });
    }

    /**
     * Fechas válidas para activar o desactivar: desactivar no antes del inicio
     * del periodo actual; activar no antes del último fin (el mismo día o el
     * siguiente reabre ese periodo).
     *
     * @return array{desde: ?string, ultimo_fin: ?string}
     */
    public function limitesDeFecha(int $id): array
    {
        return [
            'desde' => DB::table('institucion_participacion')->where('inpa_id_institucion', $id)->whereNull('inpa_fecha_fin')->value('inpa_fecha_inicio'),
            'ultimo_fin' => DB::table('institucion_participacion')->where('inpa_id_institucion', $id)->max('inpa_fecha_fin'),
        ];
    }

    /**
     * Por qué no se puede borrar, o null si sí se puede (sin personas, sedes ni puestos).
     */
    public function motivoParaNoBorrar(int $id): ?string
    {
        $partes = array_filter([
            $this->cantidad(DB::table('persona')->where('pers_id_institucion_educativa', $id)->count(), 'persona', 'personas'),
            $this->cantidad(DB::table('institucion_educativa')->where('ined_id_institucion_padre', $id)->count(), 'sede (facultad o campus)', 'sedes (facultades o campus)'),
            $this->cantidad(DB::table('puesto')->where('pues_id_institucion_educativa', $id)->count(), 'puesto', 'puestos'),
        ]);

        if ($partes === []) {
            return null;
        }

        $ultima = array_pop($partes);
        $lista = $partes === [] ? $ultima : implode(', ', $partes).' y '.$ultima;

        return "No se puede borrar porque tiene {$lista}. Si ya no participa, desactívala: conserva su historial.";
    }

    /**
     * Borra una institución capturada por error (sin personas, sedes ni
     * puestos), con su historial de nombres y de participación y su domicilio.
     * Queda en la bitácora con su motivo.
     */
    public function eliminar(Usuario $actor, int $id, string $motivo): bool
    {
        return DB::transaction(function () use ($actor, $id, $motivo): bool {
            $actual = DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $id)->lockForUpdate()->first();

            if ($actual === null || $this->motivoParaNoBorrar($id) !== null) {
                return false;
            }

            $idBitacora = $this->bitacora($actor, 'Eliminacion', 'INSTITUCION_EDUCATIVA', $id, $motivo);
            $this->detalle($idBitacora, $id, 'Razón social', $this->nombreDe($id), null);
            $this->detalle($idBitacora, $id, 'Participación', $actual->ined_participacion, null);
            if ($actual->ined_num_afiliacion !== null) {
                $this->detalle($idBitacora, $id, 'Número de afiliación', $actual->ined_num_afiliacion, null);
            }

            DB::table('institucion_nombre')->where('instn_id_institucion', $id)->delete();
            DB::table('institucion_participacion')->where('inpa_id_institucion', $id)->delete();
            DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $id)->delete();

            if ($actual->ined_id_domicilio !== null) {
                $this->domicilios->borrar($actual->ined_id_domicilio);
            }

            return true;
        });
    }

    private function cantidad(int $total, string $singular, string $plural): ?string
    {
        return match (true) {
            $total === 0 => null,
            $total === 1 => "1 {$singular}",
            default => "{$total} {$plural}",
        };
    }

    /**
     * Consulta base: institución con su razón social vigente, su tipo, su
     * universidad (con la razón social vigente de esta) y su zona.
     */
    private function consulta(): Builder
    {
        return DB::table('institucion_educativa as i')
            ->join('tipo_institucion_educativa as t', 't.tiie_id_tipo_institucion', '=', 'i.ined_id_tipo_institucion')
            ->leftJoin('institucion_nombre as n', fn ($j) => $j->on('n.instn_id_institucion', '=', 'i.ined_id_institucion_educativa')->whereNull('n.instn_fecha_fin'))
            ->leftJoin('institucion_nombre as np', fn ($j) => $j->on('np.instn_id_institucion', '=', 'i.ined_id_institucion_padre')->whereNull('np.instn_fecha_fin'))
            ->leftJoin('zona_regional as z', 'z.zore_id_zona_regional', '=', 'i.ined_id_zona_regional')
            ->select([
                'i.ined_id_institucion_educativa as id',
                'n.instn_nombre as nombre',
                't.tiie_nombre as tipo',
                'i.ined_id_institucion_padre as universidad_id',
                'np.instn_nombre as universidad',
                'i.ined_participacion as participacion',
                'i.ined_num_afiliacion as numero',
                'i.ined_sector as sector',
                'i.ined_sitio_web as sitio_web',
                'i.ined_id_domicilio as domicilio_id',
                'i.ined_id_zona_regional as zona_id',
                'z.zore_numero as zona_numero',
                'z.zore_nombre as zona_nombre',
                'i.ined_activo as activa',
            ]);
    }

    private function idTipo(string $tipo): int
    {
        return (int) DB::table('tipo_institucion_educativa')->where('tiie_nombre', $tipo)->value('tiie_id_tipo_institucion');
    }

    private function nombreDe(?int $institucion): ?string
    {
        return $institucion === null ? null
            : DB::table('institucion_nombre')->where('instn_id_institucion', $institucion)->whereNull('instn_fecha_fin')->value('instn_nombre');
    }

    private function zonaDe(?int $zona): ?string
    {
        if ($zona === null) {
            return null;
        }

        $z = DB::table('zona_regional')->where('zore_id_zona_regional', $zona)->first();

        return $z ? "Zona {$z->zore_numero} · {$z->zore_nombre}" : null;
    }
}

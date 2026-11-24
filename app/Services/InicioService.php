<?php

namespace App\Services;

use App\Enums\Privilegio;
use App\Enums\ResultadoAcceso;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos de la pantalla de inicio (sección 10). Todo sale de consultas a la base:
 * contadores, la actividad reciente (de todos, para quien ve la bitácora; la
 * propia, para los demás) y los pendientes por atender.
 */
class InicioService
{
    public const MOVIMIENTOS_RECIENTES = 5;

    public function __construct(private readonly BitacoraService $bitacora) {}

    /**
     * Instituciones afiliadas y observadoras activas (las matrices no se cuentan)
     * y coordinaciones nacionales activas. Los "_total" incluyen las desactivadas
     * y se muestran como contexto ("de N registradas").
     *
     * @return array{afiliadas: int, afiliadas_total: int, observadoras: int, observadoras_total: int, coordinaciones: int, coordinaciones_total: int}
     */
    public function contadores(): array
    {
        $instituciones = DB::table('institucion_educativa')
            ->whereIn('ined_participacion', ['Afiliada', 'Observadora'])
            ->selectRaw('ined_participacion, count(*) filter (where ined_activo) as activas, count(*) as total')
            ->groupBy('ined_participacion')
            ->get()
            ->keyBy('ined_participacion');

        $coordinaciones = DB::table('coordinacion_nacional')
            ->selectRaw('count(*) filter (where cona_activo) as activas, count(*) as total')
            ->first();

        return [
            'afiliadas' => (int) ($instituciones['Afiliada']->activas ?? 0),
            'afiliadas_total' => (int) ($instituciones['Afiliada']->total ?? 0),
            'observadoras' => (int) ($instituciones['Observadora']->activas ?? 0),
            'observadoras_total' => (int) ($instituciones['Observadora']->total ?? 0),
            'coordinaciones' => (int) $coordinaciones->activas,
            'coordinaciones_total' => (int) $coordinaciones->total,
        ];
    }

    /**
     * Inicio de sesión exitoso ANTERIOR al actual, o null si es el primero.
     */
    public function inicioDeSesionAnterior(Usuario $cuenta): ?Carbon
    {
        $fecha = DB::table('log_acceso')
            ->where('loga_id_usuario', $cuenta->usua_id_usuario)
            ->where('loga_resultado', ResultadoAcceso::Exitoso->value)
            ->orderByDesc('loga_fecha_hora')
            ->orderByDesc('loga_id_log')
            ->skip(1)
            ->value('loga_fecha_hora');

        return $fecha === null ? null : Carbon::parse($fecha);
    }

    /**
     * Últimos movimientos de la bitácora para la pantalla de inicio.
     *
     * @return array{movimientos: Collection<int, array<string, mixed>>, hayMas: bool}
     */
    public function bitacoraReciente(?int $usuario = null): array
    {
        return [
            'movimientos' => $this->bitacora->recientes(self::MOVIMIENTOS_RECIENTES, $usuario),
            'hayMas' => $this->bitacora->total($usuario) > self::MOVIMIENTOS_RECIENTES,
        ];
    }

    /**
     * Pendientes que la cuenta puede atender, cada uno con su enlace:
     * - puestos vacantes en los directorios (por directorio);
     * - instituciones afiliadas u observadoras activas sin titular hoy;
     * - personas con designación vigente sin correo visible (el sitio no las podría contactar).
     * Una persona oculta cuenta como vacante, igual que en los directorios.
     *
     * @return array<int, array{clave: string, cantidad: int, texto: string, enlaces: array<int, array{texto: string, url: string}>, todos: ?string}>
     */
    public function porAtender(Usuario $cuenta): array
    {
        $hoy = now()->toDateString();
        $vigente = fn ($q) => $q->where('d.desi_fecha_inicio', '<=', $hoy)
            ->where(fn ($w) => $w->whereNull('d.desi_fecha_fin')->orWhere('d.desi_fecha_fin', '>=', $hoy));
        $ocupado = fn (string $puesto) => fn ($q) => $q->from('designacion as d')->join('persona as per', 'per.pers_id_persona', '=', 'd.desi_id_persona')
            ->whereColumn('d.desi_id_puesto', $puesto)->where('per.pers_oculta_admin', false)->where($vigente);
        $pendientes = [];

        if ($cuenta->can(Privilegio::VerDirectorios->value)) {
            $vacantes = DB::table('puesto_directorio as pd')
                ->join('tipo_directorio as t', 't.tidi_id_tipo_directorio', '=', 'pd.pudi_id_tipo_directorio')
                ->join('puesto as p', 'p.pues_id_puesto', '=', 'pd.pudi_id_puesto')
                ->where('p.pues_activo', true)
                ->whereNotExists($ocupado('p.pues_id_puesto'))
                ->groupBy('t.tidi_id_tipo_directorio', 't.tidi_nombre')
                ->orderBy('t.tidi_id_tipo_directorio')
                ->get(['t.tidi_id_tipo_directorio as id', 't.tidi_nombre as nombre', DB::raw('count(*) as cantidad')]);
            $pendientes[] = [
                'clave' => 'vacantes',
                'cantidad' => (int) $vacantes->sum('cantidad'),
                'texto' => 'puestos vacantes en los directorios',
                'enlaces' => $vacantes->map(fn (object $v) => ['texto' => "{$v->nombre}: {$v->cantidad}", 'url' => route('directorios.index', ['directorio' => $v->id])])->all(),
                'todos' => null,
            ];
        }

        if ($cuenta->can(Privilegio::GestionarInstituciones->value)) {
            $sinTitular = DB::table('institucion_educativa as i')
                ->join('institucion_nombre as n', fn ($j) => $j->on('n.instn_id_institucion', '=', 'i.ined_id_institucion_educativa')->whereNull('n.instn_fecha_fin'))
                ->where('i.ined_activo', true)
                ->whereIn('i.ined_participacion', ['Afiliada', 'Observadora'])
                ->whereNotExists(fn ($q) => $q->from('puesto as p')->whereColumn('p.pues_id_institucion_educativa', 'i.ined_id_institucion_educativa')
                    ->where('p.pues_titularidad', true)->whereExists($ocupado('p.pues_id_puesto')))
                ->orderBy('n.instn_nombre')
                ->get(['i.ined_id_institucion_educativa as id', 'n.instn_nombre as nombre']);
            $pendientes[] = [
                'clave' => 'sin-titular',
                'cantidad' => $sinTitular->count(),
                'texto' => 'instituciones afiliadas u observadoras sin titular',
                'enlaces' => $sinTitular->take(5)->map(fn (object $i) => ['texto' => $i->nombre, 'url' => route('instituciones.show', $i->id)])->all(),
                'todos' => $sinTitular->count() > 5 ? route('instituciones.index') : null,
            ];
        }

        if ($cuenta->can(Privilegio::GestionarPersonas->value)) {
            $sinCorreo = DB::table('persona as per')
                ->where('per.pers_oculta_admin', false)
                ->whereExists(fn ($q) => $q->from('designacion as d')->whereColumn('d.desi_id_persona', 'per.pers_id_persona')->where($vigente))
                ->whereNotExists(fn ($q) => $q->from('contacto as c')->join('tipo_contacto as t', 't.tico_id_tipo_contacto', '=', 'c.cont_id_tipo_contacto')
                    ->whereColumn('c.cont_id_persona', 'per.pers_id_persona')->where('t.tico_nombre', 'Correo')->where('c.cont_visible', true))
                ->orderBy('per.pers_primer_apellido')
                ->get(['per.pers_id_persona as id', DB::raw("concat_ws(' ', per.pers_nombre, per.pers_primer_apellido, per.pers_segundo_apellido) as nombre")]);
            $pendientes[] = [
                'clave' => 'sin-correo',
                'cantidad' => $sinCorreo->count(),
                'texto' => 'personas con designación vigente sin correo visible',
                'enlaces' => $sinCorreo->take(5)->map(fn (object $p) => ['texto' => $p->nombre, 'url' => route('personas.edit', $p->id)])->all(),
                'todos' => $sinCorreo->count() > 5 ? route('personas.index', ['situacion' => 'con-cargo']) : null,
            ];
        }

        return $pendientes;
    }
}

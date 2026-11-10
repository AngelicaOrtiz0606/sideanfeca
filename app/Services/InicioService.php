<?php

namespace App\Services;

use App\Enums\ResultadoAcceso;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos de la pantalla de inicio (sección 10). Todo sale de consultas a la base.
 * No incluye designaciones ni asignaciones.
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
    public function bitacoraReciente(): array
    {
        return [
            'movimientos' => $this->bitacora->recientes(self::MOVIMIENTOS_RECIENTES),
            'hayMas' => $this->bitacora->total() > self::MOVIMIENTOS_RECIENTES,
        ];
    }
}

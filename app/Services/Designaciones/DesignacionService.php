<?php

namespace App\Services\Designaciones;

use App\Models\Usuario;
use App\Services\Catalogos\BitacoraCatalogo;
use App\Services\Personas\PersonaService;
use App\Services\Puestos\PuestoService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Designaciones: una persona ocupando un puesto (un cargo en su ámbito)
 * desde una fecha, y hasta una fecha cuando deja el puesto.
 *
 * - Designar: busca el puesto con el cargo y su ámbito, o lo crea si no
 *   existe, y registra la designación. Lo que se hizo va como detalles del
 *   movimiento de bitácora que pide quien llama (una sola línea por acción).
 * - Terminar (la persona deja el puesto): desactivar_designacion de la base.
 * - Borrar una designación capturada por error: eliminar_designacion_erronea.
 * Las dos funciones revisan el privilegio gestionar_designaciones y
 * escriben su propia bitácora.
 */
class DesignacionService
{
    use BitacoraCatalogo;

    public const MOTIVOS_PARA_BORRAR = ['Error de captura', 'Duplicado'];

    public function __construct(private readonly PuestoService $puestos) {}

    /**
     * Registra las designaciones de una persona. Cada una queda como detalle
     * del movimiento $idBitacora ("Designación: Presidenta · Nacional, desde
     * 20/11/2026"), y cada puesto creado como "Puesto creado".
     *
     * @param  array<int, array{cargo: int, zona: ?int, coordinacion: ?int, institucion: ?int, fecha_inicio: string}>  $designaciones
     */
    public function designar(int $persona, string $genero, array $designaciones, int $idBitacora): void
    {
        $servicio = app(PersonaService::class);

        foreach ($designaciones as $designacion) {
            $creado = false;
            $puesto = $servicio->puestoDe($designacion['cargo'], $designacion['zona'], $designacion['coordinacion'], $designacion['institucion']);
            if ($puesto === null) {
                $puesto = (int) DB::table('puesto')->insertGetId([
                    'pues_id_cargo' => $designacion['cargo'],
                    'pues_id_zona_regional' => $designacion['zona'],
                    'pues_id_coordinacion_nacional' => $designacion['coordinacion'],
                    'pues_id_institucion_educativa' => $designacion['institucion'],
                    'pues_titularidad' => false,
                    'pues_activo' => true,
                ], 'pues_id_puesto');
                $creado = true;
            }

            $id = (int) DB::table('designacion')->insertGetId([
                'desi_id_puesto' => $puesto,
                'desi_id_persona' => $persona,
                'desi_fecha_inicio' => $designacion['fecha_inicio'],
            ], 'desi_id_designacion');

            $datosPuesto = $this->puestos->buscar($puesto);
            $this->detalle($idBitacora, $id, 'Designación',
                null, $this->puestos->nombre($datosPuesto, $genero).', desde '.Carbon::parse($designacion['fecha_inicio'])->format('d/m/Y'));
            if ($creado) {
                $this->detalle($idBitacora, $puesto, 'Puesto creado', null, $this->puestos->nombre($datosPuesto));
            }
        }
    }

    /**
     * Agrega designaciones a una persona ya registrada: una sola línea en la
     * bitácora ("modificó las designaciones de la persona …") con cada una.
     *
     * @param  array<int, array{cargo: int, zona: ?int, coordinacion: ?int, institucion: ?int, fecha_inicio: string}>  $designaciones
     */
    public function agregar(Usuario $actor, int $persona, string $genero, array $designaciones): void
    {
        DB::transaction(function () use ($actor, $persona, $genero, $designaciones): void {
            $idBitacora = $this->bitacora($actor, 'Modificacion', 'PERSONA', $persona);
            $this->designar($persona, $genero, $designaciones, $idBitacora);
        });
    }

    /**
     * La persona deja el puesto: $fecha es el primer día sin el puesto (la
     * base guarda el día anterior como último día). Devuelve el error de la
     * base en palabras, o null si se registró.
     */
    public function terminar(Usuario $actor, int $designacion, string $fecha): ?string
    {
        return $this->llamar('select desactivar_designacion(?, ?, ?::date)', [$actor->usua_id_usuario, $designacion, $fecha]);
    }

    /**
     * Borra una designación capturada por error (nunca ocurrió). Devuelve el
     * error de la base en palabras, o null si se borró.
     */
    public function borrar(Usuario $actor, int $designacion, string $motivo): ?string
    {
        return $this->llamar('select eliminar_designacion_erronea(?, ?, ?)', [$actor->usua_id_usuario, $designacion, $motivo]);
    }

    /**
     * Toda persona debe tener una designación o una cuenta (validar_persona_con_razon).
     * Si es su única designación y no tiene cuenta, no se puede borrar: se dice por qué.
     */
    public function motivoParaNoBorrar(int $persona): ?string
    {
        $designaciones = DB::table('designacion')->where('desi_id_persona', $persona)->count();
        $tieneCuenta = DB::table('usuario')->where('usua_id_persona', $persona)->exists();

        return $designaciones <= 1 && ! $tieneCuenta
            ? 'Es su única designación y no tiene cuenta: no puede quedarse sin ninguna. Si se capturó por error, borra a la persona.'
            : null;
    }

    /**
     * La designación de esa persona (si es suya), con su puesto.
     */
    public function deLaPersona(int $persona, int $designacion): ?object
    {
        return DB::table('designacion')
            ->where('desi_id_designacion', $designacion)
            ->where('desi_id_persona', $persona)
            ->first(['desi_id_designacion as id', 'desi_id_puesto as puesto', 'desi_fecha_inicio as desde', 'desi_fecha_fin as hasta']);
    }

    /**
     * Llama una función de la base. Si la rechaza (raise exception), devuelve
     * su mensaje, que ya está escrito para la persona usuaria.
     *
     * @param  array<int, mixed>  $parametros
     */
    private function llamar(string $sql, array $parametros): ?string
    {
        try {
            DB::select($sql, $parametros);
        } catch (QueryException $error) {
            if (preg_match('/ERROR:\s+(.+?)(\s+\(Connection:|\s+CONTEXT:|\n|$)/u', $error->getMessage(), $partes) === 1) {
                return trim($partes[1]);
            }
            throw $error;
        }

        return null;
    }
}

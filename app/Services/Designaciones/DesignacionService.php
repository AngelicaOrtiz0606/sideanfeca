<?php

namespace App\Services\Designaciones;

use App\Models\Usuario;
use App\Services\Catalogos\BitacoraCatalogo;
use App\Services\Personas\PersonaService;
use App\Services\Puestos\PuestoService;
use App\Support\FuncionSql;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Designaciones: una persona ocupando un puesto (un cargo en su ámbito)
 * desde una fecha, y hasta una fecha cuando deja el puesto.
 *
 * - Designar: busca el puesto con el cargo y su ámbito, o lo crea si no
 *   existe, y registra la designación. Lo que se hizo va como detalles del
 *   movimiento de bitácora que pide quien llama (una sola línea por acción).
 * - Terminar (la persona deja el puesto): desactivar_designacion de la base.
 * - Borrar una designación capturada por error: eliminar_designacion_erronea.
 * - Titular de la institución: al designar en un puesto institucional se
 *   puede marcar como titular (el que sale en el directorio de Instituciones).
 *   Si el titular era otro puesto, ese deja de serlo; si era el mismo puesto
 *   y alguien lo ocupaba, su designación termina el día anterior.
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
     * @param  array<int, array{cargo: int, zona: ?int, coordinacion: ?int, institucion: ?int, organizacion?: ?int, fecha_inicio: string, fecha_fin?: ?string, titular?: bool}>  $designaciones
     */
    public function designar(int $persona, string $genero, array $designaciones, int $idBitacora, ?Usuario $actor = null): void
    {
        $servicio = app(PersonaService::class);

        foreach ($designaciones as $designacion) {
            $creado = false;
            $puesto = $servicio->puestoDe($designacion['cargo'], $designacion['zona'], $designacion['coordinacion'], $designacion['institucion'], $designacion['organizacion'] ?? null);
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

            if (($designacion['titular'] ?? false) && $designacion['institucion'] !== null) {
                $this->hacerTitular($actor, $puesto, $persona, $designacion['institucion'], $designacion['fecha_inicio'], $idBitacora);
            }

            $id = (int) DB::table('designacion')->insertGetId([
                'desi_id_puesto' => $puesto,
                'desi_id_persona' => $persona,
                'desi_fecha_inicio' => $designacion['fecha_inicio'],
                'desi_fecha_fin' => $designacion['fecha_fin'] ?? null,
            ], 'desi_id_designacion');

            $datosPuesto = $this->puestos->buscar($puesto);
            $this->detalle($idBitacora, $id, 'Designación',
                null, $this->puestos->nombre($datosPuesto, $genero).', desde '.Carbon::parse($designacion['fecha_inicio'])->format('d/m/Y')
                    .(($designacion['fecha_fin'] ?? null) ? ' hasta '.Carbon::parse($designacion['fecha_fin'])->format('d/m/Y') : ''));
            if ($creado) {
                $this->detalle($idBitacora, $puesto, 'Puesto creado', null, $this->puestos->nombre($datosPuesto));
            }
        }
    }

    /**
     * Agrega designaciones a una persona ya registrada: una sola línea en la
     * bitácora ("modificó las designaciones de la persona …") con cada una.
     *
     * @param  array<int, array{cargo: int, zona: ?int, coordinacion: ?int, institucion: ?int, organizacion?: ?int, fecha_inicio: string, fecha_fin?: ?string}>  $designaciones
     */
    public function agregar(Usuario $actor, int $persona, string $genero, array $designaciones): void
    {
        DB::transaction(function () use ($actor, $persona, $genero, $designaciones): void {
            $idBitacora = $this->bitacora($actor, 'Modificacion', 'PERSONA', $persona);
            $this->designar($persona, $genero, $designaciones, $idBitacora, $actor);
        });
    }

    /**
     * La persona deja el puesto: $fecha es el primer día sin el puesto (la
     * base guarda el día anterior como último día). Devuelve el error de la
     * base en palabras, o null si se registró.
     */
    /**
     * El titular de una institución en esa fecha: su puesto titular y, si
     * alguien lo ocupa, quién y desde cuándo. Null si no tiene puesto titular.
     */
    public function titularDe(int $institucion, ?string $fecha = null): ?object
    {
        $fecha ??= now()->toDateString();
        $puesto = DB::table('puesto as p')->join('cargo as c', 'c.carg_id_cargo', '=', 'p.pues_id_cargo')
            ->where('p.pues_id_institucion_educativa', $institucion)->where('p.pues_titularidad', true)
            ->first(['p.pues_id_puesto as id', 'p.pues_id_cargo as cargo_id', 'c.carg_nombre_m as cargo', 'c.carg_nombre_f as cargo_f']);
        if ($puesto === null) {
            return null;
        }

        $ocupante = DB::table('designacion as d')->join('persona as per', 'per.pers_id_persona', '=', 'd.desi_id_persona')
            ->where('d.desi_id_puesto', $puesto->id)
            ->where('d.desi_fecha_inicio', '<=', $fecha)
            ->where(fn ($q) => $q->whereNull('d.desi_fecha_fin')->orWhere('d.desi_fecha_fin', '>=', $fecha))
            ->first(['d.desi_id_designacion as designacion', 'd.desi_id_persona as persona', 'd.desi_fecha_inicio as desde', 'per.pers_genero as genero',
                DB::raw("concat_ws(' ', per.pers_nombre, per.pers_primer_apellido, per.pers_segundo_apellido) as nombre")]);

        return (object) [
            'puesto' => (int) $puesto->id,
            'cargo_id' => (int) $puesto->cargo_id,
            'cargo' => $ocupante?->genero === 'F' && filled($puesto->cargo_f) ? $puesto->cargo_f : $puesto->cargo,
            'designacion' => $ocupante ? (int) $ocupante->designacion : null,
            'persona' => $ocupante ? (int) $ocupante->persona : null,
            'ocupante' => $ocupante?->nombre,
            'desde' => $ocupante?->desde,
        ];
    }

    /**
     * Deja $puesto como titular de la institución desde $desde: si el titular
     * era otro puesto, ese deja de serlo (quien lo ocupa conserva su cargo);
     * si era este mismo y lo ocupaba otra persona, su designación termina el
     * día anterior. Queda como detalle del movimiento $idBitacora.
     */
    private function hacerTitular(?Usuario $actor, int $puesto, int $persona, int $institucion, string $desde, int $idBitacora): void
    {
        $actual = $this->titularDe($institucion, $desde);

        if ($actual !== null && $actual->puesto === $puesto) {
            if ($actual->designacion !== null && $actual->persona !== $persona) {
                if ($actor === null || ($error = $this->terminar($actor, $actual->designacion, $desde)) !== null) {
                    throw ValidationException::withMessages(['designaciones' => 'No se pudo terminar la designación del titular anterior: '.($error ?? 'falta quién lo hace').'.']);
                }
                // desactivar_designacion deja su propia línea en la bitácora (sin nombres en los detalles)
            }

            return;
        }

        if ($actual !== null) {
            DB::table('puesto')->where('pues_id_puesto', $actual->puesto)->update(['pues_titularidad' => false]);
        }
        DB::table('puesto')->where('pues_id_puesto', $puesto)->update(['pues_titularidad' => true]);
        $this->detalle($idBitacora, $puesto, 'Titular de la institución', $actual?->cargo,
            DB::table('puesto as p')->join('cargo as c', 'c.carg_id_cargo', '=', 'p.pues_id_cargo')->where('p.pues_id_puesto', $puesto)->value('c.carg_nombre_m'));
    }

    public function terminar(Usuario $actor, int $designacion, string $fecha): ?string
    {
        return FuncionSql::llamar('desactivar_designacion', [$actor->usua_id_usuario, $designacion, $fecha])[1];
    }

    /**
     * Corrige la fecha de inicio de una designación (un error de captura). La
     * base revisa sus reglas (puesto activo, pertenencia, que no se cruce con
     * otra persona en el mismo puesto). Devuelve el error en palabras o null.
     */
    public function corregirInicio(Usuario $actor, int $designacion, string $desde): ?string
    {
        $anterior = DB::table('designacion')->where('desi_id_designacion', $designacion)->value('desi_fecha_inicio');

        try {
            DB::table('designacion')->where('desi_id_designacion', $designacion)->update(['desi_fecha_inicio' => $desde]);
        } catch (QueryException $error) {
            return FuncionSql::mensaje($error);
        }

        $idBitacora = $this->bitacora($actor, 'Modificacion', 'DESIGNACION', $designacion);
        $this->detalle($idBitacora, $designacion, 'Fecha de inicio', $anterior, $desde);

        return null;
    }

    /**
     * Borra una designación capturada por error (nunca ocurrió). Devuelve el
     * error de la base en palabras, o null si se borró.
     */
    public function borrar(Usuario $actor, int $designacion, string $motivo): ?string
    {
        return FuncionSql::llamar('eliminar_designacion_erronea', [$actor->usua_id_usuario, $designacion, $motivo])[1];
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
}

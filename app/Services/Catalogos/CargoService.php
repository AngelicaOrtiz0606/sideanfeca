<?php

namespace App\Services\Catalogos;

use App\Models\Cargo;
use App\Models\Usuario;
use App\Services\Puestos\PuestoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de cargos. No hay funciones SQL para él: los cambios se hacen aquí
 * y la bitácora se registra en la misma transacción.
 *
 * - Si un cargo ya tiene puestos, su nivel no cambia (validar_puesto depende de él).
 * - Se borra (por ejemplo, por error de captura) si sus puestos nunca tuvieron
 *   personas designadas; esos puestos vacíos se borran con él. Si ya tiene
 *   historia, se desactiva.
 */
class CargoService
{
    use BitacoraCatalogo;

    public function __construct(private readonly PuestoService $servicioPuestos) {}

    /**
     * @return Collection<int, object>
     */
    public function listado(): Collection
    {
        return DB::table('cargo as c')
            ->select('c.carg_id_cargo', 'c.carg_nombre_m', 'c.carg_nombre_f', 'c.carg_nivel', 'c.carg_activo')
            ->selectSub(fn ($q) => $q->from('puesto')->whereColumn('pues_id_cargo', 'c.carg_id_cargo')->selectRaw('count(*)'), 'puestos')
            ->selectSub(fn ($q) => $q->from('designacion as d')->join('puesto as p', 'p.pues_id_puesto', '=', 'd.desi_id_puesto')
                ->whereColumn('p.pues_id_cargo', 'c.carg_id_cargo')->selectRaw('count(*)'), 'designaciones')
            ->orderByRaw("array_position(array['Nacional','Regional','Institucional']::varchar[], c.carg_nivel)")
            ->orderBy('c.carg_nombre_m')
            ->get()
            ->each(function (object $cargo): void {
                $cargo->motivo_para_no_borrar = $this->explicar((int) $cargo->designaciones);
                $cargo->texto_al_borrar = $this->textoAlBorrar((int) $cargo->puestos);
            });
    }

    public function puestos(Cargo $cargo): int
    {
        return DB::table('puesto')->where('pues_id_cargo', $cargo->carg_id_cargo)->count();
    }

    public function registrar(Usuario $actor, string $masculino, string $femenino, string $nivel): Cargo
    {
        return DB::transaction(function () use ($actor, $masculino, $femenino, $nivel): Cargo {
            $id = (int) DB::table('cargo')->insertGetId([
                'carg_nombre_m' => $masculino,
                'carg_nombre_f' => $femenino,
                'carg_nivel' => $nivel,
                'carg_activo' => true,
            ], 'carg_id_cargo');

            $this->bitacora($actor, 'Registro', 'CARGO', $id);

            return Cargo::findOrFail($id);
        });
    }

    public function actualizar(Usuario $actor, Cargo $cargo, string $masculino, string $femenino, string $nivel): void
    {
        DB::transaction(function () use ($actor, $cargo, $masculino, $femenino, $nivel): void {
            $actual = DB::table('cargo')->where('carg_id_cargo', $cargo->carg_id_cargo)->lockForUpdate()->first();

            $cambios = array_filter([
                'Nombre masculino' => $actual->carg_nombre_m !== $masculino ? [$actual->carg_nombre_m, $masculino] : null,
                'Nombre femenino' => $actual->carg_nombre_f !== $femenino ? [$actual->carg_nombre_f, $femenino] : null,
                'Nivel' => $actual->carg_nivel !== $nivel ? [$actual->carg_nivel, $nivel] : null,
            ]);

            if ($cambios === []) {
                return;
            }

            DB::table('cargo')->where('carg_id_cargo', $cargo->carg_id_cargo)->update([
                'carg_nombre_m' => $masculino,
                'carg_nombre_f' => $femenino,
                'carg_nivel' => $nivel,
            ]);

            $idBitacora = $this->bitacora($actor, 'Modificacion', 'CARGO', $cargo->carg_id_cargo);
            foreach ($cambios as $campo => [$anterior, $nuevo]) {
                $this->detalle($idBitacora, $cargo->carg_id_cargo, $campo, $anterior, $nuevo);
            }
        });
    }

    public function cambiarEstado(Usuario $actor, Cargo $cargo, bool $activo): void
    {
        DB::transaction(function () use ($actor, $cargo, $activo): void {
            $actual = (bool) DB::table('cargo')->where('carg_id_cargo', $cargo->carg_id_cargo)->lockForUpdate()->value('carg_activo');

            if ($actual === $activo) {
                return;
            }

            DB::table('cargo')->where('carg_id_cargo', $cargo->carg_id_cargo)->update(['carg_activo' => $activo]);
            $this->bitacora($actor, $activo ? 'Activacion' : 'Desactivacion', 'CARGO', $cargo->carg_id_cargo);
        });
    }

    /**
     * Por qué no se puede borrar, o null. Se puede borrar aunque tenga puestos,
     * siempre que ninguno haya tenido personas designadas (por ejemplo, un
     * cargo capturado por error): esos puestos vacíos se borran con él.
     */
    public function motivoParaNoBorrar(Cargo $cargo): ?string
    {
        return $this->explicar($this->designaciones($cargo));
    }

    /**
     * Borra el cargo y sus puestos vacíos (cada uno queda en la bitácora con el mismo motivo).
     */
    public function eliminar(Usuario $actor, Cargo $cargo, string $motivo): bool
    {
        return DB::transaction(function () use ($actor, $cargo, $motivo): bool {
            $actual = DB::table('cargo')->where('carg_id_cargo', $cargo->carg_id_cargo)->lockForUpdate()->first();

            if ($actual === null || $this->motivoParaNoBorrar($cargo) !== null) {
                return false;
            }

            $puestos = DB::table('puesto')->where('pues_id_cargo', $cargo->carg_id_cargo)->lockForUpdate()->pluck('pues_id_puesto');
            foreach ($puestos as $puesto) {
                // Si alguno no se pudo borrar, se deshace todo (no queda el cargo a medias)
                if (! $this->servicioPuestos->eliminar($actor, (int) $puesto, $motivo)) {
                    throw new \RuntimeException('Un puesto del cargo ya tiene designaciones.');
                }
            }

            $idBitacora = $this->bitacora($actor, 'Eliminacion', 'CARGO', $cargo->carg_id_cargo, $motivo);
            $this->detalle($idBitacora, $cargo->carg_id_cargo, 'Nombre masculino', $actual->carg_nombre_m, null);
            $this->detalle($idBitacora, $cargo->carg_id_cargo, 'Nombre femenino', $actual->carg_nombre_f, null);
            $this->detalle($idBitacora, $cargo->carg_id_cargo, 'Nivel', $actual->carg_nivel, null);
            if ($puestos->isNotEmpty()) {
                $this->detalle($idBitacora, $cargo->carg_id_cargo, 'Puestos vacíos borrados con el cargo', (string) $puestos->count(), null);
            }

            DB::table('cargo')->where('carg_id_cargo', $cargo->carg_id_cargo)->delete();

            return true;
        });
    }

    private function designaciones(Cargo $cargo): int
    {
        return DB::table('designacion as d')->join('puesto as p', 'p.pues_id_puesto', '=', 'd.desi_id_puesto')
            ->where('p.pues_id_cargo', $cargo->carg_id_cargo)->count();
    }

    /**
     * Qué pasa al borrar: si tiene puestos vacíos, se avisa que se borran con él.
     */
    private function textoAlBorrar(int $puestos): string
    {
        return match (true) {
            $puestos === 0 => 'No se puede deshacer. Queda en la bitácora con su motivo.',
            $puestos === 1 => 'También se borra su puesto, que nunca ha tenido personas. No se puede deshacer; queda en la bitácora con su motivo.',
            default => "También se borran sus {$puestos} puestos, que nunca han tenido personas. No se puede deshacer; queda en la bitácora con su motivo.",
        };
    }

    private function explicar(int $designaciones): ?string
    {
        return match (true) {
            $designaciones === 0 => null,
            $designaciones === 1 => 'No se puede borrar porque sus puestos ya tuvieron 1 designación. Si ya no se usa, desactívalo: su historia se conserva.',
            default => "No se puede borrar porque sus puestos ya tuvieron {$designaciones} designaciones. Si ya no se usa, desactívalo: su historia se conserva.",
        };
    }
}

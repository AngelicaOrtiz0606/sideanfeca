<?php

namespace App\Services\Catalogos;

use App\Models\Cargo;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de cargos. No hay funciones SQL para él: los cambios se hacen aquí
 * y la bitácora se registra en la misma transacción.
 *
 * - Si un cargo ya tiene puestos, su nivel no cambia (validar_puesto depende de él).
 * - Se borra (por ejemplo, por error de captura) solo si no tiene puestos. Si
 *   tiene puestos sin personas, se borran primero en Puestos; si ya tiene
 *   historia, se desactiva.
 */
class CargoService
{
    use BitacoraCatalogo;

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
                $cargo->motivo_para_no_borrar = $this->explicar((int) $cargo->designaciones, (int) $cargo->puestos);
                $cargo->texto_al_borrar = 'No se puede deshacer. Queda en la bitácora con su motivo.';
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
        return $this->explicar($this->designaciones($cargo), $this->puestos($cargo));
    }

    /**
     * Borra un cargo que no tiene puestos (si tiene, primero se borran o se
     * desactiva el cargo: así nunca se borran puestos de los directorios sin querer).
     */
    public function eliminar(Usuario $actor, Cargo $cargo, string $motivo): bool
    {
        return DB::transaction(function () use ($actor, $cargo, $motivo): bool {
            $actual = DB::table('cargo')->where('carg_id_cargo', $cargo->carg_id_cargo)->lockForUpdate()->first();

            if ($actual === null || $this->motivoParaNoBorrar($cargo) !== null) {
                return false;
            }

            $idBitacora = $this->bitacora($actor, 'Eliminacion', 'CARGO', $cargo->carg_id_cargo, $motivo);
            $this->detalle($idBitacora, $cargo->carg_id_cargo, 'Nombre masculino', $actual->carg_nombre_m, null);
            $this->detalle($idBitacora, $cargo->carg_id_cargo, 'Nombre femenino', $actual->carg_nombre_f, null);
            $this->detalle($idBitacora, $cargo->carg_id_cargo, 'Nivel', $actual->carg_nivel, null);

            DB::table('cargo')->where('carg_id_cargo', $cargo->carg_id_cargo)->delete();

            return true;
        });
    }

    private function designaciones(Cargo $cargo): int
    {
        return DB::table('designacion as d')->join('puesto as p', 'p.pues_id_puesto', '=', 'd.desi_id_puesto')
            ->where('p.pues_id_cargo', $cargo->carg_id_cargo)->count();
    }

    private function explicar(int $designaciones, int $puestos): ?string
    {
        return match (true) {
            $designaciones === 1 => 'No se puede borrar porque sus puestos ya tuvieron 1 designación. Si ya no se usa, desactívalo: su historia se conserva.',
            $designaciones > 1 => "No se puede borrar porque sus puestos ya tuvieron {$designaciones} designaciones. Si ya no se usa, desactívalo: su historia se conserva.",
            $puestos === 1 => 'No se puede borrar porque tiene 1 puesto. Si el puesto se capturó por error, bórralo primero en Puestos; si el cargo ya no se usa, desactívalo.',
            $puestos > 1 => "No se puede borrar porque tiene {$puestos} puestos. Si se capturaron por error, bórralos primero en Puestos; si el cargo ya no se usa, desactívalo.",
            default => null,
        };
    }
}

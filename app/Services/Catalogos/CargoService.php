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
 * - Solo se borra si no tiene puestos; si ya se usó, se desactiva.
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
            ->orderByRaw("array_position(array['Nacional','Regional','Institucional']::varchar[], c.carg_nivel)")
            ->orderBy('c.carg_nombre_m')
            ->get()
            ->each(function (object $cargo): void {
                $cargo->motivo_para_no_borrar = $this->explicar((int) $cargo->puestos);
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

    public function motivoParaNoBorrar(Cargo $cargo): ?string
    {
        return $this->explicar($this->puestos($cargo));
    }

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

    private function explicar(int $puestos): ?string
    {
        return match (true) {
            $puestos === 0 => null,
            $puestos === 1 => 'No se puede borrar porque tiene 1 puesto. Si ya no se usa, desactívalo: su historial se conserva.',
            default => "No se puede borrar porque tiene {$puestos} puestos. Si ya no se usa, desactívalo: su historial se conserva.",
        };
    }
}

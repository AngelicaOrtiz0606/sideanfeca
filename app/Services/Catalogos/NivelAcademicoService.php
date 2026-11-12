<?php

namespace App\Services\Catalogos;

use App\Models\NivelAcademico;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de grados académicos (tabla NIVEL_ACADEMICO). No hay funciones SQL para él: los cambios se
 * hacen aquí y la bitácora se registra en la misma transacción.
 *
 * - Solo se borra si ninguna persona lo tiene; si ya se usó, se desactiva.
 * - Ojo: "Grado académico" es un campo personal en la bitácora (no guarda
 *   valores), por eso aquí los campos se llaman "Nombre del grado" y "Abreviatura…".
 */
class NivelAcademicoService
{
    use BitacoraCatalogo;

    /**
     * @return Collection<int, object>
     */
    public function listado(): Collection
    {
        return DB::table('nivel_academico as n')
            ->select('n.niac_id_nivel_academico', 'n.niac_nombre', 'n.niac_abr_m', 'n.niac_abr_f', 'n.niac_activo')
            ->selectSub(fn ($q) => $q->from('persona_nivel_academico')->whereColumn('pena_id_nivel_academico', 'n.niac_id_nivel_academico')->selectRaw('count(*)'), 'personas')
            ->orderBy('n.niac_nombre')
            ->get()
            ->each(function (object $nivel): void {
                $nivel->motivo_para_no_borrar = $this->explicar((int) $nivel->personas);
            });
    }

    public function registrar(Usuario $actor, string $nombre, string $masculina, string $femenina): NivelAcademico
    {
        return DB::transaction(function () use ($actor, $nombre, $masculina, $femenina): NivelAcademico {
            $id = (int) DB::table('nivel_academico')->insertGetId([
                'niac_nombre' => $nombre,
                'niac_abr_m' => $masculina,
                'niac_abr_f' => $femenina,
                'niac_activo' => true,
            ], 'niac_id_nivel_academico');

            $this->bitacora($actor, 'Registro', 'NIVEL_ACADEMICO', $id);

            return NivelAcademico::findOrFail($id);
        });
    }

    public function actualizar(Usuario $actor, NivelAcademico $nivel, string $nombre, string $masculina, string $femenina): void
    {
        DB::transaction(function () use ($actor, $nivel, $nombre, $masculina, $femenina): void {
            $id = $nivel->niac_id_nivel_academico;
            $actual = DB::table('nivel_academico')->where('niac_id_nivel_academico', $id)->lockForUpdate()->first();

            $cambios = array_filter([
                'Nombre del grado' => $actual->niac_nombre !== $nombre ? [$actual->niac_nombre, $nombre] : null,
                'Abreviatura masculina' => $actual->niac_abr_m !== $masculina ? [$actual->niac_abr_m, $masculina] : null,
                'Abreviatura femenina' => $actual->niac_abr_f !== $femenina ? [$actual->niac_abr_f, $femenina] : null,
            ]);

            if ($cambios === []) {
                return;
            }

            DB::table('nivel_academico')->where('niac_id_nivel_academico', $id)->update([
                'niac_nombre' => $nombre,
                'niac_abr_m' => $masculina,
                'niac_abr_f' => $femenina,
            ]);

            $idBitacora = $this->bitacora($actor, 'Modificacion', 'NIVEL_ACADEMICO', $id);
            foreach ($cambios as $campo => [$anterior, $nuevo]) {
                $this->detalle($idBitacora, $id, $campo, $anterior, $nuevo);
            }
        });
    }

    public function cambiarEstado(Usuario $actor, NivelAcademico $nivel, bool $activo): void
    {
        DB::transaction(function () use ($actor, $nivel, $activo): void {
            $id = $nivel->niac_id_nivel_academico;
            $actual = (bool) DB::table('nivel_academico')->where('niac_id_nivel_academico', $id)->lockForUpdate()->value('niac_activo');

            if ($actual === $activo) {
                return;
            }

            DB::table('nivel_academico')->where('niac_id_nivel_academico', $id)->update(['niac_activo' => $activo]);
            $this->bitacora($actor, $activo ? 'Activacion' : 'Desactivacion', 'NIVEL_ACADEMICO', $id);
        });
    }

    public function motivoParaNoBorrar(NivelAcademico $nivel): ?string
    {
        return $this->explicar(DB::table('persona_nivel_academico')->where('pena_id_nivel_academico', $nivel->niac_id_nivel_academico)->count());
    }

    public function eliminar(Usuario $actor, NivelAcademico $nivel, string $motivo): bool
    {
        return DB::transaction(function () use ($actor, $nivel, $motivo): bool {
            $id = $nivel->niac_id_nivel_academico;
            $actual = DB::table('nivel_academico')->where('niac_id_nivel_academico', $id)->lockForUpdate()->first();

            if ($actual === null || $this->motivoParaNoBorrar($nivel) !== null) {
                return false;
            }

            $idBitacora = $this->bitacora($actor, 'Eliminacion', 'NIVEL_ACADEMICO', $id, $motivo);
            $this->detalle($idBitacora, $id, 'Nombre del grado', $actual->niac_nombre, null);

            DB::table('nivel_academico')->where('niac_id_nivel_academico', $id)->delete();

            return true;
        });
    }

    private function explicar(int $personas): ?string
    {
        return match (true) {
            $personas === 0 => null,
            $personas === 1 => 'No se puede borrar porque 1 persona tiene este grado. Si ya no se usa, desactívalo: su historial se conserva.',
            default => "No se puede borrar porque {$personas} personas tienen este grado. Si ya no se usa, desactívalo: su historial se conserva.",
        };
    }
}

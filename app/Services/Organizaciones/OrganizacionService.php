<?php

namespace App\Services\Organizaciones;

use App\Models\Usuario;
use App\Services\Catalogos\BitacoraCatalogo;
use App\Services\Instituciones\DomicilioService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Organizaciones que no son instituciones educativas (por ejemplo, CACECA):
 * nombre (sin repetirse), domicilio obligatorio y sitio web. No tienen
 * número de afiliación ni zona.
 *
 * - Se registran, editan, activan y desactivan desde la lista.
 * - Solo se borran si ninguna persona pertenece a ellas; si ya se usaron,
 *   se desactivan.
 * - Ojo: "Nombre" es un campo personal en la bitácora (no guarda valores),
 *   por eso aquí el campo se llama "Nombre de la organización".
 */
class OrganizacionService
{
    use BitacoraCatalogo;

    public function __construct(private readonly DomicilioService $domicilios) {}

    /**
     * Lista con su ubicación (municipio y entidad) y cuántas personas le pertenecen.
     *
     * @return Collection<int, object>
     */
    public function listado(): Collection
    {
        return DB::table('organizacion as o')
            ->join('domicilio as d', 'd.domi_id_domicilio', '=', 'o.orga_id_domicilio')
            ->join('colonia as c', 'c.colo_id_colonia', '=', 'd.domi_id_colonia')
            ->join('alcaldia_municipio as m', 'm.almu_id_alcaldia_municipio', '=', 'c.colo_id_alcaldia_municipio')
            ->join('entidad_federativa as e', 'e.enfe_id_entidad_federativa', '=', 'm.almu_id_entidad_federativa')
            ->select([
                'o.orga_id_organizacion as id',
                'o.orga_nombre as nombre',
                'o.orga_sitio_web as sitio_web',
                'o.orga_activo as activa',
                'o.orga_id_domicilio as domicilio_id',
                'd.domi_calle as calle',
                'd.domi_num_ext as num_ext',
                'd.domi_num_int as num_int',
                'c.colo_id_colonia as colonia',
                'c.colo_codigo_postal as codigo_postal',
                'm.almu_nombre as municipio',
                'e.enfe_nombre as entidad',
            ])
            ->selectSub(fn ($q) => $q->from('persona')->whereColumn('pers_id_organizacion', 'o.orga_id_organizacion')->selectRaw('count(*)'), 'personas')
            ->selectSub(fn ($q) => $q->from('puesto')->whereColumn('pues_id_organizacion', 'o.orga_id_organizacion')->selectRaw('count(*)'), 'puestos')
            ->orderBy('o.orga_nombre')
            ->get()
            ->each(function (object $organizacion): void {
                $organizacion->motivo_para_no_borrar = $this->explicar((int) $organizacion->personas, (int) $organizacion->puestos);
            });
    }

    public function buscar(int $id): ?object
    {
        return DB::table('organizacion')->where('orga_id_organizacion', $id)->first();
    }

    /**
     * @param  array{nombre: string, sitio_web: ?string, domicilio: array{colonia: int, calle: string, num_ext: string, num_int: ?string}}  $datos
     */
    public function registrar(Usuario $actor, array $datos): int
    {
        return DB::transaction(function () use ($actor, $datos): int {
            $domicilio = $this->domicilios->guardar(null, $datos['domicilio']);

            $id = (int) DB::table('organizacion')->insertGetId([
                'orga_nombre' => $datos['nombre'],
                'orga_id_domicilio' => $domicilio,
                'orga_sitio_web' => $datos['sitio_web'],
                'orga_activo' => true,
            ], 'orga_id_organizacion');

            $this->bitacora($actor, 'Registro', 'ORGANIZACION', $id);

            return $id;
        });
    }

    /**
     * @param  array{nombre: string, sitio_web: ?string, domicilio: array{colonia: int, calle: string, num_ext: string, num_int: ?string}}  $datos
     */
    public function actualizar(Usuario $actor, int $id, array $datos): void
    {
        DB::transaction(function () use ($actor, $id, $datos): void {
            $actual = DB::table('organizacion')->where('orga_id_organizacion', $id)->lockForUpdate()->first();
            $domicilioAntes = $this->domicilios->texto($actual->orga_id_domicilio);

            $this->domicilios->guardar($actual->orga_id_domicilio, $datos['domicilio']);
            DB::table('organizacion')->where('orga_id_organizacion', $id)->update([
                'orga_nombre' => $datos['nombre'],
                'orga_sitio_web' => $datos['sitio_web'],
            ]);

            $cambios = array_filter([
                'Nombre de la organización' => [$actual->orga_nombre, $datos['nombre']],
                'Sitio web' => [$actual->orga_sitio_web, $datos['sitio_web']],
                'Domicilio' => [$domicilioAntes, $this->domicilios->texto($actual->orga_id_domicilio)],
            ], fn (array $par) => $par[0] !== $par[1]);

            if ($cambios === []) {
                return;
            }

            $idBitacora = $this->bitacora($actor, 'Modificacion', 'ORGANIZACION', $id);
            foreach ($cambios as $campo => [$anterior, $nuevo]) {
                $this->detalle($idBitacora, $id, $campo, $anterior, $nuevo);
            }
        });
    }

    public function cambiarEstado(Usuario $actor, int $id, bool $activa): void
    {
        DB::transaction(function () use ($actor, $id, $activa): void {
            $actual = (bool) DB::table('organizacion')->where('orga_id_organizacion', $id)->lockForUpdate()->value('orga_activo');

            if ($actual === $activa) {
                return;
            }

            DB::table('organizacion')->where('orga_id_organizacion', $id)->update(['orga_activo' => $activa]);
            $this->bitacora($actor, $activa ? 'Activacion' : 'Desactivacion', 'ORGANIZACION', $id);
        });
    }

    public function motivoParaNoBorrar(int $id): ?string
    {
        return $this->explicar(DB::table('persona')->where('pers_id_organizacion', $id)->count(), DB::table('puesto')->where('pues_id_organizacion', $id)->count());
    }

    /**
     * Borra una organización sin personas, con su domicilio. Queda en la bitácora con su motivo.
     */
    public function eliminar(Usuario $actor, int $id, string $motivo): bool
    {
        return DB::transaction(function () use ($actor, $id, $motivo): bool {
            $actual = DB::table('organizacion')->where('orga_id_organizacion', $id)->lockForUpdate()->first();

            if ($actual === null || $this->motivoParaNoBorrar($id) !== null) {
                return false;
            }

            $idBitacora = $this->bitacora($actor, 'Eliminacion', 'ORGANIZACION', $id, $motivo);
            $this->detalle($idBitacora, $id, 'Nombre de la organización', $actual->orga_nombre, null);

            DB::table('organizacion')->where('orga_id_organizacion', $id)->delete();
            $this->domicilios->borrar($actual->orga_id_domicilio);

            return true;
        });
    }

    private function explicar(int $personas, int $puestos = 0): ?string
    {
        return match (true) {
            $personas === 0 && $puestos > 0 => 'No se puede borrar porque tiene '.($puestos === 1 ? 'un puesto' : "{$puestos} puestos")
                .' (por ejemplo, su Director General). Si ya no participa, desactívala: su historial se conserva.',
            $personas === 0 => null,
            $personas === 1 => 'No se puede borrar porque 1 persona pertenece a esta organización. Si ya no participa, desactívala: su historial se conserva.',
            default => "No se puede borrar porque {$personas} personas pertenecen a esta organización. Si ya no participa, desactívala: su historial se conserva.",
        };
    }
}

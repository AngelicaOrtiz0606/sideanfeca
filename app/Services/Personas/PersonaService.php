<?php

namespace App\Services\Personas;

use App\Models\Usuario;
use App\Services\Catalogos\BitacoraCatalogo;
use App\Support\Paginacion;
use App\Support\TextoCatalogo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Personas: datos personales (nombre, CURP opcional, género), pertenencia
 * (institución u organización, opcional), grados académicos y contactos.
 *
 * - Las personas ocultas (PERS_OCULTA_ADMIN) no aparecen aquí (M8).
 * - La bitácora no guarda valores personales: solo qué campo cambió
 *   (bitacora_detalle_registrar lo exige). Por eso los detalles de esta
 *   tabla van sin valor anterior ni nuevo.
 * - Con un cargo vigente, la pertenencia no se puede quitar, y si el cargo
 *   es institucional debe seguir siendo esa institución (validar_designacion
 *   lo exige al designar; aquí se cuida al editar).
 */
class PersonaService
{
    use BitacoraCatalogo;

    public const GENEROS = ['F' => 'Femenino', 'M' => 'Masculino'];

    /**
     * Lista con filtros: texto (nombre o CURP), grado, zona (de su institución) y situación.
     *
     * @param  array{texto?: ?string, grado?: ?int, zona?: ?int, situacion?: ?string}  $filtros
     */
    public function listado(array $filtros): LengthAwarePaginator
    {
        return $this->consulta()
            ->when($filtros['texto'] ?? null, function (Builder $q, string $texto) {
                // Sin importar mayúsculas ni acentos (la ñ cuenta como letra distinta)
                $parecido = '%'.addcslashes(TextoCatalogo::clave($texto), '%_\\').'%';
                $q->where(fn (Builder $w) => $w
                    ->whereRaw("translate(lower(concat_ws(' ', p.pers_nombre, p.pers_primer_apellido, p.pers_segundo_apellido)), 'áéíóúü', 'aeiouu') like ?", [$parecido])
                    ->orWhere('p.pers_curp', 'ilike', $parecido));
            })
            ->when($filtros['grado'] ?? null, fn (Builder $q, int $grado) => $q->whereExists(fn (Builder $e) => $e->from('persona_nivel_academico')
                ->whereColumn('pena_id_persona', 'p.pers_id_persona')->where('pena_id_nivel_academico', $grado)))
            ->when($filtros['zona'] ?? null, fn (Builder $q, int $zona) => $q->where('i.ined_id_zona_regional', $zona))
            ->when(($filtros['situacion'] ?? null) === 'con-cargo', fn (Builder $q) => $q->whereExists($this->vigentes()))
            ->when(($filtros['situacion'] ?? null) === 'sin-cargo', fn (Builder $q) => $q->whereNotExists($this->vigentes()))
            ->orderBy('p.pers_primer_apellido')->orderBy('p.pers_segundo_apellido')->orderBy('p.pers_nombre')
            ->paginate(Paginacion::POR_PAGINA)
            ->withQueryString()
            ->through(function (object $persona): object {
                $persona->cargos = $this->cargosVigentes((int) $persona->id, $persona->genero);

                return $persona;
            });
    }

    public function buscar(int $id): ?object
    {
        $persona = $this->consulta()->where('p.pers_id_persona', $id)->first();

        if ($persona !== null) {
            $persona->grados = DB::table('persona_nivel_academico as pn')
                ->join('nivel_academico as n', 'n.niac_id_nivel_academico', '=', 'pn.pena_id_nivel_academico')
                ->where('pn.pena_id_persona', $id)
                ->orderBy('n.niac_nombre')
                ->get(['n.niac_id_nivel_academico as id', 'n.niac_nombre as nombre', 'n.niac_abr_m as abr_m', 'n.niac_abr_f as abr_f']);
            $persona->cargos = $this->cargosVigentes($id, $persona->genero);
            $persona->tiene_cuenta = DB::table('usuario')->where('usua_id_persona', $id)->exists();
        }

        return $persona;
    }

    /**
     * Contactos con su tipo (y qué permite: lada, extensión).
     *
     * @return Collection<int, object>
     */
    public function contactos(int $persona): Collection
    {
        return DB::table('contacto as c')
            ->join('tipo_contacto as t', 't.tico_id_tipo_contacto', '=', 'c.cont_id_tipo_contacto')
            ->where('c.cont_id_persona', $persona)
            ->orderBy('t.tico_id_tipo_contacto')->orderBy('c.cont_id_contacto')
            ->get(['c.cont_id_contacto as id', 'c.cont_id_tipo_contacto as tipo_id', 't.tico_nombre as tipo', 'c.cont_valor as valor',
                'c.cont_lada as lada', 'c.cont_extension as extension', 'c.cont_visible as visible']);
    }

    /**
     * Sus contactos como los pide el formulario: el correo y el teléfono
     * obligatorios (los primeros de su tipo) y, aparte, los demás.
     *
     * @return array{correo: ?object, telefono: ?object, otros: Collection<int, object>}
     */
    public function contactosFormulario(int $persona): array
    {
        $contactos = $this->contactos($persona);
        $correo = $contactos->firstWhere('tipo', 'Correo');
        $telefono = $contactos->firstWhere('tipo', 'Teléfono');

        return [
            'correo' => $correo,
            'telefono' => $telefono,
            'otros' => $contactos->reject(fn (object $c) => $c === $correo || $c === $telefono)->values(),
        ];
    }

    /**
     * Todos sus cargos (vigentes, pasados y futuros), del más reciente al más antiguo.
     *
     * @return Collection<int, object>
     */
    public function historialCargos(int $persona, string $genero): Collection
    {
        return $this->designaciones()
            ->where('d.desi_id_persona', $persona)
            ->orderByDesc('d.desi_fecha_inicio')
            ->get()
            ->each(fn (object $d) => $d->nombre = $this->nombreCargo($d, $genero));
    }

    /**
     * @return Collection<int, object>
     */
    public function tiposContacto(): Collection
    {
        return DB::table('tipo_contacto')->orderBy('tico_id_tipo_contacto')
            ->get(['tico_id_tipo_contacto as id', 'tico_nombre as nombre', 'tico_permite_lada as lada', 'tico_permite_extension as extension']);
    }

    /**
     * Grados para elegir: los activos más los que la persona ya tiene.
     *
     * @return Collection<int, object>
     */
    public function grados(?int $persona = null): Collection
    {
        return DB::table('nivel_academico as n')
            ->where(fn (Builder $q) => $q->where('n.niac_activo', true)
                ->when($persona, fn (Builder $w) => $w->orWhereExists(fn (Builder $e) => $e->from('persona_nivel_academico')
                    ->whereColumn('pena_id_nivel_academico', 'n.niac_id_nivel_academico')->where('pena_id_persona', $persona))))
            ->orderBy('n.niac_nombre')
            ->get(['n.niac_id_nivel_academico as id', 'n.niac_nombre as nombre', 'n.niac_abr_m as abr_m', 'n.niac_abr_f as abr_f']);
    }

    /**
     * Organizaciones activas para la pertenencia (más la actual, aunque esté desactivada).
     *
     * @return Collection<int, object>
     */
    public function organizaciones(?int $actual = null): Collection
    {
        return DB::table('organizacion')
            ->where(fn (Builder $q) => $q->where('orga_activo', true)->when($actual, fn (Builder $w, int $id) => $w->orWhere('orga_id_organizacion', $id)))
            ->orderBy('orga_nombre')
            ->get(['orga_id_organizacion as id', 'orga_nombre as nombre']);
    }

    /**
     * Por qué no se puede cambiar la pertenencia así, o null.
     */
    public function motivoParaNoCambiarPertenencia(int $persona, ?int $institucion, ?int $organizacion): ?string
    {
        $vigentes = $this->designaciones()->where('d.desi_id_persona', $persona)
            ->where('d.desi_fecha_inicio', '<=', DB::raw('current_date'))
            ->where(fn (Builder $q) => $q->whereNull('d.desi_fecha_fin')->orWhere('d.desi_fecha_fin', '>=', DB::raw('current_date')))
            ->get();

        if ($vigentes->isEmpty()) {
            return null;
        }

        $institucional = $vigentes->firstWhere('nivel', 'Institucional');
        if ($institucional !== null && (int) $institucional->institucion_id !== $institucion) {
            return "Tiene el cargo vigente «{$institucional->cargo} · {$institucional->institucion}», así que debe seguir perteneciendo a esa institución. Si ya no está ahí, primero registra que deja el cargo.";
        }

        if ($institucion === null && $organizacion === null) {
            return 'Tiene un cargo vigente, y para tenerlo debe pertenecer a una institución u organización.';
        }

        return null;
    }

    /**
     * Guarda el formulario completo (datos personales, pertenencia, grados y
     * contactos) en una sola transacción. La bitácora registra qué cambió,
     * nunca el valor personal.
     *
     * @param  array{nombre: string, primer_apellido: string, segundo_apellido: ?string, curp: ?string, genero: string, institucion: ?int, organizacion: ?int, grados: array<int, int>, contactos: array<int, array{tipo: int, valor: string, lada: ?string, extension: ?string, visible: bool}>}  $datos
     */
    public function actualizar(Usuario $actor, int $id, array $datos): void
    {
        DB::transaction(function () use ($actor, $id, $datos): void {
            $actual = DB::table('persona')->where('pers_id_persona', $id)->lockForUpdate()->first();
            $cambios = [];

            // Datos personales y pertenencia
            $nuevos = [
                'pers_nombre' => $datos['nombre'],
                'pers_primer_apellido' => $datos['primer_apellido'],
                'pers_segundo_apellido' => $datos['segundo_apellido'],
                'pers_curp' => $datos['curp'],
                'pers_genero' => $datos['genero'],
                'pers_id_institucion_educativa' => $datos['institucion'],
                'pers_id_organizacion' => $datos['organizacion'],
            ];
            $campos = [
                'pers_nombre' => 'Nombre', 'pers_primer_apellido' => 'Primer apellido', 'pers_segundo_apellido' => 'Segundo apellido',
                'pers_curp' => 'CURP', 'pers_genero' => 'Género', 'pers_id_institucion_educativa' => 'Pertenencia', 'pers_id_organizacion' => 'Pertenencia',
            ];
            foreach ($nuevos as $columna => $valor) {
                if ((string) $actual->{$columna} !== (string) $valor) {
                    $cambios[] = $campos[$columna];
                }
            }
            DB::table('persona')->where('pers_id_persona', $id)->update($nuevos);

            // Grados
            $antes = DB::table('persona_nivel_academico')->where('pena_id_persona', $id)->pluck('pena_id_nivel_academico')->map(fn ($g) => (int) $g)->sort()->values()->all();
            $despues = collect($datos['grados'])->unique()->sort()->values()->all();
            if ($antes !== $despues) {
                DB::table('persona_nivel_academico')->where('pena_id_persona', $id)->delete();
                DB::table('persona_nivel_academico')->insert(array_map(fn (int $g) => ['pena_id_persona' => $id, 'pena_id_nivel_academico' => $g], $despues));
                $cambios[] = 'Grado académico';
            }

            // Contactos: si cambiaron, se reemplazan (la bitácora dice si fueron correos u otros).
            // El primero de cada tipo es el obligatorio: cuenta cuál es; el orden de los demás, no.
            $correo = $this->idTipoCorreo();
            $firma = function (iterable $lista, bool $correos) use ($correo): array {
                $texto = fn ($c) => implode('|', [$c['tipo'], $c['valor'], $c['lada'] ?? '', $c['extension'] ?? '', $c['visible'] ? 1 : 0]);
                $grupo = collect($lista)->filter(fn ($c) => ((int) $c['tipo'] === $correo) === $correos)->values();
                $primero = $grupo->first();

                return [$primero === null ? '' : $texto($primero), ...$grupo->slice(1)->map($texto)->sort()->values()->all()];
            };
            $previos = DB::table('contacto')->where('cont_id_persona', $id)->orderBy('cont_id_contacto')->get()
                ->map(fn ($c) => ['tipo' => $c->cont_id_tipo_contacto, 'valor' => $c->cont_valor, 'lada' => $c->cont_lada, 'extension' => $c->cont_extension, 'visible' => (bool) $c->cont_visible]);
            $cambioCorreo = $firma($previos, true) !== $firma($datos['contactos'], true);
            $cambioOtros = $firma($previos, false) !== $firma($datos['contactos'], false);
            if ($cambioCorreo || $cambioOtros) {
                DB::table('contacto')->where('cont_id_persona', $id)->delete();
                DB::table('contacto')->insert(array_map(fn (array $c) => [
                    'cont_id_persona' => $id,
                    'cont_id_tipo_contacto' => $c['tipo'],
                    'cont_valor' => $c['valor'],
                    'cont_lada' => $c['lada'],
                    'cont_extension' => $c['extension'],
                    'cont_visible' => $c['visible'],
                ], $datos['contactos']));
                if ($cambioCorreo) {
                    $cambios[] = 'Correo';
                }
                if ($cambioOtros) {
                    $cambios[] = 'Contacto';
                }
            }

            $cambios = array_values(array_unique($cambios));
            if ($cambios === []) {
                return;
            }

            $idBitacora = $this->bitacora($actor, 'Modificacion', 'PERSONA', $id);
            foreach ($cambios as $campo) {
                $this->detalle($idBitacora, $id, $campo, null, null);
            }
        });
    }

    /**
     * Nombre completo, como se escribe.
     */
    public function nombreCompleto(object $persona): string
    {
        return trim("{$persona->nombre} {$persona->primer_apellido} ".($persona->segundo_apellido ?? ''));
    }

    /**
     * Cargos vigentes hoy, en el género de la persona, con su ámbito.
     *
     * @return Collection<int, string>
     */
    private function cargosVigentes(int $persona, string $genero): Collection
    {
        return $this->designaciones()
            ->where('d.desi_id_persona', $persona)
            ->where('d.desi_fecha_inicio', '<=', DB::raw('current_date'))
            ->where(fn (Builder $q) => $q->whereNull('d.desi_fecha_fin')->orWhere('d.desi_fecha_fin', '>=', DB::raw('current_date')))
            ->orderBy('d.desi_fecha_inicio')
            ->get()
            ->map(fn (object $d) => $this->nombreCargo($d, $genero));
    }

    private function nombreCargo(object $designacion, string $genero): string
    {
        $cargo = $genero === 'F' ? $designacion->cargo_f : $designacion->cargo;
        $ambito = match ($designacion->nivel) {
            'Institucional' => $designacion->institucion,
            'Regional' => trim("Zona {$designacion->zona_numero} ".($designacion->coordinacion ? "· {$designacion->coordinacion}" : '')),
            default => $designacion->coordinacion,
        };

        return $ambito ? "{$cargo} · {$ambito}" : $cargo;
    }

    private function designaciones(): Builder
    {
        return DB::table('designacion as d')
            ->join('puesto as pu', 'pu.pues_id_puesto', '=', 'd.desi_id_puesto')
            ->join('cargo as c', 'c.carg_id_cargo', '=', 'pu.pues_id_cargo')
            ->leftJoin('zona_regional as z', 'z.zore_id_zona_regional', '=', 'pu.pues_id_zona_regional')
            ->leftJoin('coordinacion_nacional as cn', 'cn.cona_id_coordinacion_nacional', '=', 'pu.pues_id_coordinacion_nacional')
            ->leftJoin('institucion_nombre as n', fn ($j) => $j->on('n.instn_id_institucion', '=', 'pu.pues_id_institucion_educativa')->whereNull('n.instn_fecha_fin'))
            ->select([
                'd.desi_id_designacion as id', 'd.desi_fecha_inicio as desde', 'd.desi_fecha_fin as hasta',
                'c.carg_nombre_m as cargo', 'c.carg_nombre_f as cargo_f', 'c.carg_nivel as nivel',
                'z.zore_numero as zona_numero', 'cn.cona_nombre as coordinacion',
                'pu.pues_id_institucion_educativa as institucion_id', 'n.instn_nombre as institucion',
            ]);
    }

    private function vigentes(): \Closure
    {
        return fn (Builder $e) => $e->from('designacion as dv')
            ->whereColumn('dv.desi_id_persona', 'p.pers_id_persona')
            ->where('dv.desi_fecha_inicio', '<=', DB::raw('current_date'))
            ->where(fn (Builder $q) => $q->whereNull('dv.desi_fecha_fin')->orWhere('dv.desi_fecha_fin', '>=', DB::raw('current_date')));
    }

    private function consulta(): Builder
    {
        return DB::table('persona as p')
            ->leftJoin('institucion_educativa as i', 'i.ined_id_institucion_educativa', '=', 'p.pers_id_institucion_educativa')
            ->leftJoin('institucion_nombre as n', fn ($j) => $j->on('n.instn_id_institucion', '=', 'i.ined_id_institucion_educativa')->whereNull('n.instn_fecha_fin'))
            ->leftJoin('zona_regional as z', 'z.zore_id_zona_regional', '=', 'i.ined_id_zona_regional')
            ->leftJoin('organizacion as o', 'o.orga_id_organizacion', '=', 'p.pers_id_organizacion')
            ->where('p.pers_oculta_admin', false)
            ->select([
                'p.pers_id_persona as id',
                'p.pers_nombre as nombre',
                'p.pers_primer_apellido as primer_apellido',
                'p.pers_segundo_apellido as segundo_apellido',
                'p.pers_curp as curp',
                'p.pers_genero as genero',
                'p.pers_id_institucion_educativa as institucion_id',
                'n.instn_nombre as institucion',
                'z.zore_numero as zona_numero',
                'z.zore_nombre as zona_nombre',
                'p.pers_id_organizacion as organizacion_id',
                'o.orga_nombre as organizacion',
            ]);
    }

    private function idTipoCorreo(): int
    {
        return (int) DB::table('tipo_contacto')->where('tico_nombre', 'Correo')->value('tico_id_tipo_contacto');
    }
}

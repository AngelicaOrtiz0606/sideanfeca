<?php

namespace App\Services\Personas;

use App\Models\Usuario;
use App\Services\Catalogos\BitacoraCatalogo;
use App\Services\Designaciones\DesignacionService;
use App\Support\Paginacion;
use App\Support\TextoCatalogo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Personas: datos personales (nombre, CURP opcional, género), pertenencia
 * (institución u organización, opcional), grados académicos y contactos.
 *
 * - Las personas ocultas (PERS_OCULTA_ADMIN) no aparecen aquí (M8).
 * - Aquí solo están las personas de los directorios (con al menos una
 *   designación, vigente o pasada). Quien solo tiene cuenta va en Usuarios.
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
     * Lista con filtros:
     * - texto: nombre, CURP, correo, su institución u organización, o un cargo vigente;
     * - cargo: uno vigente;
     * - zona: la de su institución o la de un cargo vigente;
     * - situación (en pantalla, "Estado"): con o sin cargo vigente.
     *
     * @param  array{texto?: ?string, cargo?: ?int, zona?: ?int, situacion?: ?string}  $filtros
     */
    public function listado(array $filtros): LengthAwarePaginator
    {
        return $this->consulta()
            ->when($filtros['texto'] ?? null, function (Builder $q, string $texto) {
                // Sin importar mayúsculas ni acentos (la ñ cuenta como letra distinta)
                $parecido = '%'.addcslashes(TextoCatalogo::clave($texto), '%_\\').'%';
                $sinAcentos = fn (string $columna) => "translate(lower({$columna}), 'áéíóúü', 'aeiouu') like ?";
                $q->where(fn (Builder $w) => $w
                    ->whereRaw($sinAcentos("concat_ws(' ', p.pers_nombre, p.pers_primer_apellido, p.pers_segundo_apellido)"), [$parecido])
                    ->orWhere('p.pers_curp', 'ilike', $parecido)
                    ->orWhereRaw($sinAcentos('n.instn_nombre'), [$parecido])
                    ->orWhereRaw($sinAcentos('o.orga_nombre'), [$parecido])
                    ->orWhereExists(fn (Builder $e) => $e->from('contacto as ct')
                        ->join('tipo_contacto as tc', 'tc.tico_id_tipo_contacto', '=', 'ct.cont_id_tipo_contacto')
                        ->whereColumn('ct.cont_id_persona', 'p.pers_id_persona')->where('tc.tico_nombre', 'Correo')
                        ->where('ct.cont_valor', 'ilike', $parecido))
                    ->orWhereExists($this->cargoVigente(fn (Builder $c) => $c->where(fn (Builder $n) => $n
                        ->whereRaw($sinAcentos('cv.carg_nombre_m'), [$parecido])->orWhereRaw($sinAcentos('cv.carg_nombre_f'), [$parecido])))));
            })
            ->when($filtros['cargo'] ?? null, fn (Builder $q, int $cargo) => $q->whereExists($this->cargoVigente(fn (Builder $c) => $c->where('pv.pues_id_cargo', $cargo))))
            ->when($filtros['zona'] ?? null, fn (Builder $q, int $zona) => $q->where(fn (Builder $w) => $w
                ->where('i.ined_id_zona_regional', $zona)
                ->orWhereExists($this->cargoVigente(fn (Builder $c) => $c->where('pv.pues_id_zona_regional', $zona)))))
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
    public function actualizar(Usuario $actor, int $id, array $datos, array $designaciones = ['borrar' => [], 'inicio' => [], 'terminar' => []], array $nuevas = []): void
    {
        DB::transaction(function () use ($actor, $id, $datos, $designaciones, $nuevas): void {
            // Datos y designaciones nuevas: un solo movimiento en la bitácora
            $cambios = $this->guardarDatos($id, $datos);
            if ($cambios !== [] || $nuevas !== []) {
                $idBitacora = $this->bitacora($actor, 'Modificacion', 'PERSONA', $id);
                foreach ($cambios as $campo) {
                    $this->detalle($idBitacora, $id, $campo, null, null);
                }
                app(DesignacionService::class)->designar($id, $datos['genero'], $nuevas, $idBitacora);
            }

            // Las guardadas que se borran o terminan: con las funciones de la base (cada una deja su bitácora).
            // Si la base rechaza algo, no se guarda nada y se avisa en el formulario.
            $servicio = app(DesignacionService::class);
            foreach ($designaciones['borrar'] as $designacion => $motivo) {
                if (($error = $servicio->borrar($actor, $designacion, $motivo)) !== null) {
                    throw ValidationException::withMessages(["existentes.{$designacion}.borrar" => $error]);
                }
            }
            // Corregir la fecha de inicio (un error de captura): no hay función de la base para esto;
            // se actualiza aquí y se registra en la bitácora con sus fechas
            foreach ($designaciones['inicio'] ?? [] as $designacion => $desde) {
                if (($error = $servicio->corregirInicio($actor, $designacion, $desde)) !== null) {
                    throw ValidationException::withMessages(["existentes.{$designacion}.desde" => $error]);
                }
            }
            foreach ($designaciones['terminar'] as $designacion => $hasta) {
                // La base recibe el primer día sin el puesto
                if (($error = $servicio->terminar($actor, $designacion, Carbon::parse($hasta)->addDay()->toDateString())) !== null) {
                    throw ValidationException::withMessages(["existentes.{$designacion}.hasta" => $error]);
                }
            }
        });
    }

    /**
     * Guarda datos personales, pertenencia, grados y contactos, y dice qué
     * campos cambiaron (sin valores: son datos personales). No escribe en la
     * bitácora: quien la llama decide en qué movimiento van.
     *
     * @param  array<string, mixed>  $datos
     * @return array<int, string>
     */
    private function guardarDatos(int $id, array $datos): array
    {
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

        return array_values(array_unique($cambios));
    }

    /**
     * Quien solo tiene cuenta (aún no está en los directorios), para registrarle
     * su primer cargo con los datos que ya tiene. Null si no existe, está oculta
     * o ya está en los directorios.
     */
    public function buscarSoloCuenta(int $id): ?object
    {
        $persona = $this->consulta(false)->where('p.pers_id_persona', $id)->whereNotExists($this->enDirectorio())->first();
        if ($persona !== null) {
            $persona->grados = DB::table('persona_nivel_academico')->where('pena_id_persona', $id)->get(['pena_id_nivel_academico as id']);
        }

        return $persona;
    }

    /**
     * Personas que se parecen a lo que se escribe (nombre completo o CURP),
     * para no registrar a alguien dos veces. Incluye a quien solo tiene cuenta.
     *
     * @return Collection<int, object>
     */
    public function coincidencias(string $nombre, string $curp): Collection
    {
        $clave = TextoCatalogo::clave($nombre);
        if (mb_strlen($clave) < 5 && strlen($curp) !== 18) {
            return collect();
        }

        return $this->consulta(false)
            ->where(fn (Builder $q) => $q
                ->when(mb_strlen($clave) >= 5, fn (Builder $w) => $w->orWhereRaw(
                    "translate(lower(concat_ws(' ', p.pers_nombre, p.pers_primer_apellido, p.pers_segundo_apellido)), 'áéíóúü', 'aeiouu') like ?",
                    [addcslashes($clave, '%_\\').'%']))
                ->when(strlen($curp) === 18, fn (Builder $w) => $w->orWhere('p.pers_curp', $curp)))
            ->orderBy('p.pers_primer_apellido')
            ->limit(5)
            ->get()
            ->map(fn (object $p) => (object) [
                'id' => (int) $p->id,
                'nombre' => $this->nombreCompleto($p),
                'misma_curp' => strlen($curp) === 18 && $p->curp === $curp,
                'en_directorio' => DB::table('designacion')->where('desi_id_persona', $p->id)->exists(),
                'tiene_cuenta' => DB::table('usuario')->where('usua_id_persona', $p->id)->exists(),
            ]);
    }

    /**
     * Registra a una persona con sus cargos, todo junto: sus datos,
     * pertenencia, grados y contactos; y por cada cargo, el puesto (si aún no
     * existe, se crea con el cargo y su ámbito) y la designación. Si ya tenía cuenta
     * ($existente), se usan y actualizan sus datos en lugar de duplicarla.
     *
     * @param  array{nombre: string, primer_apellido: string, segundo_apellido: ?string, curp: ?string, genero: string, institucion: ?int, organizacion: ?int, grados: array<int, int>, contactos: array<int, array<string, mixed>>, designaciones: array<int, array{cargo: int, zona: ?int, coordinacion: ?int, institucion: ?int, fecha_inicio: string}>}  $datos
     */
    public function registrar(Usuario $actor, array $datos, ?int $existente = null): int
    {
        return DB::transaction(function () use ($actor, $datos, $existente): int {
            // Un solo movimiento en la bitácora: la persona (registrada, o modificada si
            // ya tenía cuenta) y, como detalles, sus designaciones y los puestos creados
            if ($existente !== null) {
                $id = $existente;
                $cambios = $this->guardarDatos($id, $datos);
                $idBitacora = $this->bitacora($actor, 'Modificacion', 'PERSONA', $id);
                foreach ($cambios as $campo) {
                    $this->detalle($idBitacora, $id, $campo, null, null);
                }
            } else {
                $id = (int) DB::table('persona')->insertGetId([
                    'pers_nombre' => $datos['nombre'],
                    'pers_primer_apellido' => $datos['primer_apellido'],
                    'pers_segundo_apellido' => $datos['segundo_apellido'],
                    'pers_curp' => $datos['curp'],
                    'pers_genero' => $datos['genero'],
                    'pers_id_institucion_educativa' => $datos['institucion'],
                    'pers_id_organizacion' => $datos['organizacion'],
                ], 'pers_id_persona');
                DB::table('persona_nivel_academico')->insert(array_map(fn (int $g) => ['pena_id_persona' => $id, 'pena_id_nivel_academico' => $g], $datos['grados']));
                DB::table('contacto')->insert(array_map(fn (array $c) => [
                    'cont_id_persona' => $id,
                    'cont_id_tipo_contacto' => $c['tipo'],
                    'cont_valor' => $c['valor'],
                    'cont_lada' => $c['lada'],
                    'cont_extension' => $c['extension'],
                    'cont_visible' => $c['visible'],
                ], $datos['contactos']));
                $idBitacora = $this->bitacora($actor, 'Registro', 'PERSONA', $id);
            }

            app(DesignacionService::class)->designar($id, $datos['genero'], $datos['designaciones'], $idBitacora);

            return $id;
        });
    }

    /**
     * El puesto con ese cargo y ese ámbito, si ya existe.
     */
    public function puestoDe(int $cargo, ?int $zona, ?int $coordinacion, ?int $institucion): ?int
    {
        $id = DB::table('puesto')
            ->where('pues_id_cargo', $cargo)
            ->where(fn (Builder $q) => $zona === null ? $q->whereNull('pues_id_zona_regional') : $q->where('pues_id_zona_regional', $zona))
            ->where(fn (Builder $q) => $coordinacion === null ? $q->whereNull('pues_id_coordinacion_nacional') : $q->where('pues_id_coordinacion_nacional', $coordinacion))
            ->where(fn (Builder $q) => $institucion === null ? $q->whereNull('pues_id_institucion_educativa') : $q->where('pues_id_institucion_educativa', $institucion))
            ->value('pues_id_puesto');

        return $id === null ? null : (int) $id;
    }

    /**
     * Si el puesto está ocupado en algún día del periodo (sin fin: desde $inicio en adelante),
     * quién lo ocupa. $excepto: una designación que no cuenta (la que se está corrigiendo).
     */
    public function ocupanteEntre(int $puesto, string $inicio, ?string $fin = null, ?int $excepto = null): ?string
    {
        $ocupante = DB::table('designacion as d')
            ->join('persona as p', 'p.pers_id_persona', '=', 'd.desi_id_persona')
            ->where('d.desi_id_puesto', $puesto)
            ->when($excepto, fn (Builder $q, int $id) => $q->where('d.desi_id_designacion', '!=', $id))
            ->where(fn (Builder $q) => $q->whereNull('d.desi_fecha_fin')->orWhere('d.desi_fecha_fin', '>=', $inicio))
            ->when($fin, fn (Builder $q, string $hasta) => $q->where('d.desi_fecha_inicio', '<=', $hasta))
            ->orderBy('d.desi_fecha_inicio')
            ->first(['p.pers_nombre as nombre', 'p.pers_primer_apellido as primer_apellido', 'p.pers_segundo_apellido as segundo_apellido', 'p.pers_oculta_admin as oculta']);

        return $ocupante === null ? null : ($ocupante->oculta ? 'otra persona' : $this->nombreCompleto($ocupante));
    }

    /**
     * Si no se puede borrar a la persona, por qué (se muestra en lugar del diálogo).
     */
    public function motivoParaNoBorrar(Usuario $actor, object $persona): ?string
    {
        if ((int) $actor->usua_id_persona === (int) $persona->id) {
            return 'Eres tú: no puedes borrar tu propia persona.';
        }
        if ($persona->cargos->isNotEmpty()) {
            return 'Tiene una designación vigente. Primero termínala (en su ficha, pestaña Designaciones).';
        }

        return null;
    }

    /**
     * Qué pasará al borrarla: sin cuenta se elimina por completo; con cuenta solo se oculta.
     */
    public function efectoDeBorrar(object $persona): string
    {
        return $persona->tiene_cuenta
            ? 'Tiene cuenta de usuario: no se borra, se oculta. Deja de aparecer en Personas y un superadministrador puede volver a mostrarla.'
            : 'Se borra por completo: sus datos, contactos, grados y designaciones. No se puede deshacer.';
    }

    /**
     * Personas ocultas (las borró un administrador y tienen cuenta). Solo las ve el superadministrador.
     *
     * @return Collection<int, object>
     */
    public function ocultas(): Collection
    {
        return DB::table('persona as p')
            ->leftJoin('usuario as u', 'u.usua_id_persona', '=', 'p.pers_id_persona')
            ->where('p.pers_oculta_admin', true)
            ->orderBy('p.pers_primer_apellido')->orderBy('p.pers_nombre')
            ->get(['p.pers_id_persona as id', 'p.pers_nombre as nombre', 'p.pers_primer_apellido as primer_apellido',
                'p.pers_segundo_apellido as segundo_apellido', 'u.usua_correo as correo', 'u.usua_activo as cuenta_activa'])
            ->each(fn (object $p) => $p->nombre_completo = $this->nombreCompleto($p));
    }

    /**
     * Una persona sin importar si está oculta o en los directorios (para el superadministrador:
     * mostrarla, borrarla definitivamente o atender una cancelación ARCO).
     */
    public function cualquiera(int $id): ?object
    {
        $persona = DB::table('persona')->where('pers_id_persona', $id)
            ->first(['pers_id_persona as id', 'pers_nombre as nombre', 'pers_primer_apellido as primer_apellido', 'pers_segundo_apellido as segundo_apellido', 'pers_oculta_admin as oculta']);
        if ($persona !== null) {
            $persona->nombre_completo = $this->nombreCompleto($persona);
            $persona->tiene_cuenta = DB::table('usuario')->where('usua_id_persona', $id)->exists();
        }

        return $persona;
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

    /**
     * Tiene un cargo vigente hoy que cumple $condicion (sobre dv, pv: puesto y cv: cargo).
     */
    private function cargoVigente(\Closure $condicion): \Closure
    {
        return function (Builder $e) use ($condicion): void {
            $this->vigentes()($e);
            $e->join('puesto as pv', 'pv.pues_id_puesto', '=', 'dv.desi_id_puesto')
                ->join('cargo as cv', 'cv.carg_id_cargo', '=', 'pv.pues_id_cargo');
            $condicion($e);
        };
    }

    private function vigentes(): \Closure
    {
        return fn (Builder $e) => $e->from('designacion as dv')
            ->whereColumn('dv.desi_id_persona', 'p.pers_id_persona')
            ->where('dv.desi_fecha_inicio', '<=', DB::raw('current_date'))
            ->where(fn (Builder $q) => $q->whereNull('dv.desi_fecha_fin')->orWhere('dv.desi_fecha_fin', '>=', DB::raw('current_date')));
    }

    /**
     * @param  bool  $soloDirectorio  false: también quien solo tiene cuenta (para recuperarla al registrar)
     */
    private function consulta(bool $soloDirectorio = true): Builder
    {
        return DB::table('persona as p')
            ->leftJoin('institucion_educativa as i', 'i.ined_id_institucion_educativa', '=', 'p.pers_id_institucion_educativa')
            ->leftJoin('institucion_nombre as n', fn ($j) => $j->on('n.instn_id_institucion', '=', 'i.ined_id_institucion_educativa')->whereNull('n.instn_fecha_fin'))
            ->leftJoin('zona_regional as z', 'z.zore_id_zona_regional', '=', 'i.ined_id_zona_regional')
            ->leftJoin('organizacion as o', 'o.orga_id_organizacion', '=', 'p.pers_id_organizacion')
            ->where('p.pers_oculta_admin', false)
            // Solo las de los directorios: tienen o tuvieron un cargo. Quien solo tiene
            // cuenta se administra en Usuarios (si tiene ambas cosas, aparece en las dos)
            ->when($soloDirectorio, fn (Builder $q) => $q->whereExists($this->enDirectorio()))
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
                DB::raw('exists (select 1 from usuario u where u.usua_id_persona = p.pers_id_persona) as tiene_cuenta'),
            ]);
    }

    private function enDirectorio(): \Closure
    {
        return fn (Builder $e) => $e->from('designacion as dd')->whereColumn('dd.desi_id_persona', 'p.pers_id_persona');
    }

    private function idTipoCorreo(): int
    {
        return (int) DB::table('tipo_contacto')->where('tico_nombre', 'Correo')->value('tico_id_tipo_contacto');
    }
}

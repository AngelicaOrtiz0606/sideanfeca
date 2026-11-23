<?php

namespace App\Services;

use App\Enums\Privilegio;
use App\Services\Puestos\PuestoService;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lectura de la bitácora (BITACORA y BITACORA_DETALLE), solo consulta.
 *
 * Convierte cada movimiento en una oración que se entiende sin conocer la
 * base: "Marta Ruiz creó la cuenta de Luis Ramírez (Administrador)".
 * Los nombres se resuelven desde la ficha de cada registro; si ya no existe
 * (por ejemplo, tras ARCO), se dice "una persona eliminada (#n)".
 *
 * La usan la pantalla de inicio (los últimos movimientos) y la consulta
 * completa de la bitácora (con filtros, de 10 en 10 y en CSV).
 */
class BitacoraService
{
    /**
     * Cada tabla de la bitácora dicha como en una oración: [singular con artículo, plural con artículo].
     */
    private const TABLAS = [
        'PERSONA' => ['la persona', 'las personas'],
        'USUARIO' => ['la cuenta', 'las cuentas'],
        'INSTITUCION_EDUCATIVA' => ['la institución', 'las instituciones'],
        'ORGANIZACION' => ['la organización', 'las organizaciones'],
        'COORDINACION_NACIONAL' => ['la coordinación nacional', 'las coordinaciones nacionales'],
        'ZONA_REGIONAL' => ['la zona regional', 'las zonas regionales'],
        'ENTIDAD_FEDERATIVA' => ['la entidad federativa', 'las entidades federativas'],
        'CARGO' => ['el cargo', 'los cargos'],
        'PUESTO' => ['el puesto', 'los puestos'],
        'PUESTO_DIRECTORIO' => ['el lugar en el directorio', 'el orden de los directorios'],
        'DESIGNACION' => ['la designación', 'las designaciones'],
        'NIVEL_ACADEMICO' => ['el grado académico', 'los grados académicos'],
        'CONTACTO' => ['el contacto', 'los contactos'],
    ];

    /**
     * Verbo en pasado para cada acción de la bitácora.
     */
    private const VERBOS = [
        'Registro' => 'registró',
        'Modificacion' => 'modificó',
        'Reordenamiento' => 'reordenó',
        'Activacion' => 'activó',
        'Desactivacion' => 'desactivó',
        'Ocultacion' => 'ocultó',
        'Restauracion' => 'restauró',
        'Eliminacion' => 'eliminó',
        'Anonimizacion' => 'anonimizó',
    ];

    /**
     * Detalles que nombran un registro de otra tabla (su número va en
     * BIDE_REFERENCIA): al registrar a una persona, cada designación y cada
     * puesto creado; al borrar una designación, su persona y su puesto.
     */
    private const CAMPOS_DE_OTRA_TABLA = [
        'Designación' => 'DESIGNACION',
        'Puesto creado' => 'PUESTO',
        'Persona' => 'PERSONA',
        'Puesto' => 'PUESTO',
    ];

    /**
     * Nombres de campo escritos por las funciones de la base, como se dicen en pantalla.
     */
    private const CAMPOS_EN_PANTALLA = [
        'Fecha en que deja el cargo' => 'Deja el puesto desde',
    ];

    /**
     * Detalles con un solo valor (no son un cambio "de → a"): se dicen "Campo: valor".
     */
    private const CAMPOS_DE_UN_VALOR = ['Directorio', 'Agregado al directorio', 'Quitado del directorio'];

    /**
     * Datos personales que la base guarda sin su valor (la bitácora no se borra
     * y la cancelación ARCO exige borrar esos datos). Se dice que cambiaron y
     * por qué no se ve el valor. El grado y la pertenencia sí lo guardan desde
     * noviembre de 2026; los anteriores a eso tampoco lo tienen.
     */
    private const CAMPOS_SIN_VALOR = ['Nombre', 'Primer apellido', 'Segundo apellido', 'CURP', 'Género', 'Correo', 'Contacto',
        'Grado académico', 'Pertenencia'];

    /**
     * Al borrar un registro se guarda cómo se llamaba (su detalle tiene el
     * valor anterior): con eso la línea dice su nombre en lugar de "#7".
     */
    private const NOMBRE_AL_BORRAR = ['Nombre de la zona', 'Nombre de la coordinación', 'Nombre del grado', 'Nombre masculino',
        'Razón social', 'Nombre de la organización', 'Puesto'];

    /**
     * Máximo de cambios de campo que se muestran por movimiento.
     */
    private const DETALLES_MAXIMOS = 3;

    /**
     * Tono de la etiqueta de cada acción: crear, cambiar, suspender o quitar.
     */
    private const TONOS = [
        'Registro' => 'exito',
        'Modificacion' => 'info',
        'Reordenamiento' => 'info',
        'Activacion' => 'exito',
        'Restauracion' => 'info',
        'Desactivacion' => 'aviso',
        'Ocultacion' => 'aviso',
        'Eliminacion' => 'error',
        'Anonimizacion' => 'error',
    ];

    /**
     * Acciones de la bitácora (CKC_BITA_ACCION) con acentos.
     */
    private const ACCIONES = [
        'Registro' => 'Registro',
        'Modificacion' => 'Modificación',
        'Reordenamiento' => 'Reordenamiento',
        'Activacion' => 'Activación',
        'Desactivacion' => 'Desactivación',
        'Ocultacion' => 'Ocultación',
        'Restauracion' => 'Restauración',
        'Eliminacion' => 'Eliminación',
        'Anonimizacion' => 'Anonimización',
    ];

    /**
     * Últimos movimientos de la bitácora, ya con nombres para mostrar.
     * Los nombres se resuelven desde la ficha; si ya no existe (por ejemplo
     * tras ARCO), se muestra "persona eliminada #n".
     *
     * Cada movimiento se describe como una oración: "Marta Ruiz registró la cuenta
     * de Luis Ramírez", con los campos que cambiaron (BITACORA_DETALLE) y el motivo.
     *
     * @return Collection<int, array{fecha: Carbon, quien: string, accion: string, tono: string, verbo: string, objeto: string, motivo: ?string, detalles: array<int, string>, mas_detalles: int, ficha: ?string}>
     */
    public function recientes(int $limite, ?int $usuario = null): Collection
    {
        $movimientos = DB::table('bitacora')
            ->when($usuario, fn (Builder $q, int $id) => $q->where('bita_id_usuario', $id))
            ->orderByDesc('bita_fecha_hora')
            ->orderByDesc('bita_id_bitacora')
            ->limit($limite)
            ->get();

        return $this->describir($movimientos);
    }

    /** Cuántos movimientos hay (de una cuenta, si se dice cuál). */
    public function total(?int $usuario = null): int
    {
        return DB::table('bitacora')->when($usuario, fn (Builder $q, int $id) => $q->where('bita_id_usuario', $id))->count();
    }

    /**
     * La bitácora completa con filtros, de la más reciente a la más antigua,
     * de 10 en 10. Cada movimiento trae todos sus cambios.
     *
     * @param  array{texto?: ?string, accion?: ?string, tabla?: ?string, desde?: ?string, hasta?: ?string, usuario?: ?int}  $filtros
     */
    public function listado(array $filtros, int $porPagina = 10): LengthAwarePaginator
    {
        $pagina = $this->filtrada($filtros)->paginate($porPagina)->withQueryString();

        return $pagina->setCollection($this->describir($pagina->getCollection(), todos: true));
    }

    /**
     * Todos los movimientos filtrados, por partes (para el CSV): $hacer recibe
     * cada parte ya descrita.
     *
     * @param  array{texto?: ?string, accion?: ?string, tabla?: ?string, desde?: ?string, hasta?: ?string, usuario?: ?int}  $filtros
     * @param  callable(Collection<int, array<string, mixed>>): void  $hacer
     */
    public function porPartes(array $filtros, callable $hacer): void
    {
        $this->filtrada($filtros)->chunk(500, fn (Collection $parte) => $hacer($this->describir($parte, todos: true)));
    }

    /**
     * Acciones para el filtro: valor en la base => como se dice.
     *
     * @return array<string, string>
     */
    public function acciones(): array
    {
        return self::ACCIONES;
    }

    /**
     * Qué se modificó, para el filtro: tabla => nombre en plural ("Personas", "Cuentas").
     *
     * @return array<string, string>
     */
    public function tablas(): array
    {
        return collect(self::TABLAS)
            ->map(fn (array $nombres) => Str::ucfirst(preg_replace('/^(el|la|los|las) /u', '', $nombres[1])))
            ->sort()
            ->all();
    }

    /**
     * Filtros: texto (quién lo hizo, la persona o cuenta de la que se trata,
     * o el texto de un cambio, como el nombre de un puesto), acción, tabla y
     * rango de fechas.
     *
     * @param  array{texto?: ?string, accion?: ?string, tabla?: ?string, desde?: ?string, hasta?: ?string, usuario?: ?int}  $filtros
     */
    private function filtrada(array $filtros): Builder
    {
        $nombre = "translate(lower(concat_ws(' ', pe.pers_nombre, pe.pers_primer_apellido, pe.pers_segundo_apellido)), 'áéíóúü', 'aeiouu') like ?";
        $texto = trim((string) ($filtros['texto'] ?? ''));
        $patron = '%'.addcslashes(strtr(mb_strtolower($texto), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']), '%_\\').'%';

        return DB::table('bitacora as b')
            ->select('b.*')
            ->when($texto !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                // Quién lo hizo
                ->whereExists(fn (Builder $e) => $e->from('usuario as u')->join('persona as pe', 'pe.pers_id_persona', '=', 'u.usua_id_persona')
                    ->whereColumn('u.usua_id_usuario', 'b.bita_id_usuario')->whereRaw($nombre, [$patron]))
                // La persona de la que se trata
                ->orWhereExists(fn (Builder $e) => $e->from('persona as pe')->where('b.bita_tabla', 'PERSONA')
                    ->whereColumn('pe.pers_id_persona', 'b.bita_referencia')->whereRaw($nombre, [$patron]))
                // La cuenta de la que se trata
                ->orWhereExists(fn (Builder $e) => $e->from('usuario as u')->join('persona as pe', 'pe.pers_id_persona', '=', 'u.usua_id_persona')
                    ->where('b.bita_tabla', 'USUARIO')->whereColumn('u.usua_id_usuario', 'b.bita_referencia')->whereRaw($nombre, [$patron]))
                // El texto de un cambio (puestos, designaciones, nombres de catálogos...)
                ->orWhereExists(fn (Builder $e) => $e->from('bitacora_detalle as d')->whereColumn('d.bide_id_bitacora', 'b.bita_id_bitacora')
                    ->where(fn (Builder $c) => $c
                        ->whereRaw("translate(lower(coalesce(d.bide_nuevo, '')), 'áéíóúü', 'aeiouu') like ?", [$patron])
                        ->orWhereRaw("translate(lower(coalesce(d.bide_anterior, '')), 'áéíóúü', 'aeiouu') like ?", [$patron])))))
            // "Mi actividad": solo lo que hizo esa cuenta
            ->when($filtros['usuario'] ?? null, fn (Builder $q, int $usuario) => $q->where('b.bita_id_usuario', $usuario))
            ->when($filtros['accion'] ?? null, fn (Builder $q, string $accion) => $q->where('b.bita_accion', $accion))
            ->when($filtros['tabla'] ?? null, fn (Builder $q, string $tabla) => $q->where('b.bita_tabla', $tabla))
            ->when($filtros['desde'] ?? null, fn (Builder $q, string $desde) => $q->where('b.bita_fecha_hora', '>=', $desde))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, string $hasta) => $q->where('b.bita_fecha_hora', '<', Carbon::parse($hasta)->addDay()->toDateString()))
            ->orderByDesc('b.bita_fecha_hora')
            ->orderByDesc('b.bita_id_bitacora');
    }

    /**
     * Convierte filas de BITACORA en movimientos listos para mostrar. Con
     * $todos trae todos sus cambios (si no, los primeros y cuántos faltan).
     *
     * @param  Collection<int, object>  $movimientos
     * @return Collection<int, array{fecha: Carbon, quien: string, accion: string, tono: string, verbo: string, objeto: string, motivo: ?string, detalles: array<int, string>, mas_detalles: int, ficha: ?string}>
     */
    public function describir(Collection $movimientos, bool $todos = false): Collection
    {
        $cuentas = $this->nombresDeCuentas(
            $movimientos->pluck('bita_id_usuario')
                ->merge($movimientos->where('bita_tabla', 'USUARIO')->pluck('bita_referencia'))
                ->filter()->unique()->values()
        );
        $personas = $this->nombresDePersonas(
            $movimientos->where('bita_tabla', 'PERSONA')->pluck('bita_referencia')->filter()->unique()->values()
        );

        $detalles = DB::table('bitacora_detalle')
            ->whereIn('bide_id_bitacora', $movimientos->pluck('bita_id_bitacora'))
            ->orderBy('bide_id_detalle')
            ->get()
            ->groupBy('bide_id_bitacora');

        // Nombres de los registros (coordinaciones, zonas, instituciones...), tanto
        // del registro principal como de los que aparecen en los cambios
        $nombres = $this->nombresDeReferencias($movimientos, $detalles);
        $contextos = $this->contextos($movimientos);

        // En el inicio se muestran unos cuantos cambios; en la bitácora completa, todos
        $maximo = $todos ? PHP_INT_MAX : self::DETALLES_MAXIMOS;

        return $movimientos->map(function (object $movimiento) use ($cuentas, $personas, $detalles, $nombres, $contextos, $maximo): array {
            $quien = $cuentas[$movimiento->bita_id_usuario] ?? "Cuenta #{$movimiento->bita_id_usuario}";
            $cambios = ($detalles[$movimiento->bita_id_bitacora] ?? collect())->map(
                fn (object $d): string => $this->describirCambio($d, $movimiento, $nombres)
            );

            return [
                'fecha' => Carbon::parse($movimiento->bita_fecha_hora),
                'quien' => $quien,
                'accion' => self::ACCIONES[$movimiento->bita_accion] ?? $movimiento->bita_accion,
                'tono' => self::TONOS[$movimiento->bita_accion] ?? 'info',
                // Crear una cuenta se dice "creó", no "registró"
                'verbo' => $movimiento->bita_tabla === 'USUARIO' && $movimiento->bita_accion === 'Registro'
                    ? 'creó'
                    : (self::VERBOS[$movimiento->bita_accion] ?? mb_strtolower($movimiento->bita_accion)),
                'objeto' => $this->describirRegistro($movimiento, $cuentas, $personas, $nombres, $contextos, $detalles[$movimiento->bita_id_bitacora] ?? collect()),
                'motivo' => $movimiento->bita_motivo,
                'detalles' => $cambios->take($maximo)->values()->all(),
                'mas_detalles' => max(0, $cambios->count() - $maximo),
                // Los datos personales no guardan su valor: la ficha muestra el actual
                'ficha' => $this->ficha($movimiento, $detalles[$movimiento->bita_id_bitacora] ?? collect()),
            ];
        });
    }

    /**
     * Lo que se nombra después del verbo: "la cuenta de Luis Ramírez (Administrador)",
     * "a la persona Ana López, de la FCA UNAM", "la zona regional Ciudad de México".
     *
     * @param  array<string, array<int, string>>  $nombres
     * @param  array<string, array<int, string>>  $contextos
     */
    private function describirRegistro(object $movimiento, Collection $cuentas, Collection $personas, array $nombres = [], array $contextos = [], ?Collection $detalles = null): string
    {
        $referencia = $movimiento->bita_referencia;
        $generico = strtolower(str_replace('_', ' ', $movimiento->bita_tabla));
        [$singular, $plural] = self::TABLAS[$movimiento->bita_tabla] ?? ["el registro de {$generico}", "los registros de {$generico}"];

        // Sin referencia, o un reordenamiento: la acción fue sobre el conjunto
        // ("reordenó las coordinaciones nacionales"); cada cambio dice cuál se movió
        if ($referencia === null || $movimiento->bita_accion === 'Reordenamiento') {
            return $plural;
        }

        $contexto = $contextos[$movimiento->bita_tabla][$referencia] ?? null;

        // Agregar designaciones a una persona ya registrada: "modificó las designaciones de la persona …"
        $soloDesignaciones = $detalles !== null && $detalles->isNotEmpty()
            && $detalles->every(fn (object $d) => in_array($d->bide_campo, ['Designación', 'Puesto creado'], true));

        return match ($movimiento->bita_tabla) {
            'PERSONA' => $this->describirPersona($movimiento, $personas[$referencia] ?? null, $referencia, $contexto, $soloDesignaciones),
            'USUARIO' => 'la cuenta de '.($cuentas[$referencia] ?? "una persona eliminada (#{$referencia})")
                .($contexto !== null ? " ({$contexto})" : ''),
            default => match (true) {
                isset($nombres[$movimiento->bita_tabla][$referencia]) => "{$singular} {$nombres[$movimiento->bita_tabla][$referencia]}",
                // Una designación borrada ya no tiene nombre: sus detalles dicen de quién y qué puesto
                $movimiento->bita_tabla === 'DESIGNACION' => 'una designación',
                // Ya no existe: se dice como se llamaba (lo guardó su detalle al borrarlo)
                default => ($borrado = $detalles?->first(fn (object $d) => in_array($d->bide_campo, self::NOMBRE_AL_BORRAR, true) && $d->bide_anterior !== null)) !== null
                    ? "{$singular} {$borrado->bide_anterior}"
                    : "{$singular} #{$referencia}",
            },
        };
    }

    private function describirPersona(object $movimiento, ?string $nombre, int $referencia, ?string $pertenencia, bool $soloDesignaciones = false): string
    {
        if ($nombre === null) {
            return "a una persona eliminada (#{$referencia})";
        }

        $persona = "la persona {$nombre}".($pertenencia !== null ? ", de {$pertenencia}" : '');

        if ($movimiento->bita_accion === 'Modificacion') {
            return $soloDesignaciones ? "las designaciones de {$persona}" : "los datos de {$persona}";
        }

        return "a {$persona}";
    }

    /**
     * Un cambio de BITACORA_DETALLE: "Orden: 3 → 5". Los campos personales no
     * guardan valores (regla del esquema), así que solo se nombra el campo.
     */
    private function describirCambio(object $detalle, object $movimiento, array $nombres): string
    {
        // Algunos campos los nombra la base con otras palabras; aquí se dicen como en las pantallas
        $campo = self::CAMPOS_EN_PANTALLA[$detalle->bide_campo] ?? $detalle->bide_campo;

        // Un detalle que nombra otro registro: "Designación: Presidenta · Nacional, desde 20/11/2026".
        // Si guardó su texto se usa ese (no cambia aunque el registro se borre después)
        if (isset(self::CAMPOS_DE_OTRA_TABLA[$detalle->bide_campo])) {
            $nombre = $detalle->bide_nuevo ?? $detalle->bide_anterior
                ?? $nombres[self::CAMPOS_DE_OTRA_TABLA[$detalle->bide_campo]][$detalle->bide_referencia] ?? null;

            return $nombre !== null ? "{$campo}: {$nombre}" : $campo;
        }

        // Datos personales sin su valor: se dice que cambiaron y por qué no se ve
        if ($detalle->bide_anterior === null && $detalle->bide_nuevo === null
            && in_array($detalle->bide_campo, [...self::CAMPOS_SIN_VALOR, 'Contraseña'], true)) {
            return $detalle->bide_campo === 'Contraseña'
                ? 'Contraseña: cambió'
                : "{$campo}: cambió (el valor no se guarda, por privacidad)";
        }

        // Al borrar, el valor es solo el que tenía: "Nombre de la zona: Ciudad de México"
        if ($movimiento->bita_accion === 'Eliminacion' && $detalle->bide_anterior !== null && $detalle->bide_nuevo === null) {
            return "{$campo}: {$this->valor($detalle->bide_anterior)}";
        }

        // Al registrar (o en campos de un solo valor), el valor es solo el nuevo: "Campo: valor"
        if (($movimiento->bita_accion === 'Registro' || in_array($detalle->bide_campo, self::CAMPOS_DE_UN_VALOR, true))
            && $detalle->bide_anterior === null && $detalle->bide_nuevo !== null) {
            return "{$campo}: {$detalle->bide_nuevo}";
        }

        // Si el cambio es de otro registro (por ejemplo, cada coordinación reordenada), se dice cuál
        $esDeOtro = $detalle->bide_referencia !== $movimiento->bita_referencia || $movimiento->bita_accion === 'Reordenamiento';
        if ($detalle->bide_referencia !== null && $esDeOtro) {
            $registro = $nombres[$movimiento->bita_tabla][$detalle->bide_referencia] ?? "#{$detalle->bide_referencia}";
            $campo = "{$registro}, ".mb_strtolower($campo);
        }

        if ($detalle->bide_anterior === null && $detalle->bide_nuevo === null) {
            return $campo;
        }

        $anterior = $this->valor($detalle->bide_anterior) ?? 'sin valor';
        $nuevo = $this->valor($detalle->bide_nuevo) ?? 'sin valor';

        return "{$campo}: {$anterior} → {$nuevo}";
    }

    /**
     * La ficha donde se ve el dato actual, si el movimiento cambió datos
     * personales (que no guardan su valor) y quien consulta puede abrirla.
     *
     * @param  Collection<int, object>  $detalles
     */
    private function ficha(object $movimiento, Collection $detalles): ?string
    {
        $sinValor = $detalles->contains(fn (object $d) => in_array($d->bide_campo, self::CAMPOS_SIN_VALOR, true) && $d->bide_nuevo === null && $d->bide_anterior === null);
        $cuenta = auth()->user();
        if (! $sinValor || $cuenta === null || $movimiento->bita_referencia === null) {
            return null;
        }

        return match ($movimiento->bita_tabla) {
            // En los directorios: su ficha; si solo tiene cuenta: su cuenta (para el superadministrador)
            'PERSONA' => match (true) {
                $cuenta->can(Privilegio::GestionarPersonas->value)
                    && DB::table('persona')->where('pers_id_persona', $movimiento->bita_referencia)->where('pers_oculta_admin', false)->exists()
                    && DB::table('designacion')->where('desi_id_persona', $movimiento->bita_referencia)->exists() => route('personas.show', $movimiento->bita_referencia),
                $cuenta->can(Privilegio::GestionarUsuarios->value)
                    && ($usuario = DB::table('usuario')->where('usua_id_persona', $movimiento->bita_referencia)->value('usua_id_usuario')) !== null => route('usuarios.edit', $usuario),
                default => null,
            },
            'USUARIO' => $cuenta->can(Privilegio::GestionarUsuarios->value)
                && DB::table('usuario')->where('usua_id_usuario', $movimiento->bita_referencia)->whereNotNull('usua_id_persona')->exists()
                    ? route('usuarios.edit', $movimiento->bita_referencia) : null,
            default => null,
        };
    }

    /**
     * Un valor guardado, como se lee: las fechas de la base (2026-11-21) van como 21/11/2026.
     */
    private function valor(?string $valor): ?string
    {
        return $valor !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1
            ? Carbon::parse($valor)->format('d/m/Y')
            : $valor;
    }

    /**
     * Nombres de los registros con nombre propio que aparecen en los movimientos,
     * como registro principal o dentro de sus cambios.
     *
     * @param  Collection<int, object>  $movimientos
     * @param  Collection<int, Collection<int, object>>  $detalles
     * @return array<string, array<int, string>>
     */
    private function nombresDeReferencias(Collection $movimientos, Collection $detalles): array
    {
        $fuentes = [
            'COORDINACION_NACIONAL' => fn (Collection $ids) => DB::table('coordinacion_nacional')
                ->whereIn('cona_id_coordinacion_nacional', $ids)->pluck('cona_nombre', 'cona_id_coordinacion_nacional'),
            'ZONA_REGIONAL' => fn (Collection $ids) => DB::table('zona_regional')
                ->whereIn('zore_id_zona_regional', $ids)->pluck('zore_nombre', 'zore_id_zona_regional'),
            'ENTIDAD_FEDERATIVA' => fn (Collection $ids) => DB::table('entidad_federativa')
                ->whereIn('enfe_id_entidad_federativa', $ids)->pluck('enfe_nombre', 'enfe_id_entidad_federativa'),
            'NIVEL_ACADEMICO' => fn (Collection $ids) => DB::table('nivel_academico')
                ->whereIn('niac_id_nivel_academico', $ids)->pluck('niac_nombre', 'niac_id_nivel_academico'),
            'CARGO' => fn (Collection $ids) => DB::table('cargo')
                ->whereIn('carg_id_cargo', $ids)->pluck('carg_nombre_m', 'carg_id_cargo'),
            'ORGANIZACION' => fn (Collection $ids) => DB::table('organizacion')
                ->whereIn('orga_id_organizacion', $ids)->pluck('orga_nombre', 'orga_id_organizacion'),
            // La razón social vigente (la que no tiene fecha de fin)
            'INSTITUCION_EDUCATIVA' => fn (Collection $ids) => DB::table('institucion_nombre')
                ->whereIn('instn_id_institucion', $ids)->whereNull('instn_fecha_fin')->pluck('instn_nombre', 'instn_id_institucion'),
            'PERSONA' => fn (Collection $ids) => $this->nombresDePersonas($ids),
            // "Presidente · Nacional", "Coordinador Regional · Zona 2 · Noreste"
            'PUESTO' => fn (Collection $ids) => $ids->mapWithKeys(function (int $id): array {
                $puesto = app(PuestoService::class)->buscar($id);

                return $puesto === null ? [] : [$id => app(PuestoService::class)->nombre($puesto)];
            }),
            // Al reordenar un directorio, cada cambio nombra el puesto que se movió
            'PUESTO_DIRECTORIO' => fn (Collection $ids) => $ids->mapWithKeys(function (int $id): array {
                $puesto = app(PuestoService::class)->buscar($id);

                return $puesto === null ? [] : [$id => app(PuestoService::class)->nombre($puesto)];
            }),
            // "de Ana López como Presidenta · Nacional" (el cargo en el género de la persona)
            'DESIGNACION' => fn (Collection $ids) => DB::table('designacion as d')
                ->join('persona as p', 'p.pers_id_persona', '=', 'd.desi_id_persona')
                ->whereIn('d.desi_id_designacion', $ids)
                ->get(['d.desi_id_designacion', 'd.desi_id_puesto', 'p.pers_genero', 'p.pers_nombre', 'p.pers_primer_apellido', 'p.pers_segundo_apellido'])
                ->mapWithKeys(function (object $d): array {
                    $puesto = app(PuestoService::class)->buscar((int) $d->desi_id_puesto);
                    $cargo = $puesto === null ? '' : ' como '.app(PuestoService::class)->nombre($puesto, $d->pers_genero);

                    return [$d->desi_id_designacion => 'de '.$this->nombre($d).$cargo];
                }),
        ];

        // Qué números buscar en cada tabla: los de los movimientos y los de sus detalles
        // (un detalle que nombra otro registro, como "Designación", va a la tabla de ese registro)
        $ids = [];
        foreach ($movimientos as $movimiento) {
            $ids[$movimiento->bita_tabla][] = $movimiento->bita_referencia;
            foreach ($detalles[$movimiento->bita_id_bitacora] ?? [] as $detalle) {
                $ids[self::CAMPOS_DE_OTRA_TABLA[$detalle->bide_campo] ?? $movimiento->bita_tabla][] = $detalle->bide_referencia;
            }
        }

        $nombres = [];
        foreach ($ids as $tabla => $deLaTabla) {
            $deLaTabla = collect($deLaTabla)->filter()->map(fn ($id) => (int) $id)->unique()->values();
            if (isset($fuentes[$tabla]) && $deLaTabla->isNotEmpty()) {
                $nombres[$tabla] = collect($fuentes[$tabla]($deLaTabla))->all();
            }
        }

        return $nombres;
    }

    /**
     * Un dato extra que se agrega a la oración: el perfil de una cuenta o la
     * institución u organización a la que pertenece una persona.
     *
     * @param  Collection<int, object>  $movimientos
     * @return array<string, array<int, string>>
     */
    private function contextos(Collection $movimientos): array
    {
        $contextos = [];

        $idsCuentas = $movimientos->where('bita_tabla', 'USUARIO')->pluck('bita_referencia')->filter()->unique();
        if ($idsCuentas->isNotEmpty()) {
            $contextos['USUARIO'] = DB::table('usuario')
                ->join('rol', 'rol.rol_id_rol', '=', 'usuario.usua_id_rol')
                ->whereIn('usuario.usua_id_usuario', $idsCuentas)
                ->pluck('rol.rol_nombre', 'usuario.usua_id_usuario')
                ->all();
        }

        $idsPersonas = $movimientos->where('bita_tabla', 'PERSONA')->pluck('bita_referencia')->filter()->unique();
        if ($idsPersonas->isNotEmpty()) {
            $contextos['PERSONA'] = DB::table('persona')
                ->leftJoin('institucion_nombre', function ($union): void {
                    $union->on('institucion_nombre.instn_id_institucion', '=', 'persona.pers_id_institucion_educativa')
                        ->whereNull('institucion_nombre.instn_fecha_fin');
                })
                ->leftJoin('organizacion', 'organizacion.orga_id_organizacion', '=', 'persona.pers_id_organizacion')
                ->whereIn('persona.pers_id_persona', $idsPersonas)
                ->get(['persona.pers_id_persona', 'institucion_nombre.instn_nombre', 'organizacion.orga_nombre'])
                ->mapWithKeys(fn (object $fila): array => [$fila->pers_id_persona => $fila->instn_nombre ?? $fila->orga_nombre])
                ->filter()
                ->all();
        }

        return $contextos;
    }

    /**
     * Nombre de la persona dueña de cada cuenta. Las cuentas anonimizadas no tienen nombre.
     *
     * @param  Collection<int, int>  $ids
     * @return Collection<int, string>
     */
    private function nombresDeCuentas(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('usuario')
            ->join('persona', 'persona.pers_id_persona', '=', 'usuario.usua_id_persona')
            ->whereIn('usuario.usua_id_usuario', $ids)
            ->get(['usuario.usua_id_usuario', 'persona.pers_nombre', 'persona.pers_primer_apellido', 'persona.pers_segundo_apellido'])
            ->mapWithKeys(fn (object $fila): array => [$fila->usua_id_usuario => $this->nombre($fila)]);
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, string>
     */
    private function nombresDePersonas(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('persona')
            ->whereIn('pers_id_persona', $ids)
            ->get(['pers_id_persona', 'pers_nombre', 'pers_primer_apellido', 'pers_segundo_apellido'])
            ->mapWithKeys(fn (object $fila): array => [$fila->pers_id_persona => $this->nombre($fila)]);
    }

    private function nombre(object $fila): string
    {
        return trim(implode(' ', array_filter([$fila->pers_nombre, $fila->pers_primer_apellido, $fila->pers_segundo_apellido])));
    }
}

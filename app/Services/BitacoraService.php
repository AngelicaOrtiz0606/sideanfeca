<?php

namespace App\Services;

use App\Services\Puestos\PuestoService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lectura de la bitácora (BITACORA y BITACORA_DETALLE), solo consulta.
 *
 * Convierte cada movimiento en una oración que se entiende sin conocer la
 * base: "Marta Ruiz creó la cuenta de Luis Ramírez (Administrador)".
 * Los nombres se resuelven desde la ficha de cada registro; si ya no existe
 * (por ejemplo, tras ARCO), se dice "una persona eliminada (#n)".
 *
 * La usan la pantalla de inicio y, en M9, la consulta completa de la bitácora.
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
     * @return Collection<int, array{fecha: Carbon, quien: string, accion: string, tono: string, verbo: string, objeto: string, motivo: ?string, detalles: array<int, string>, mas_detalles: int}>
     */
    public function recientes(int $limite): Collection
    {
        $movimientos = DB::table('bitacora')
            ->orderByDesc('bita_fecha_hora')
            ->orderByDesc('bita_id_bitacora')
            ->limit($limite)
            ->get();

        return $this->describir($movimientos);
    }

    public function total(): int
    {
        return DB::table('bitacora')->count();
    }

    /**
     * Convierte filas de BITACORA en movimientos listos para mostrar.
     * La usará también la pantalla completa de bitácora (M9).
     *
     * @param  Collection<int, object>  $movimientos
     * @return Collection<int, array{fecha: Carbon, quien: string, accion: string, tono: string, verbo: string, objeto: string, motivo: ?string, detalles: array<int, string>, mas_detalles: int}>
     */
    public function describir(Collection $movimientos): Collection
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

        return $movimientos->map(function (object $movimiento) use ($cuentas, $personas, $detalles, $nombres, $contextos): array {
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
                'objeto' => $this->describirRegistro($movimiento, $cuentas, $personas, $nombres, $contextos),
                'motivo' => $movimiento->bita_motivo,
                'detalles' => $cambios->take(self::DETALLES_MAXIMOS)->values()->all(),
                'mas_detalles' => max(0, $cambios->count() - self::DETALLES_MAXIMOS),
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
    private function describirRegistro(object $movimiento, Collection $cuentas, Collection $personas, array $nombres = [], array $contextos = []): string
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

        return match ($movimiento->bita_tabla) {
            'PERSONA' => $this->describirPersona($movimiento, $personas[$referencia] ?? null, $referencia, $contexto),
            'USUARIO' => 'la cuenta de '.($cuentas[$referencia] ?? "una persona eliminada (#{$referencia})")
                .($contexto !== null ? " ({$contexto})" : ''),
            default => isset($nombres[$movimiento->bita_tabla][$referencia])
                ? "{$singular} {$nombres[$movimiento->bita_tabla][$referencia]}"
                : "{$singular} #{$referencia}",
        };
    }

    private function describirPersona(object $movimiento, ?string $nombre, int $referencia, ?string $pertenencia): string
    {
        if ($nombre === null) {
            return "a una persona eliminada (#{$referencia})";
        }

        $persona = "la persona {$nombre}".($pertenencia !== null ? ", de {$pertenencia}" : '');

        return $movimiento->bita_accion === 'Modificacion' ? "los datos de {$persona}" : "a {$persona}";
    }

    /**
     * Un cambio de BITACORA_DETALLE: "Orden: 3 → 5". Los campos personales no
     * guardan valores (regla del esquema), así que solo se nombra el campo.
     */
    private function describirCambio(object $detalle, object $movimiento, array $nombres): string
    {
        $campo = $detalle->bide_campo;

        // Un detalle que nombra otro registro: "Designación: Presidenta · Nacional, desde 20/11/2026".
        // Si guardó su texto se usa ese (no cambia aunque el registro se borre después)
        if (isset(self::CAMPOS_DE_OTRA_TABLA[$campo])) {
            $nombre = $detalle->bide_nuevo ?? $detalle->bide_anterior
                ?? $nombres[self::CAMPOS_DE_OTRA_TABLA[$campo]][$detalle->bide_referencia] ?? null;

            return $nombre !== null ? "{$campo}: {$nombre}" : $campo;
        }

        // Al registrar, el valor es solo el nuevo: "Campo: valor"
        if ($movimiento->bita_accion === 'Registro' && $detalle->bide_anterior === null && $detalle->bide_nuevo !== null) {
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

        $anterior = $detalle->bide_anterior ?? 'sin valor';
        $nuevo = $detalle->bide_nuevo ?? 'sin valor';

        return "{$campo}: {$anterior} → {$nuevo}";
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

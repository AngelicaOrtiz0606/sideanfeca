<?php

namespace App\Http\Requests\Personas;

use App\Services\Designaciones\DesignacionService;
use App\Services\Personas\PersonaService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Validación de las designaciones que se capturan (una o varias filas, en
 * Nueva persona y en "Agregar designación" de la ficha). Cada fila: el nivel
 * del cargo, el cargo, su ámbito (zona y coordinación; un cargo institucional
 * es de la institución a la que pertenece la persona) y la fecha de inicio.
 * Con eso se busca el puesto (o se crea al guardar). La fecha de fin es
 * opcional (su último día en el puesto), para registrar de una vez
 * designaciones que ya terminaron. Reglas:
 * - la persona debe pertenecer a una institución u organización;
 * - el mismo puesto no se repite entre las filas en periodos que se cruzan;
 * - el puesto debe estar activo y libre en todo ese periodo;
 * - un cargo nacional que es de una organización (el presidente de CACECA)
 *   solo lo ocupa alguien de esa organización, y se usa el puesto de ella;
 * - un cargo institucional se puede marcar como titular de la institución:
 *   si ya hay titular, hay que confirmar el cambio (reemplazar). Si es el
 *   mismo puesto, la designación del titular anterior termina el día antes.
 * - si el puesto ya lo ocupa alguien desde antes, se ofrece reemplazarlo
 *   (reemplazar_ocupante): su designación termina el día antes de la nueva.
 * Los mensajes dicen de qué fila se trata ("Designación 2: ...").
 */
trait ValidaDesignaciones
{
    public const MAXIMO_DESIGNACIONES = 10;

    /**
     * Las filas sin nada elegido (por ejemplo, una agregada sin llenar) no cuentan.
     */
    protected function filtrarDesignaciones(): void
    {
        $this->merge(['designaciones' => collect(is_array($this->input('designaciones')) ? $this->input('designaciones') : [])
            ->filter(fn ($d) => is_array($d) && (filled($d['cargo'] ?? null) || filled($d['zona'] ?? null)))
            ->values()->all()]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function reglasDesignaciones(bool $obligatorias = true): array
    {
        return [
            'designaciones' => [$obligatorias ? 'required' : 'nullable', 'array', 'max:'.self::MAXIMO_DESIGNACIONES],
            'designaciones.*.cargo' => ['required', 'integer', Rule::exists('cargo', 'carg_id_cargo')->where('carg_activo', true)],
            'designaciones.*.zona' => ['nullable', 'integer', Rule::exists('zona_regional', 'zore_id_zona_regional')],
            'designaciones.*.coordinacion' => ['nullable', 'integer', Rule::exists('coordinacion_nacional', 'cona_id_coordinacion_nacional')->where('cona_activo', true)],
            'designaciones.*.fecha_inicio' => ['required', 'date', 'after_or_equal:1950-01-01', 'before_or_equal:today'],
            'designaciones.*.fecha_fin' => ['nullable', 'date', 'after_or_equal:designaciones.*.fecha_inicio'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesDesignaciones(): array
    {
        return [
            'designaciones.required' => 'Agrega al menos una designación.',
            'designaciones.max' => 'Puedes registrar hasta '.self::MAXIMO_DESIGNACIONES.' designaciones a la vez.',
            'designaciones.*.cargo.required' => 'Elige el cargo de cada designación o quita la fila.',
            'designaciones.*.cargo.exists' => 'Elige cargos activos de la lista.',
            'designaciones.*.zona.exists' => 'Elige una zona de la lista.',
            'designaciones.*.coordinacion.exists' => 'Elige una coordinación activa de la lista.',
            'designaciones.*.fecha_inicio.required' => 'Escribe desde cuándo tiene cada designación.',
            'designaciones.*.fecha_inicio.date' => 'Escribe una fecha válida.',
            'designaciones.*.fecha_inicio.after_or_equal' => 'La fecha de inicio no puede ser anterior a 1950.',
            'designaciones.*.fecha_inicio.before_or_equal' => 'La fecha de inicio no puede ser posterior a hoy.',
            'designaciones.*.fecha_fin.date' => 'Escribe una fecha de fin válida.',
            'designaciones.*.fecha_fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
        ];
    }

    /**
     * Las designaciones, listas para guardar: lo que no aplica al nivel del
     * cargo va vacío; un cargo institucional lleva la institución de la persona
     * y uno nacional sin coordinación, su organización (para usar el puesto de
     * su organización, si lo hay).
     *
     * @return array<int, array{cargo: int, zona: ?int, coordinacion: ?int, institucion: ?int, organizacion: ?int, fecha_inicio: string, fecha_fin: ?string, titular: bool, reemplazar: bool, reemplazar_ocupante: bool}>
     */
    public function designaciones(?int $institucion, ?int $organizacion = null): array
    {
        return array_map(function (array $d) use ($institucion, $organizacion) {
            $nivel = $this->nivelDe($d['cargo'] ?? null);
            $coordinacion = $nivel !== 'Institucional' && filled($d['coordinacion'] ?? null) ? (int) $d['coordinacion'] : null;

            return [
                'cargo' => (int) $d['cargo'],
                'zona' => $nivel === 'Regional' && filled($d['zona'] ?? null) ? (int) $d['zona'] : null,
                'coordinacion' => $coordinacion,
                'institucion' => $nivel === 'Institucional' ? $institucion : null,
                'organizacion' => $nivel === 'Nacional' && $coordinacion === null ? $organizacion : null,
                'fecha_inicio' => (string) $d['fecha_inicio'],
                'fecha_fin' => filled($d['fecha_fin'] ?? null) ? (string) $d['fecha_fin'] : null,
                'titular' => $nivel === 'Institucional' && filter_var($d['titular'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'reemplazar' => filter_var($d['reemplazar'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'reemplazar_ocupante' => filter_var($d['reemplazar_ocupante'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }, (array) $this->input('designaciones', []));
    }

    /**
     * @param  string  $pertenencia  'institucion', 'organizacion' o 'ninguna'
     * @param  string  $campoPertenencia  dónde se avisa si no pertenece a nada
     */
    protected function revisarDesignaciones(Validator $validator, string $pertenencia, ?int $institucion, string $campoPertenencia, ?int $organizacion = null): void
    {
        if ($validator->errors()->has('designaciones')) {
            return;
        }
        if ($pertenencia === 'ninguna') {
            $validator->errors()->add($campoPertenencia, 'Para recibir una designación debe pertenecer a una institución u organización.');

            return;
        }

        $servicio = app(PersonaService::class);
        $vistas = [];
        foreach ($this->designaciones($institucion, $organizacion) as $i => $designacion) {
            $n = $i + 1;
            $campo = "designaciones.{$i}.cargo";
            if ($validator->errors()->hasAny([$campo, "designaciones.{$i}.fecha_inicio", "designaciones.{$i}.fecha_fin"])) {
                continue;
            }
            $nivel = $this->nivelDe($designacion['cargo']);
            if ($nivel === 'Institucional' && $pertenencia !== 'institucion') {
                $validator->errors()->add($campo, "Designación {$n}: un cargo institucional es de la institución a la que pertenece la persona, y ella no pertenece a una institución.");

                continue;
            }
            if ($nivel === 'Regional' && $designacion['zona'] === null) {
                $validator->errors()->add("designaciones.{$i}.zona", "Designación {$n}: elige la zona regional.");

                continue;
            }

            // Un cargo de una organización (el presidente de CACECA) solo lo ocupa alguien de ella
            if ($nivel === 'Nacional' && $designacion['coordinacion'] === null) {
                $deOrganizaciones = DB::table('puesto as p')->join('organizacion as o', 'o.orga_id_organizacion', '=', 'p.pues_id_organizacion')
                    ->where('p.pues_id_cargo', $designacion['cargo'])->pluck('o.orga_nombre', 'p.pues_id_organizacion');
                if ($deOrganizaciones->isNotEmpty() && ! $deOrganizaciones->has($designacion['organizacion'])) {
                    $validator->errors()->add($campo, "Designación {$n}: ese cargo es de {$deOrganizaciones->implode(', ')}; solo lo ocupa alguien que pertenezca a esa organización.");

                    continue;
                }
            }

            // El mismo puesto puede repetirse en periodos distintos (por ejemplo, dos periodos de presidencia)
            $clave = implode('|', [$designacion['cargo'], $designacion['zona'], $designacion['coordinacion'], $designacion['institucion']]);
            foreach ($vistas[$clave] ?? [] as [$otra, $inicio, $fin]) {
                if ($designacion['fecha_inicio'] <= ($fin ?? '9999-12-31') && ($designacion['fecha_fin'] ?? '9999-12-31') >= $inicio) {
                    $validator->errors()->add($campo, "Designación {$n}: es el mismo puesto que la designación {$otra}, en fechas que se cruzan.");

                    continue 2;
                }
            }
            $vistas[$clave][] = [$n, $designacion['fecha_inicio'], $designacion['fecha_fin']];

            $puesto = $servicio->puestoDe($designacion['cargo'], $designacion['zona'], $designacion['coordinacion'], $designacion['institucion'], $designacion['organizacion']);

            // Titular de la institución: si ya hay alguien, se confirma el cambio
            $reemplazada = null;
            if ($designacion['titular'] && $designacion['institucion'] !== null) {
                $titular = app(DesignacionService::class)->titularDe($designacion['institucion'], $designacion['fecha_inicio']);
                if ($titular?->ocupante !== null && ! $designacion['reemplazar']) {
                    $validator->errors()->add("designaciones.{$i}.reemplazar", "Designación {$n}: el titular de la institución es {$titular->ocupante} ({$titular->cargo}). Confirma el cambio de titular.");

                    continue;
                }
                if ($titular?->ocupante !== null && $titular->puesto === $puesto) {
                    // El mismo puesto: su designación termina el día anterior, así que debe haber empezado antes
                    if ($titular->desde >= $designacion['fecha_inicio']) {
                        $validator->errors()->add("designaciones.{$i}.fecha_inicio", "Designación {$n}: {$titular->ocupante} es titular desde el ".Carbon::parse($titular->desde)->format('d/m/Y').'; la nueva designación debe empezar después.');

                        continue;
                    }
                    $reemplazada = $titular->designacion;
                }
            }

            if ($puesto === null) {
                continue; // El puesto se creará al guardar
            }
            if (! DB::table('puesto')->where('pues_id_puesto', $puesto)->value('pues_activo')) {
                $validator->errors()->add($campo, "Designación {$n}: ese puesto está desactivado. Actívalo en Puestos o elige otro.");
            } elseif (($ocupante = $servicio->ocupanteEntre($puesto, $designacion['fecha_inicio'], $designacion['fecha_fin'], $reemplazada)) !== null) {
                // Si quien lo ocupa empezó antes y nadie más se cruza, se ofrece reemplazarlo:
                // su designación termina el día anterior a la nueva
                $actual = app(DesignacionService::class)->ocupanteEn($puesto, $designacion['fecha_inicio']);
                $soloEse = $actual !== null && $actual->desde < $designacion['fecha_inicio']
                    && $actual->persona !== (int) ($this->route('persona') ?? 0)
                    && $servicio->ocupanteEntre($puesto, $designacion['fecha_inicio'], $designacion['fecha_fin'], $actual->designacion) === null;
                if (! $soloEse) {
                    $validator->errors()->add($campo, "Designación {$n}: ese puesto lo ocupa {$ocupante} en esas fechas. Primero termina esa designación.");
                } elseif (! $designacion['reemplazar_ocupante']) {
                    $validator->errors()->add("designaciones.{$i}.reemplazar_ocupante", "Designación {$n}: ese puesto lo ocupa {$actual->nombre} desde el "
                        .Carbon::parse($actual->desde)->format('d/m/Y').'. Si deja el puesto, marca «Sí, reemplazar»: su designación terminará el '
                        .Carbon::parse($designacion['fecha_inicio'])->subDay()->format('d/m/Y').'.');
                }
            }
        }
    }

    private function nivelDe(mixed $cargo): ?string
    {
        static $niveles = [];
        if (! ctype_digit((string) $cargo)) {
            return null;
        }

        return $niveles[(int) $cargo] ??= DB::table('cargo')->where('carg_id_cargo', (int) $cargo)->value('carg_nivel');
    }
}

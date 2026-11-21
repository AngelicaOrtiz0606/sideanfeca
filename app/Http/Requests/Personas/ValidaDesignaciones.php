<?php

namespace App\Http\Requests\Personas;

use App\Services\Personas\PersonaService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Validación de las designaciones que se capturan (una o varias filas, en
 * Nueva persona y en "Agregar designación" de la ficha). Cada fila: el nivel
 * del cargo, el cargo, su ámbito (zona y coordinación; un cargo institucional
 * es de la institución a la que pertenece la persona) y la fecha de inicio.
 * Con eso se busca el puesto (o se crea al guardar). Reglas:
 * - la persona debe pertenecer a una institución u organización;
 * - el mismo puesto no se repite entre las filas;
 * - el puesto debe estar activo y libre en esa fecha.
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
    protected function reglasDesignaciones(): array
    {
        return [
            'designaciones' => ['required', 'array', 'max:'.self::MAXIMO_DESIGNACIONES],
            'designaciones.*.cargo' => ['required', 'integer', Rule::exists('cargo', 'carg_id_cargo')->where('carg_activo', true)],
            'designaciones.*.zona' => ['nullable', 'integer', Rule::exists('zona_regional', 'zore_id_zona_regional')],
            'designaciones.*.coordinacion' => ['nullable', 'integer', Rule::exists('coordinacion_nacional', 'cona_id_coordinacion_nacional')->where('cona_activo', true)],
            'designaciones.*.fecha_inicio' => ['required', 'date', 'after_or_equal:1950-01-01', 'before_or_equal:today'],
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
        ];
    }

    /**
     * Las designaciones, listas para guardar: lo que no aplica al nivel del
     * cargo va vacío; un cargo institucional lleva la institución de la persona.
     *
     * @return array<int, array{cargo: int, zona: ?int, coordinacion: ?int, institucion: ?int, fecha_inicio: string}>
     */
    public function designaciones(?int $institucion): array
    {
        return array_map(function (array $d) use ($institucion) {
            $nivel = $this->nivelDe($d['cargo'] ?? null);

            return [
                'cargo' => (int) $d['cargo'],
                'zona' => $nivel === 'Regional' && filled($d['zona'] ?? null) ? (int) $d['zona'] : null,
                'coordinacion' => $nivel !== 'Institucional' && filled($d['coordinacion'] ?? null) ? (int) $d['coordinacion'] : null,
                'institucion' => $nivel === 'Institucional' ? $institucion : null,
                'fecha_inicio' => (string) $d['fecha_inicio'],
            ];
        }, (array) $this->input('designaciones', []));
    }

    /**
     * @param  string  $pertenencia  'institucion', 'organizacion' o 'ninguna'
     * @param  string  $campoPertenencia  dónde se avisa si no pertenece a nada
     */
    protected function revisarDesignaciones(Validator $validator, string $pertenencia, ?int $institucion, string $campoPertenencia): void
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
        foreach ($this->designaciones($institucion) as $i => $designacion) {
            $n = $i + 1;
            $campo = "designaciones.{$i}.cargo";
            if ($validator->errors()->has($campo) || $validator->errors()->has("designaciones.{$i}.fecha_inicio")) {
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

            $clave = implode('|', [$designacion['cargo'], $designacion['zona'], $designacion['coordinacion'], $designacion['institucion']]);
            if (isset($vistas[$clave])) {
                $validator->errors()->add($campo, "Designación {$n}: es el mismo puesto que la designación {$vistas[$clave]}.");

                continue;
            }
            $vistas[$clave] = $n;

            $puesto = $servicio->puestoDe($designacion['cargo'], $designacion['zona'], $designacion['coordinacion'], $designacion['institucion']);
            if ($puesto === null) {
                continue; // El puesto se creará al guardar
            }
            if (! DB::table('puesto')->where('pues_id_puesto', $puesto)->value('pues_activo')) {
                $validator->errors()->add($campo, "Designación {$n}: ese puesto está desactivado. Actívalo en Puestos o elige otro.");
            } elseif (($ocupante = $servicio->ocupanteDesde($puesto, $designacion['fecha_inicio'])) !== null) {
                $validator->errors()->add($campo, "Designación {$n}: ese puesto ya lo ocupa {$ocupante} en esa fecha. Primero termina esa designación.");
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

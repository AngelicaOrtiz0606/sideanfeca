<?php

namespace App\Http\Requests\Personas;

use App\Rules\TextoSeguro;
use App\Services\Personas\PersonaService;
use App\Support\TextoCatalogo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Formulario de una persona (todo junto):
 * - Datos personales: nombre, apellidos, género y CURP opcional (18
 *   caracteres con el formato oficial, sin repetirse; se guarda en mayúsculas).
 * - Pertenencia opcional: una institución o una organización activas (o
 *   ninguna). Con un cargo vigente no se puede quitar, ni cambiar de
 *   institución si el cargo es institucional.
 * - Grados académicos: varios o ninguno (los activos, más los que ya tenía).
 * - Contactos obligatorios: un correo y un teléfono. Son los que se muestran
 *   en el sitio de ANFECA, así que siempre quedan visibles.
 * - Otros contactos (opcionales, por ejemplo el celular): correo válido (en
 *   minúsculas) o número de 7 a 10 dígitos; la lada (2 o 3) y la extensión
 *   (hasta 5) solo si el tipo las permite; y si es visible en el sitio.
 * - Al registrar (Nueva persona), además sus cargos (uno o varios, como los
 *   contactos): cada uno con su ámbito según el nivel (zona y coordinación;
 *   un cargo institucional es de la institución a la que pertenece) y la
 *   fecha de inicio. Necesita pertenecer a algo; ningún puesto debe estar
 *   ocupado en esa fecha ni repetirse. Si ya tenía cuenta
 *   (persona_existente), se usan sus datos en lugar de duplicarla.
 */
class PersonaRequest extends FormRequest
{
    public const CURP = '/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$/';

    public const MAXIMO_CONTACTOS = 20;

    public const MAXIMO_CARGOS = 10;

    /** @var array<int, object>|null */
    private ?array $tipos = null;

    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta (can:gestionar_personas)
    }

    protected function prepareForValidation(): void
    {
        $tipos = $this->tiposContacto();
        $digitos = fn ($v) => is_string($v) ? preg_replace('/[\s\-().]/', '', $v) : $v;

        // Filas de contacto vacías (por ejemplo, una agregada sin llenar) no cuentan
        $contactos = collect(is_array($this->input('contactos')) ? $this->input('contactos') : [])
            ->filter(fn ($c) => is_array($c) && (filled($c['valor'] ?? null) || filled($c['lada'] ?? null) || filled($c['extension'] ?? null)))
            ->map(function (array $c) use ($tipos, $digitos) {
                $tipo = $tipos[(int) ($c['tipo'] ?? 0)] ?? null;

                return [
                    'tipo' => $c['tipo'] ?? null,
                    'valor' => $tipo?->tico_nombre === 'Correo'
                        ? (is_string($c['valor'] ?? null) ? mb_strtolower(trim($c['valor'])) : null)
                        : $digitos($c['valor'] ?? null),
                    'lada' => $digitos($c['lada'] ?? null),
                    'extension' => $digitos($c['extension'] ?? null),
                    'visible' => filter_var($c['visible'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ];
            })->values()->all();

        $telefono = is_array($this->input('telefono')) ? $this->input('telefono') : [];

        if ($this->route('persona') === null) {
            // Filas de cargo sin nada elegido (por ejemplo, una agregada sin llenar) no cuentan
            $this->merge(['cargos' => collect(is_array($this->input('cargos')) ? $this->input('cargos') : [])
                ->filter(fn ($c) => is_array($c) && (filled($c['cargo'] ?? null) || filled($c['zona'] ?? null)))
                ->values()->all()]);
        }

        $this->merge([
            'correo' => is_string($this->input('correo')) ? mb_strtolower(trim($this->input('correo'))) : $this->input('correo'),
            'telefono' => [
                'lada' => $digitos($telefono['lada'] ?? null),
                'valor' => $digitos($telefono['valor'] ?? null),
                'extension' => $digitos($telefono['extension'] ?? null),
            ],
            'curp' => is_string($this->input('curp')) ? strtoupper(preg_replace('/\s+/', '', $this->input('curp'))) : $this->input('curp'),
            'contactos' => $contactos,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = $this->route('persona') ?? $this->existente();
        $pertenencia = $this->input('pertenencia');
        $actual = $id === null ? null : DB::table('persona')->where('pers_id_persona', (int) $id)->first();

        return [
            'nombre' => ['required', 'string', new TextoSeguro, 'max:60'],
            'primer_apellido' => ['required', 'string', new TextoSeguro, 'max:45'],
            'segundo_apellido' => ['nullable', 'string', new TextoSeguro, 'max:45'],
            'curp' => ['nullable', 'string', 'size:18', 'regex:'.self::CURP,
                Rule::unique('persona', 'pers_curp')->ignore($id, 'pers_id_persona')],
            'genero' => ['required', Rule::in(array_keys(PersonaService::GENEROS))],

            'pertenencia' => ['required', Rule::in(['institucion', 'organizacion', 'ninguna'])],
            'institucion' => [Rule::requiredIf($pertenencia === 'institucion'), 'nullable', 'integer',
                Rule::exists('institucion_educativa', 'ined_id_institucion_educativa')->where(fn ($q) => $q->where('ined_activo', true)
                    ->when($actual?->pers_id_institucion_educativa, fn ($w, $i) => $w->orWhere('ined_id_institucion_educativa', $i)))],
            'organizacion' => [Rule::requiredIf($pertenencia === 'organizacion'), 'nullable', 'integer',
                Rule::exists('organizacion', 'orga_id_organizacion')->where(fn ($q) => $q->where('orga_activo', true)
                    ->when($actual?->pers_id_organizacion, fn ($w, $o) => $w->orWhere('orga_id_organizacion', $o)))],

            'grados' => ['nullable', 'array'],
            'grados.*' => ['integer', 'distinct', Rule::exists('nivel_academico', 'niac_id_nivel_academico')->where(fn ($q) => $q->where('niac_activo', true)
                ->when($id, fn ($w) => $w->orWhereExists(fn ($e) => $e->from('persona_nivel_academico')
                    ->whereColumn('pena_id_nivel_academico', 'niac_id_nivel_academico')->where('pena_id_persona', (int) $id))))],

            'correo' => ['required', 'string', 'max:120', 'email:filter'],
            'telefono.valor' => ['required', 'string', 'regex:/^[0-9]{7,10}$/'],
            'telefono.lada' => ['nullable', 'string', 'regex:/^[0-9]{2,3}$/'],
            'telefono.extension' => ['nullable', 'string', 'regex:/^[0-9]{1,5}$/'],

            ...($this->registrando() ? $this->reglasCargo() : []),

            'contactos' => ['array', 'max:'.self::MAXIMO_CONTACTOS],
            'contactos.*.tipo' => ['required', 'integer', Rule::exists('tipo_contacto', 'tico_id_tipo_contacto')],
            'contactos.*.valor' => ['required', 'string', 'max:120'],
            'contactos.*.lada' => ['nullable', 'string', 'regex:/^[0-9]{2,3}$/'],
            'contactos.*.extension' => ['nullable', 'string', 'regex:/^[0-9]{1,5}$/'],
            'contactos.*.visible' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $tipos = $this->tiposContacto();

                // Cada contacto según su tipo
                foreach ((array) $this->input('contactos', []) as $i => $contacto) {
                    $tipo = $tipos[(int) ($contacto['tipo'] ?? 0)] ?? null;
                    if ($tipo === null || $validator->errors()->has("contactos.{$i}.valor")) {
                        continue;
                    }
                    $n = $i + 1;
                    if ($tipo->tico_nombre === 'Correo') {
                        if (filter_var($contacto['valor'], FILTER_VALIDATE_EMAIL) === false) {
                            $validator->errors()->add("contactos.{$i}.valor", "Contacto {$n}: escribe un correo válido, por ejemplo nombre@dominio.mx.");
                        }
                    } elseif (! preg_match('/^[0-9]{7,10}$/', (string) $contacto['valor'])) {
                        $validator->errors()->add("contactos.{$i}.valor", "Contacto {$n}: el número lleva de 7 a 10 dígitos (sin la lada).");
                    }
                    $nombre = mb_strtolower($tipo->tico_nombre);
                    if (! $tipo->tico_permite_lada && filled($contacto['lada'] ?? null)) {
                        $validator->errors()->add("contactos.{$i}.lada", "Contacto {$n}: un {$nombre} no lleva lada.");
                    }
                    if (! $tipo->tico_permite_extension && filled($contacto['extension'] ?? null)) {
                        $validator->errors()->add("contactos.{$i}.extension", "Contacto {$n}: un {$nombre} no lleva extensión.");
                    }
                }

                if ($this->registrando()) {
                    $this->revisarCargo($validator);
                }

                // La pertenencia, si tiene un cargo vigente
                $id = $this->route('persona');
                if ($id !== null && ! $validator->errors()->hasAny(['pertenencia', 'institucion', 'organizacion'])) {
                    $datos = $this->pertenencia();
                    $motivo = app(PersonaService::class)->motivoParaNoCambiarPertenencia((int) $id, $datos['institucion'], $datos['organizacion']);
                    if ($motivo !== null) {
                        $validator->errors()->add('pertenencia', $motivo);
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre.',
            'primer_apellido.required' => 'Escribe el primer apellido.',
            'curp.size' => 'La CURP tiene 18 caracteres.',
            'curp.regex' => 'La CURP no tiene un formato válido. Revísala: 4 letras, 6 números (fecha), H o M, 5 letras y 2 caracteres finales.',
            'curp.unique' => 'Esa CURP ya pertenece a otra persona.',
            'genero.required' => 'Elige el género.',
            'pertenencia.required' => 'Elige a qué pertenece: una institución, una organización o ninguna.',
            'institucion.required' => 'Elige la institución.',
            'institucion.exists' => 'Elige una institución activa de la lista.',
            'organizacion.required' => 'Elige la organización.',
            'organizacion.exists' => 'Elige una organización activa de la lista.',
            'grados.*.exists' => 'Elige grados activos de la lista.',
            'correo.required' => 'Escribe el correo.',
            'correo.email' => 'Escribe un correo válido, por ejemplo nombre@dominio.mx.',
            'correo.max' => 'El correo puede tener hasta 120 caracteres.',
            'telefono.valor.required' => 'Escribe el teléfono.',
            'telefono.valor.regex' => 'El teléfono lleva de 7 a 10 dígitos (sin la lada).',
            'telefono.lada.regex' => 'La lada lleva 2 o 3 dígitos.',
            'telefono.extension.regex' => 'La extensión lleva hasta 5 dígitos.',
            'persona_existente.integer' => 'No encontramos a esa persona. Vuelve a buscarla.',
            'cargos.required' => 'Elige al menos un cargo.',
            'cargos.max' => 'Puede registrar hasta '.self::MAXIMO_CARGOS.' cargos a la vez.',
            'cargos.*.cargo.required' => 'Elige el cargo de cada fila o quítala.',
            'cargos.*.cargo.exists' => 'Elige cargos activos de la lista.',
            'cargos.*.zona.exists' => 'Elige una zona de la lista.',
            'cargos.*.coordinacion.exists' => 'Elige una coordinación activa de la lista.',
            'cargos.*.fecha_inicio.required' => 'Escribe desde cuándo tiene cada cargo.',
            'cargos.*.fecha_inicio.date' => 'Escribe una fecha válida.',
            'cargos.*.fecha_inicio.after_or_equal' => 'La fecha de inicio no puede ser anterior a 1950.',
            'cargos.*.fecha_inicio.before_or_equal' => 'La fecha de inicio no puede ser posterior a hoy.',
            'contactos.max' => 'Puede tener hasta '.self::MAXIMO_CONTACTOS.' contactos.',
            'contactos.*.tipo.required' => 'Elige el tipo de cada contacto.',
            'contactos.*.valor.required' => 'Escribe cada contacto o quita la fila.',
            'contactos.*.valor.max' => 'Un contacto puede tener hasta 120 caracteres.',
            'contactos.*.lada.regex' => 'La lada lleva 2 o 3 dígitos.',
            'contactos.*.extension.regex' => 'La extensión lleva hasta 5 dígitos.',
        ];
    }

    /**
     * @return array{nombre: string, primer_apellido: string, segundo_apellido: ?string, curp: ?string, genero: string, institucion: ?int, organizacion: ?int, grados: array<int, int>, contactos: array<int, array{tipo: int, valor: string, lada: ?string, extension: ?string, visible: bool}>}
     */
    public function datos(): array
    {
        $tipos = $this->tiposContacto();

        return [
            'nombre' => TextoCatalogo::limpiar($this->input('nombre')),
            'primer_apellido' => TextoCatalogo::limpiar($this->input('primer_apellido')),
            'segundo_apellido' => TextoCatalogo::limpiar($this->input('segundo_apellido')) ?: null,
            'curp' => $this->input('curp') ?: null,
            'genero' => (string) $this->input('genero'),
            ...$this->pertenencia(),
            'grados' => array_values(array_unique(array_map('intval', (array) $this->input('grados', [])))),
            // Primero el correo y el teléfono obligatorios (siempre visibles), luego los otros
            'contactos' => [[
                'tipo' => $this->idTipo('Correo'),
                'valor' => (string) $this->input('correo'),
                'lada' => null,
                'extension' => null,
                'visible' => true,
            ], [
                'tipo' => $this->idTipo('Teléfono'),
                'valor' => (string) $this->input('telefono.valor'),
                'lada' => $this->input('telefono.lada') ?: null,
                'extension' => $this->input('telefono.extension') ?: null,
                'visible' => true,
            ], ...array_map(function (array $c) use ($tipos) {
                $tipo = $tipos[(int) $c['tipo']];

                return [
                    'tipo' => (int) $c['tipo'],
                    'valor' => (string) $c['valor'],
                    'lada' => $tipo->tico_permite_lada ? ($c['lada'] ?: null) : null,
                    'extension' => $tipo->tico_permite_extension ? ($c['extension'] ?: null) : null,
                    'visible' => (bool) $c['visible'],
                ];
            }, (array) $this->input('contactos', []))],
        ];
    }

    /**
     * Al registrar: si ya tenía cuenta, su número (si de verdad solo tiene cuenta).
     */
    public function existente(): ?int
    {
        $id = $this->input('persona_existente');
        if (! $this->registrando() || ! ctype_digit((string) $id)) {
            return null;
        }

        return app(PersonaService::class)->buscarSoloCuenta((int) $id) === null ? null : (int) $id;
    }

    /**
     * Los cargos, listos para PersonaService::registrar: lo que no aplica al
     * nivel de cada cargo va vacío.
     *
     * @return array<int, array{cargo: int, zona: ?int, coordinacion: ?int, institucion: ?int, fecha_inicio: string}>
     */
    public function cargos(): array
    {
        $institucion = $this->pertenencia()['institucion'];

        return array_map(function (array $c) use ($institucion) {
            $nivel = $this->nivelDe($c['cargo'] ?? null);

            return [
                'cargo' => (int) $c['cargo'],
                'zona' => $nivel === 'Regional' && filled($c['zona'] ?? null) ? (int) $c['zona'] : null,
                'coordinacion' => $nivel !== 'Institucional' && filled($c['coordinacion'] ?? null) ? (int) $c['coordinacion'] : null,
                'institucion' => $nivel === 'Institucional' ? $institucion : null,
                'fecha_inicio' => (string) $c['fecha_inicio'],
            ];
        }, (array) $this->input('cargos', []));
    }

    private function registrando(): bool
    {
        return $this->route('persona') === null;
    }

    private function nivelDe(mixed $cargo): ?string
    {
        static $niveles = [];
        if (! ctype_digit((string) $cargo)) {
            return null;
        }

        return $niveles[(int) $cargo] ??= DB::table('cargo')->where('carg_id_cargo', (int) $cargo)->value('carg_nivel');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function reglasCargo(): array
    {
        return [
            'persona_existente' => ['nullable', 'integer'],
            'cargos' => ['required', 'array', 'max:'.self::MAXIMO_CARGOS],
            'cargos.*.cargo' => ['required', 'integer', Rule::exists('cargo', 'carg_id_cargo')->where('carg_activo', true)],
            'cargos.*.zona' => ['nullable', 'integer', Rule::exists('zona_regional', 'zore_id_zona_regional')],
            'cargos.*.coordinacion' => ['nullable', 'integer', Rule::exists('coordinacion_nacional', 'cona_id_coordinacion_nacional')->where('cona_activo', true)],
            'cargos.*.fecha_inicio' => ['required', 'date', 'after_or_equal:1950-01-01', 'before_or_equal:today'],
        ];
    }

    /**
     * Cada cargo según la pertenencia, sin repetirse, y que su puesto esté
     * libre en esa fecha. Los mensajes dicen de qué fila ("Cargo 2: ...").
     */
    private function revisarCargo(Validator $validator): void
    {
        if (filled($this->input('persona_existente')) && $this->existente() === null) {
            $validator->errors()->add('persona_existente', 'Esa persona ya no está disponible para registrarla aquí. Vuelve a buscarla.');
        }
        if ($validator->errors()->hasAny(['cargos', 'pertenencia', 'institucion', 'organizacion'])) {
            return;
        }

        $pertenencia = $this->input('pertenencia');
        if ($pertenencia === 'ninguna') {
            $validator->errors()->add('pertenencia', 'Para recibir un cargo debe pertenecer a una institución u organización.');

            return;
        }

        $servicio = app(PersonaService::class);
        $vistos = [];
        foreach ($this->cargos() as $i => $cargo) {
            $n = $i + 1;
            $campo = "cargos.{$i}.cargo";
            if ($validator->errors()->has($campo) || $validator->errors()->has("cargos.{$i}.fecha_inicio")) {
                continue;
            }
            $nivel = $this->nivelDe($cargo['cargo']);
            if ($nivel === 'Institucional' && $pertenencia !== 'institucion') {
                $validator->errors()->add($campo, "Cargo {$n}: un cargo institucional es de la institución a la que pertenece; elige la institución en «Pertenece a».");

                continue;
            }
            if ($nivel === 'Regional' && $cargo['zona'] === null) {
                $validator->errors()->add("cargos.{$i}.zona", "Cargo {$n}: elige la zona regional.");

                continue;
            }

            $clave = implode('|', [$cargo['cargo'], $cargo['zona'], $cargo['coordinacion'], $cargo['institucion']]);
            if (isset($vistos[$clave])) {
                $validator->errors()->add($campo, "Cargo {$n}: es el mismo puesto que el cargo {$vistos[$clave]}.");

                continue;
            }
            $vistos[$clave] = $n;

            $puesto = $servicio->puestoDe($cargo['cargo'], $cargo['zona'], $cargo['coordinacion'], $cargo['institucion']);
            if ($puesto === null) {
                continue; // Se creará al guardar
            }
            if (! DB::table('puesto')->where('pues_id_puesto', $puesto)->value('pues_activo')) {
                $validator->errors()->add($campo, "Cargo {$n}: ese puesto está desactivado. Actívalo en Puestos o elige otro.");
            } elseif (($ocupante = $servicio->ocupanteDesde($puesto, $cargo['fecha_inicio'])) !== null) {
                $validator->errors()->add($campo, "Cargo {$n}: ese puesto ya lo ocupa {$ocupante} en esa fecha. Primero registra que dejó el cargo.");
            }
        }
    }

    /**
     * @return array{institucion: ?int, organizacion: ?int}
     */
    private function pertenencia(): array
    {
        $pertenencia = $this->input('pertenencia');

        return [
            'institucion' => $pertenencia === 'institucion' ? (int) $this->input('institucion') : null,
            'organizacion' => $pertenencia === 'organizacion' ? (int) $this->input('organizacion') : null,
        ];
    }

    private function idTipo(string $nombre): int
    {
        return (int) collect($this->tiposContacto())->firstWhere('tico_nombre', $nombre)->tico_id_tipo_contacto;
    }

    /**
     * @return array<int, object>
     */
    private function tiposContacto(): array
    {
        return $this->tipos ??= DB::table('tipo_contacto')->get()->keyBy('tico_id_tipo_contacto')->all();
    }
}

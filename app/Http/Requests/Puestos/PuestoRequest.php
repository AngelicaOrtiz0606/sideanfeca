<?php

namespace App\Http\Requests\Puestos;

use App\Services\Puestos\PuestoService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Registro y edición de un puesto. Lo que se pide depende del nivel del cargo:
 * - Institucional: institución (y si es el titular de la institución).
 * - Regional: zona; coordinación opcional.
 * - Nacional: coordinación u organización (por ejemplo, Director General de
 *   CACECA), opcionales y nunca las dos.
 * Lo que no aplica al nivel se ignora. Los cargos, coordinaciones e
 * instituciones inactivos no se ofrecen para puestos nuevos.
 */
class PuestoRequest extends FormRequest
{
    private ?object $actual = null;

    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta (can:gestionar_puestos)
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $nivel = $this->nivel();
        $actual = $this->actual();

        return [
            'cargo' => ['required', 'integer', Rule::exists('cargo', 'carg_id_cargo')->where(fn ($q) => $q->where('carg_activo', true)
                ->when($actual, fn ($w) => $w->orWhere('carg_id_cargo', $actual->cargo_id)))],
            'institucion' => [Rule::requiredIf($nivel === 'Institucional'), 'nullable', 'integer',
                Rule::exists('institucion_educativa', 'ined_id_institucion_educativa')->where(fn ($q) => $q->where('ined_activo', true)
                    ->when($actual?->institucion_id, fn ($w, $id) => $w->orWhere('ined_id_institucion_educativa', $id)))],
            'zona' => [Rule::requiredIf($nivel === 'Regional'), 'nullable', 'integer', Rule::exists('zona_regional', 'zore_id_zona_regional')],
            'coordinacion' => ['nullable', 'integer',
                Rule::exists('coordinacion_nacional', 'cona_id_coordinacion_nacional')->where(fn ($q) => $q->where('cona_activo', true)
                    ->when($actual?->coordinacion_id, fn ($w, $id) => $w->orWhere('cona_id_coordinacion_nacional', $id)))],
            'organizacion' => ['nullable', 'integer',
                Rule::exists('organizacion', 'orga_id_organizacion')->where(fn ($q) => $q->where('orga_activo', true)
                    ->when($actual?->organizacion_id, fn ($w, $id) => $w->orWhere('orga_id_organizacion', $id)))],
            'titular' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $datos = $this->datos();
                $actual = $this->actual();
                $servicio = app(PuestoService::class);

                if ($datos['coordinacion'] !== null && $datos['organizacion'] !== null) {
                    $validator->errors()->add('organizacion', 'Un puesto nacional lleva coordinación u organización, no las dos.');

                    return;
                }

                // Con designaciones, el cargo y el ámbito no cambian
                if ($actual !== null && ($motivo = $servicio->motivoParaNoCambiarAmbito((int) $actual->designaciones)) !== null
                    && [$datos['cargo'], $datos['zona'], $datos['coordinacion'], $datos['institucion'], $datos['organizacion']]
                        !== [(int) $actual->cargo_id, $this->entero($actual->zona_id), $this->entero($actual->coordinacion_id), $this->entero($actual->institucion_id), $this->entero($actual->organizacion_id)]) {
                    $validator->errors()->add('cargo', $motivo);

                    return;
                }

                // El mismo puesto no se repite
                $repetido = DB::table('puesto')
                    ->where('pues_id_cargo', $datos['cargo'])
                    ->where(fn ($q) => $datos['zona'] === null ? $q->whereNull('pues_id_zona_regional') : $q->where('pues_id_zona_regional', $datos['zona']))
                    ->where(fn ($q) => $datos['coordinacion'] === null ? $q->whereNull('pues_id_coordinacion_nacional') : $q->where('pues_id_coordinacion_nacional', $datos['coordinacion']))
                    ->where(fn ($q) => $datos['institucion'] === null ? $q->whereNull('pues_id_institucion_educativa') : $q->where('pues_id_institucion_educativa', $datos['institucion']))
                    ->where(fn ($q) => $datos['organizacion'] === null ? $q->whereNull('pues_id_organizacion') : $q->where('pues_id_organizacion', $datos['organizacion']))
                    ->when($actual, fn ($q) => $q->where('pues_id_puesto', '!=', $actual->id))
                    ->value('pues_id_puesto');
                if ($repetido !== null) {
                    $validator->errors()->add('cargo', 'Ese puesto ya existe: «'.$servicio->ambito($servicio->buscar((int) $repetido), true).'».');

                    return;
                }

                // Una institución tiene a lo más un puesto titular
                if ($datos['titular']) {
                    $otro = DB::table('puesto as p')->join('cargo as c', 'c.carg_id_cargo', '=', 'p.pues_id_cargo')
                        ->where('p.pues_id_institucion_educativa', $datos['institucion'])->where('p.pues_titularidad', true)
                        ->when($actual, fn ($q) => $q->where('p.pues_id_puesto', '!=', $actual->id))
                        ->value('c.carg_nombre_m');
                    if ($otro !== null) {
                        $validator->errors()->add('titular', "La institución ya tiene su puesto titular: «{$otro}». Solo puede haber uno.");
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
            'cargo.required' => 'Elige el cargo.',
            'cargo.exists' => 'Elige un cargo activo de la lista.',
            'institucion.required' => 'Elige la institución del puesto.',
            'institucion.exists' => 'Elige una institución activa de la lista.',
            'zona.required' => 'Elige la zona regional del puesto.',
            'zona.exists' => 'Elige una zona de la lista.',
            'coordinacion.exists' => 'Elige una coordinación activa de la lista.',
            'organizacion.exists' => 'Elige una organización activa de la lista.',
        ];
    }

    /**
     * Datos listos para PuestoService: lo que no aplica al nivel del cargo va vacío.
     *
     * @return array{cargo: int, zona: ?int, coordinacion: ?int, institucion: ?int, organizacion: ?int, titular: bool}
     */
    public function datos(): array
    {
        $nivel = $this->nivel();

        return [
            'cargo' => (int) $this->input('cargo'),
            'zona' => $nivel === 'Regional' ? $this->entero($this->input('zona')) : null,
            'coordinacion' => $nivel !== 'Institucional' ? $this->entero($this->input('coordinacion')) : null,
            'institucion' => $nivel === 'Institucional' ? $this->entero($this->input('institucion')) : null,
            'organizacion' => $nivel === 'Nacional' ? $this->entero($this->input('organizacion')) : null,
            'titular' => $nivel === 'Institucional' && $this->boolean('titular'),
        ];
    }

    private function nivel(): ?string
    {
        $cargo = $this->input('cargo');

        return is_scalar($cargo) && ctype_digit((string) $cargo)
            ? DB::table('cargo')->where('carg_id_cargo', (int) $cargo)->value('carg_nivel')
            : null;
    }

    private function actual(): ?object
    {
        $id = $this->route('puesto');

        return $id === null ? null : ($this->actual ??= app(PuestoService::class)->buscar((int) $id));
    }

    private function entero(mixed $valor): ?int
    {
        return is_scalar($valor) && ctype_digit((string) $valor) ? (int) $valor : null;
    }
}

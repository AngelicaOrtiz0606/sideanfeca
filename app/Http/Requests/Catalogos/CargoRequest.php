<?php

namespace App\Http\Requests\Catalogos;

use App\Models\Cargo;
use App\Rules\NoRepetidoEnCatalogo;
use App\Rules\TextoSeguro;
use App\Support\TextoCatalogo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Registro y edición de un cargo.
 * - Nombres en masculino y femenino, sin repetirse (sin importar mayúsculas, acentos ni espacios).
 * - Nivel: Nacional, Regional o Institucional. Si el cargo ya tiene puestos,
 *   el nivel no cambia: las reglas de sus puestos dependen de él.
 */
class CargoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta (can:gestionar_cargos)
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = $this->cargo()?->carg_id_cargo;

        return [
            'masculino' => ['required', 'string', new TextoSeguro, 'max:100',
                new NoRepetidoEnCatalogo('cargo', 'carg_nombre_m', 'carg_id_cargo', $id, 'Ya existe el cargo «:existente».')],
            'femenino' => ['required', 'string', new TextoSeguro, 'max:100',
                new NoRepetidoEnCatalogo('cargo', 'carg_nombre_f', 'carg_id_cargo', $id, 'Ya existe el cargo «:existente».')],
            'nivel' => ['required', Rule::in(Cargo::NIVELES)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $cargo = $this->cargo();

                if ($cargo && ! $validator->errors()->has('nivel') && $this->input('nivel') !== $cargo->carg_nivel
                    && DB::table('puesto')->where('pues_id_cargo', $cargo->carg_id_cargo)->exists()) {
                    $validator->errors()->add('nivel', 'El nivel no se puede cambiar porque el cargo ya tiene puestos.');
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
            'masculino.required' => 'Escribe el nombre del cargo en masculino.',
            'femenino.required' => 'Escribe el nombre del cargo en femenino.',
            'masculino.max' => 'El nombre en masculino puede tener hasta 100 caracteres.',
            'femenino.max' => 'El nombre en femenino puede tener hasta 100 caracteres.',
            'nivel.required' => 'Elige el nivel del cargo.',
            'nivel.in' => 'Elige el nivel del cargo: Nacional, Regional o Institucional.',
        ];
    }

    public function texto(string $campo): string
    {
        return TextoCatalogo::limpiar($this->input($campo));
    }

    public function nivel(): string
    {
        return (string) $this->validated('nivel');
    }

    private function cargo(): ?Cargo
    {
        $cargo = $this->route('cargo');

        return $cargo instanceof Cargo ? $cargo : null;
    }
}

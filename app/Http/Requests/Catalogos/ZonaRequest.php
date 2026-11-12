<?php

namespace App\Http\Requests\Catalogos;

use App\Models\ZonaRegional;
use App\Rules\NoRepetidoEnCatalogo;
use App\Support\TextoCatalogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registro y edición de una zona regional.
 * - Número del 1 al 7, sin repetirse.
 * - Nombre sin repetirse (sin importar mayúsculas, acentos ni espacios).
 * - Entidades federativas que pasan a esta zona (opcional).
 */
class ZonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta (can:gestionar_zonas)
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = $this->zona()?->zore_id_zona_regional;

        return [
            'numero' => ['required', 'integer', 'between:1,7', Rule::unique('zona_regional', 'zore_numero')->ignore($id, 'zore_id_zona_regional')],
            'nombre' => ['required', 'string', 'max:60',
                new NoRepetidoEnCatalogo('zona_regional', 'zore_nombre', 'zore_id_zona_regional', $id, 'Ya existe la zona «:existente».')],
            'entidades' => ['nullable', 'array'],
            'entidades.*' => ['integer', 'distinct', Rule::exists('entidad_federativa', 'enfe_id_entidad_federativa')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero.required' => 'Escribe el número de la zona.',
            'numero.integer' => 'El número de la zona debe ser un número del 1 al 7.',
            'numero.between' => 'El número de la zona debe ser del 1 al 7.',
            'numero.unique' => 'El número ya pertenece a otra zona.',
            'nombre.required' => 'Escribe el nombre de la zona.',
            'nombre.max' => 'El nombre de la zona puede tener hasta 60 caracteres.',
            'entidades.*' => 'Alguna de las entidades elegidas no existe. Recarga la página e inténtalo de nuevo.',
        ];
    }

    public function numero(): int
    {
        return (int) $this->validated('numero');
    }

    public function nombre(): string
    {
        return TextoCatalogo::limpiar($this->input('nombre'));
    }

    /**
     * @return array<int, int>
     */
    public function entidades(): array
    {
        return array_map('intval', $this->validated('entidades') ?? []);
    }

    private function zona(): ?ZonaRegional
    {
        $zona = $this->route('zona');

        return $zona instanceof ZonaRegional ? $zona : null;
    }
}

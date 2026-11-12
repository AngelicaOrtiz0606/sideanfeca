<?php

namespace App\Http\Requests\Catalogos;

use App\Models\CoordinacionNacional;
use App\Rules\NoRepetidoEnCatalogo;
use App\Rules\TextoSeguro;
use App\Support\TextoCatalogo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Registro y edición de una coordinación nacional: nombre (sin repetirse,
 * sin importar mayúsculas, acentos ni espacios) y descripción opcional.
 */
class CoordinacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta (can:gestionar_coordinaciones)
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', new TextoSeguro, 'max:70',
                new NoRepetidoEnCatalogo('coordinacion_nacional', 'cona_nombre', 'cona_id_coordinacion_nacional', $this->coordinacion()?->cona_id_coordinacion_nacional, 'Ya existe la coordinación «:existente».')],
            'descripcion' => ['nullable', 'string', new TextoSeguro, 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre de la coordinación.',
            'nombre.max' => 'El nombre de la coordinación puede tener hasta 70 caracteres.',
            'descripcion.max' => 'La descripción puede tener hasta 100 caracteres.',
        ];
    }

    public function nombre(): string
    {
        return TextoCatalogo::limpiar($this->input('nombre'));
    }

    public function descripcion(): ?string
    {
        $descripcion = TextoCatalogo::limpiar($this->input('descripcion'));

        return $descripcion === '' ? null : $descripcion;
    }

    private function coordinacion(): ?CoordinacionNacional
    {
        $coordinacion = $this->route('coordinacion');

        return $coordinacion instanceof CoordinacionNacional ? $coordinacion : null;
    }
}

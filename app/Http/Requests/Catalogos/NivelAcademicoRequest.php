<?php

namespace App\Http\Requests\Catalogos;

use App\Models\NivelAcademico;
use App\Rules\NoRepetidoEnCatalogo;
use App\Rules\TextoSeguro;
use App\Support\TextoCatalogo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Registro y edición de un grado académico (tabla NIVEL_ACADEMICO): nombre
 * (sin repetirse, sin importar mayúsculas, acentos ni espacios) y sus
 * abreviaturas en masculino y femenino.
 */
class NivelAcademicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta (can:gestionar_grados)
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $grado = $this->route('grado');

        return [
            'nombre' => ['required', 'string', new TextoSeguro, 'max:50',
                new NoRepetidoEnCatalogo('nivel_academico', 'niac_nombre', 'niac_id_nivel_academico', $grado instanceof NivelAcademico ? $grado->niac_id_nivel_academico : null, 'Ya existe el grado «:existente».')],
            'masculina' => ['required', 'string', new TextoSeguro, 'max:10'],
            'femenina' => ['required', 'string', new TextoSeguro, 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre del grado.',
            'nombre.max' => 'El nombre puede tener hasta 50 caracteres.',
            'masculina.required' => 'Escribe la abreviatura en masculino (por ejemplo, Dr.).',
            'femenina.required' => 'Escribe la abreviatura en femenino (por ejemplo, Dra.).',
            'masculina.max' => 'La abreviatura puede tener hasta 10 caracteres.',
            'femenina.max' => 'La abreviatura puede tener hasta 10 caracteres.',
        ];
    }

    public function texto(string $campo): string
    {
        return TextoCatalogo::limpiar($this->input($campo));
    }
}

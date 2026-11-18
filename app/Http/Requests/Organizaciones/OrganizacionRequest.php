<?php

namespace App\Http\Requests\Organizaciones;

use App\Rules\NoRepetidoEnCatalogo;
use App\Rules\TextoSeguro;
use App\Support\TextoCatalogo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registro y edición de una organización: nombre (sin repetirse, sin importar
 * mayúsculas, acentos ni espacios), domicilio obligatorio y sitio web opcional.
 */
class OrganizacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta (can:gestionar_instituciones)
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = $this->route('organizacion');

        return [
            'nombre' => ['required', 'string', new TextoSeguro, 'max:150',
                new NoRepetidoEnCatalogo('organizacion', 'orga_nombre', 'orga_id_organizacion', $id === null ? null : (int) $id, 'Ya existe la organización «:existente».')],
            'sitio_web' => ['nullable', 'string', 'max:200', 'url:http,https'],
            'colonia' => ['required', 'integer', Rule::exists('colonia', 'colo_id_colonia')],
            'calle' => ['required', 'string', new TextoSeguro, 'max:60'],
            'num_ext' => ['required', 'string', new TextoSeguro, 'max:10'],
            'num_int' => ['nullable', 'string', new TextoSeguro, 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre de la organización.',
            'nombre.max' => 'El nombre puede tener hasta 150 caracteres.',
            'sitio_web.url' => 'El sitio web debe empezar con http:// o https://, por ejemplo https://www.caceca.org.',
            'colonia.required' => 'Escribe el código postal y elige la colonia.',
            'colonia.exists' => 'Elige una colonia de la lista.',
            'calle.required' => 'Escribe la calle.',
            'num_ext.required' => 'Escribe el número exterior.',
        ];
    }

    /**
     * @return array{nombre: string, sitio_web: ?string, domicilio: array{colonia: int, calle: string, num_ext: string, num_int: ?string}}
     */
    public function datos(): array
    {
        $datos = $this->validated();

        return [
            'nombre' => TextoCatalogo::limpiar($datos['nombre']),
            'sitio_web' => ($datos['sitio_web'] ?? null) ?: null,
            'domicilio' => [
                'colonia' => (int) $datos['colonia'],
                'calle' => TextoCatalogo::limpiar($datos['calle']),
                'num_ext' => TextoCatalogo::limpiar($datos['num_ext']),
                'num_int' => TextoCatalogo::limpiar($datos['num_int'] ?? null) ?: null,
            ],
        ];
    }
}

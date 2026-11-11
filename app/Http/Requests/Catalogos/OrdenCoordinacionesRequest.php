<?php

namespace App\Http\Requests\Catalogos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Orden nuevo de las coordinaciones: la lista completa de identificadores.
 * Que estén todas y una sola vez lo revisa también reordenar_coordinaciones.
 */
class OrdenCoordinacionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'orden' => ['required', 'array', 'min:1'],
            'orden.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'orden.*' => 'La lista no es válida. Recarga la página e inténtalo de nuevo.',
            'orden.required' => 'La lista no es válida. Recarga la página e inténtalo de nuevo.',
        ];
    }

    /**
     * @return array<int, int>
     */
    public function ids(): array
    {
        return array_values(array_map('intval', $this->validated('orden')));
    }
}

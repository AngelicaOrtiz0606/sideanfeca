<?php

namespace App\Http\Requests\Catalogos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Confirmación de un borrado: la bitácora exige uno de los motivos permitidos.
 */
class EliminarRequest extends FormRequest
{
    /** Motivos que acepta BITACORA (CKC_BITA_MOTIVO), salvo Cancelación ARCO, que es solo para personas. */
    public const MOTIVOS = ['Error de captura', 'Decisión de la organización', 'Duplicado'];

    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', Rule::in(self::MOTIVOS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Elige el motivo del borrado.',
            'motivo.in' => 'Elige uno de los motivos de la lista.',
        ];
    }

    public function motivo(): string
    {
        return (string) $this->validated('motivo');
    }
}

<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Formulario de contraseña nueva que se abre desde el enlace del correo.
 * Sigue la regla única del sistema (Password::defaults()).
 */
class RestablecerContraseniaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'contrasenia' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contrasenia.required' => 'Escribe tu contraseña nueva.',
            'contrasenia.confirmed' => 'Las dos contraseñas no coinciden. Escríbelas de nuevo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'contrasenia' => 'la contraseña nueva',
        ];
    }
}

<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Formulario para pedir un código de recuperación. Límites:
 * - 3 solicitudes cada 15 minutos por correo e IP;
 * - 5 solicitudes por hora desde una misma IP, con cualquier correo (para que
 *   nadie use el sistema para llenar de correos a otras personas).
 */
class SolicitarRecuperacionRequest extends FormRequest
{
    public const SOLICITUDES_PERMITIDAS = 3;

    public const VENTANA_SEGUNDOS = 900;

    public const SOLICITUDES_POR_IP = 5;

    public const VENTANA_IP_SEGUNDOS = 3600;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'correo' => ['required', 'string', 'email', 'max:254'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'correo.required' => 'Escribe tu correo electrónico.',
            'correo.email' => 'Escribe un correo electrónico válido.',
            'correo.max' => 'El correo electrónico es demasiado largo.',
        ];
    }

    public function correo(): string
    {
        return trim((string) $this->validated('correo'));
    }

    /**
     * @throws ValidationException
     */
    public function asegurarLimite(): void
    {
        foreach ([[$this->claveDeLimite(), self::SOLICITUDES_PERMITIDAS], [$this->claveDeIp(), self::SOLICITUDES_POR_IP]] as [$clave, $maximo]) {
            if (RateLimiter::tooManyAttempts($clave, $maximo)) {
                $minutos = (int) ceil(RateLimiter::availableIn($clave) / 60);

                throw ValidationException::withMessages([
                    'correo' => "Ya pediste varios códigos. Espera {$minutos} minutos y vuelve a intentarlo.",
                ]);
            }
        }

        RateLimiter::hit($this->claveDeLimite(), self::VENTANA_SEGUNDOS);
        RateLimiter::hit($this->claveDeIp(), self::VENTANA_IP_SEGUNDOS);
    }

    private function claveDeIp(): string
    {
        return 'recuperacion-ip|'.$this->ip();
    }

    private function claveDeLimite(): string
    {
        return 'recuperacion|'.Str::transliterate(Str::lower(trim((string) $this->input('correo')))).'|'.$this->ip();
    }
}

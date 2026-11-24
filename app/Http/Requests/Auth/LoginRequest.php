<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Formulario de inicio de sesión: valida los campos y limita los intentos:
 * - 5 por minuto para cada combinación de correo e IP (adivinar una contraseña);
 * - 20 fallidos cada 10 minutos desde una misma IP, con cualquier correo
 *   (probar una contraseña común contra muchas cuentas).
 *
 * Los intentos bloqueados por el límite no se evalúan ni se registran en
 * LOG_ACCESO (decisión del lote 3, opción A).
 */
class LoginRequest extends FormRequest
{
    public const INTENTOS_POR_MINUTO = 5;

    public const FALLIDOS_POR_IP = 20;

    public const VENTANA_IP_SEGUNDOS = 600;

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
            'contrasenia' => ['required', 'string', 'max:255'],
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
            'contrasenia.required' => 'Escribe tu contraseña.',
            'contrasenia.max' => 'La contraseña es demasiado larga.',
        ];
    }

    public function correo(): string
    {
        return (string) $this->validated('correo');
    }

    public function contrasenia(): string
    {
        return (string) $this->validated('contrasenia');
    }

    /**
     * Detiene el intento si ya se alcanzó el límite.
     *
     * @throws ValidationException
     */
    public function asegurarQueNoEsteBloqueado(): void
    {
        $clave = match (true) {
            RateLimiter::tooManyAttempts($this->claveDeLimite(), self::INTENTOS_POR_MINUTO) => $this->claveDeLimite(),
            RateLimiter::tooManyAttempts($this->claveDeIp(), self::FALLIDOS_POR_IP) => $this->claveDeIp(),
            default => null,
        };

        if ($clave === null) {
            return;
        }

        $segundos = RateLimiter::availableIn($clave);
        $espera = $segundos > 90 ? ((int) ceil($segundos / 60)).' minutos' : "{$segundos} segundos";

        throw ValidationException::withMessages([
            'correo' => "Demasiados intentos. Espera {$espera} e inténtalo de nuevo.",
        ]);
    }

    public function registrarIntentoFallido(): void
    {
        RateLimiter::hit($this->claveDeLimite(), 60);
        RateLimiter::hit($this->claveDeIp(), self::VENTANA_IP_SEGUNDOS);
    }

    /** Al entrar se limpian los intentos de ese correo; los de la IP siguen contando. */
    public function limpiarIntentos(): void
    {
        RateLimiter::clear($this->claveDeLimite());
    }

    private function claveDeIp(): string
    {
        return 'login-ip|'.$this->ip();
    }

    private function claveDeLimite(): string
    {
        return 'login|'.Str::transliterate(Str::lower(trim((string) $this->input('correo')))).'|'.$this->ip();
    }
}

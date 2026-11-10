<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * La contraseña debe tener al menos una letra mayúscula (incluye Á, É, Ñ...).
 * Forma parte de la regla única de contraseña (AppServiceProvider).
 */
class TieneMayuscula implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/\p{Lu}/u', $value) !== 1) {
            $fail(':Attribute debe tener al menos una letra mayúscula.');
        }
    }
}

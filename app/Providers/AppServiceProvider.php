<?php

namespace App\Providers;

use App\Rules\TieneMayuscula;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Regla única de contraseña para TODAS las cuentas (superadmin, admin,
        // recuperación y gestión de usuarios): al menos 8 caracteres, una
        // mayúscula, un número y un carácter especial. Se usa con Password::defaults().
        // El máximo de 72 caracteres es el límite de bcrypt.
        Password::defaults(fn () => Password::min(8)
            ->max(72)
            ->numbers()
            ->symbols()
            ->rules([new TieneMayuscula]));
    }
}

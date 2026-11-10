<?php

namespace App\Providers;

use App\Enums\Privilegio;
use App\Models\Usuario;
use App\Rules\TieneMayuscula;
use App\Services\PermisosService;
use Illuminate\Support\Facades\Gate;
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

        // Un Gate por privilegio (M2). La respuesta sale de la función SQL
        // cuenta_tiene_privilegio, así que los permisos viven en ROL_PRIVILEGIO.
        // No hay excepción para el Superadmin: tiene todo porque su rol tiene
        // todos los privilegios.
        foreach (Privilegio::cases() as $privilegio) {
            Gate::define(
                $privilegio->value,
                fn (Usuario $cuenta): bool => $this->app->make(PermisosService::class)->tiene($cuenta, $privilegio),
            );
        }
    }
}

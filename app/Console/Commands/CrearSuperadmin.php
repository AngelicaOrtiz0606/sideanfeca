<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use App\Services\CuentaInicialService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

/*
 * Crea el primer superadministrador de SIDEANFECA.
 *
 * Uso (después de php artisan migrate y php artisan db:seed):
 *   php artisan sideanfeca:crear-superadmin
 *
 * - Solo funciona si todavía no existe ninguna cuenta.
 * - La contraseña se escribe oculta y nunca se pasa como opción del
 *   comando, para que no quede en el historial de la terminal.
 */
class CrearSuperadmin extends Command
{
    protected $signature = 'sideanfeca:crear-superadmin';

    protected $description = 'Crea el primer superadministrador (solo si no existe ninguna cuenta)';

    public function handle(CuentaInicialService $cuentas): int
    {
        if ($cuentas->yaExistenCuentas()) {
            $this->error('Ya existen cuentas. Crea las demás desde la gestión de usuarios.');

            return self::FAILURE;
        }

        if (! $cuentas->existenRoles()) {
            $this->error('No existe el rol Superadministrador. Corre primero: php artisan db:seed');

            return self::FAILURE;
        }

        $this->info('Primer superadministrador de SIDEANFECA');

        $datos = [
            'nombre' => trim((string) $this->ask('Nombre')),
            'primer_apellido' => trim((string) $this->ask('Primer apellido')),
            'segundo_apellido' => trim((string) $this->ask('Segundo apellido (opcional, Enter para omitir)', '')),
            'genero' => $this->choice('Género', ['F', 'M']),
            'correo' => trim((string) $this->ask('Correo electrónico')),
            'contrasenia' => (string) $this->secret('Contraseña (al menos 8 caracteres, una mayúscula, un número y un carácter especial)'),
            'contrasenia_confirmation' => (string) $this->secret('Escribe la contraseña otra vez'),
        ];

        $validacion = Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:60'],
            'primer_apellido' => ['required', 'string', 'max:45'],
            'segundo_apellido' => ['nullable', 'string', 'max:45'],
            'genero' => ['required', 'in:F,M'],
            'correo' => ['required', 'string', 'email', 'max:254'],
            'contrasenia' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        if ($validacion->fails()) {
            $this->error('No se creó la cuenta. Corrige lo siguiente y vuelve a correr el comando:');
            foreach ($validacion->errors()->all() as $mensaje) {
                $this->line("  - {$mensaje}");
            }

            return self::FAILURE;
        }

        if (Usuario::porCorreo($datos['correo'])->exists()) {
            $this->error('Ese correo ya está registrado.');

            return self::FAILURE;
        }

        $this->table(['Dato', 'Valor'], [
            ['Nombre', trim("{$datos['nombre']} {$datos['primer_apellido']} {$datos['segundo_apellido']}")],
            ['Género', $datos['genero']],
            ['Correo', $datos['correo']],
            ['Rol', 'Superadministrador'],
        ]);

        if (! $this->confirm('¿Crear la cuenta?')) {
            $this->warn('Cancelado. No se guardó nada.');

            return self::FAILURE;
        }

        try {
            $cuenta = $cuentas->crearSuperadmin([
                'nombre' => $datos['nombre'],
                'primer_apellido' => $datos['primer_apellido'],
                'segundo_apellido' => $datos['segundo_apellido'] === '' ? null : $datos['segundo_apellido'],
                'genero' => $datos['genero'],
                'correo' => $datos['correo'],
                'contrasenia' => $datos['contrasenia'],
            ]);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Cuenta creada (#{$cuenta->usua_id_usuario}). Ya puedes iniciar sesión con {$cuenta->usua_correo}.");

        return self::SUCCESS;
    }
}

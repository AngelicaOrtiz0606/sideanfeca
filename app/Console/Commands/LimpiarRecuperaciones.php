<?php

namespace App\Console\Commands;

use App\Services\RecuperacionService;
use Illuminate\Console\Command;

/*
 * Borra las solicitudes de recuperación vencidas o ya usadas
 * (función SQL limpiar_recuperaciones). Corre cada hora con el
 * programador de Laravel; ver routes/console.php.
 */
class LimpiarRecuperaciones extends Command
{
    protected $signature = 'sideanfeca:limpiar-recuperaciones';

    protected $description = 'Borra las solicitudes de recuperación de contraseña vencidas o ya usadas';

    public function handle(RecuperacionService $recuperacion): int
    {
        $borradas = $recuperacion->limpiar();

        $this->info("Solicitudes borradas: {$borradas}");

        return self::SUCCESS;
    }
}

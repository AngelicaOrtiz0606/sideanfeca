<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Tareas programadas. En el servidor, cron llama cada minuto a
 * "php artisan schedule:run" y Laravel decide qué toca correr.
 */

// Criterio de M1: limpiar cada hora los códigos de recuperación vencidos o usados
Schedule::command('sideanfeca:limpiar-recuperaciones')
    ->hourly()
    ->withoutOverlapping();

<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecuperacionController;
use Illuminate\Support\Facades\Route;

// Hasta que exista el sitio público (M10), la raíz lleva al inicio de sesión
Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'mostrar'])->name('login');
    Route::post('/login', [LoginController::class, 'iniciar'])->name('login.iniciar');

    // Recuperación de contraseña con un enlace enviado por correo
    Route::get('/recuperar-contrasenia', [RecuperacionController::class, 'mostrarSolicitud'])->name('contrasenia.solicitar');
    Route::post('/recuperar-contrasenia', [RecuperacionController::class, 'enviar'])->name('contrasenia.enviar');

    Route::middleware('throttle:10,1')
        ->where(['solicitud' => '[0-9]+', 'token' => '[A-Za-z0-9]{64}'])
        ->group(function () {
            Route::get('/recuperar-contrasenia/{solicitud}/{token}', [RecuperacionController::class, 'mostrarRestablecer'])
                ->name('contrasenia.restablecer');
            Route::post('/recuperar-contrasenia/{solicitud}/{token}', [RecuperacionController::class, 'restablecer'])
                ->name('contrasenia.actualizar');
        });
});

Route::middleware(['auth', 'cuenta.activa'])->group(function () {
    // Página temporal: la pantalla de inicio de la sección 10 llega en otro lote de M1
    Route::view('/inicio', 'inicio')->name('inicio');

    Route::post('/logout', [LoginController::class, 'cerrar'])->name('logout');
});

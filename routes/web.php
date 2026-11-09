<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// Hasta que exista el sitio público (M10), la raíz lleva al inicio de sesión
Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'mostrar'])->name('login');
    Route::post('/login', [LoginController::class, 'iniciar'])->name('login.iniciar');

    // Página temporal: el flujo de recuperación por correo se programa en su propio lote de M1
    Route::view('/recuperar-contrasenia', 'auth.recuperar')->name('contrasenia.solicitar');
});

Route::middleware(['auth', 'cuenta.activa'])->group(function () {
    // Página temporal: la pantalla de inicio de la sección 10 llega en otro lote de M1
    Route::view('/inicio', 'inicio')->name('inicio');

    Route::post('/logout', [LoginController::class, 'cerrar'])->name('logout');
});

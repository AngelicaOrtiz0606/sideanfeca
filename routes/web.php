<?php

use App\Enums\Privilegio;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecuperacionController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\PantallaPendienteController;
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

// auth.session: si la contraseña cambia (por ejemplo, con el enlace de
// recuperación), las sesiones abiertas en otras computadoras se cierran
Route::middleware(['auth', 'auth.session', 'cuenta.activa'])->group(function () {
    // Pantalla de inicio (sección 10)
    Route::get('/inicio', InicioController::class)->name('inicio');

    Route::post('/logout', [LoginController::class, 'cerrar'])->name('logout');

    // Pantallas con privilegio: quien no lo tiene recibe 403.
    // Mientras su módulo no exista, muestran la pantalla provisional.
    Route::get('/bitacora', PantallaPendienteController::class)
        ->defaults('titulo', 'Bitácora')
        ->middleware(Privilegio::VerBitacora->middleware())
        ->name('bitacora.index');

    Route::get('/usuarios', PantallaPendienteController::class)
        ->defaults('titulo', 'Usuarios')
        ->middleware(Privilegio::GestionarUsuarios->middleware())
        ->name('usuarios.index');
});

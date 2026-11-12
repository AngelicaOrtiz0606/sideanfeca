<?php

use App\Enums\Privilegio;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecuperacionController;
use App\Http\Controllers\Catalogos\CargoController;
use App\Http\Controllers\Catalogos\CodigoPostalController;
use App\Http\Controllers\Catalogos\CoordinacionController;
use App\Http\Controllers\Catalogos\NivelAcademicoController;
use App\Http\Controllers\Catalogos\ZonaController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\PantallaPendienteController;
use Illuminate\Support\Facades\Route;

// Los identificadores en las direcciones son siempre números
Route::pattern('zona', '[0-9]+');
Route::pattern('coordinacion', '[0-9]+');
Route::pattern('cargo', '[0-9]+');
Route::pattern('grado', '[0-9]+');
Route::pattern('codigo', '[0-9]{5}');

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

    // Catálogos (M3)
    // Catálogo de SEPOMEX: solo se lee, para llenar domicilios desde el código postal (M4)
    Route::get('/codigos-postales/{codigo}', CodigoPostalController::class)->name('codigos-postales.show');

    Route::resource('zonas', ZonaController::class)
        ->parameters(['zonas' => 'zona'])
        ->except(['create', 'edit'])
        ->middleware(Privilegio::GestionarZonas->middleware());

    Route::middleware(Privilegio::GestionarCoordinaciones->middleware())->group(function () {
        Route::put('/coordinaciones/orden', [CoordinacionController::class, 'guardarOrden'])->name('coordinaciones.guardar-orden');
        Route::patch('/coordinaciones/{coordinacion}/estado', [CoordinacionController::class, 'cambiarEstado'])->name('coordinaciones.estado');
        Route::resource('coordinaciones', CoordinacionController::class)
            ->parameters(['coordinaciones' => 'coordinacion'])
            ->except(['create', 'edit']);
    });

    Route::middleware(Privilegio::GestionarCargos->middleware())->group(function () {
        Route::patch('/cargos/{cargo}/estado', [CargoController::class, 'cambiarEstado'])->name('cargos.estado');
        Route::resource('cargos', CargoController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware(Privilegio::GestionarGrados->middleware())->group(function () {
        // Grados académicos (tabla NIVEL_ACADEMICO)
        Route::patch('/grados-academicos/{grado}/estado', [NivelAcademicoController::class, 'cambiarEstado'])->name('grados.estado');
        Route::resource('grados-academicos', NivelAcademicoController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['grados-academicos' => 'grado'])
            ->names('grados');
    });

    Route::get('/usuarios', PantallaPendienteController::class)
        ->defaults('titulo', 'Usuarios')
        ->middleware(Privilegio::GestionarUsuarios->middleware())
        ->name('usuarios.index');
});

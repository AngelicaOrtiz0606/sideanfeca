<?php

use App\Enums\Privilegio;
use App\Http\Controllers\Auth\ConfirmarContraseniaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecuperacionController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\Catalogos\CargoController;
use App\Http\Controllers\Catalogos\CodigoPostalController;
use App\Http\Controllers\Catalogos\CoordinacionController;
use App\Http\Controllers\Catalogos\NivelAcademicoController;
use App\Http\Controllers\Catalogos\ZonaController;
use App\Http\Controllers\Designaciones\DesignacionController;
use App\Http\Controllers\Directorios\ConsultaDirectorioController;
use App\Http\Controllers\Directorios\OrdenDirectorioController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\Instituciones\InstitucionController;
use App\Http\Controllers\Organizaciones\OrganizacionController;
use App\Http\Controllers\Personas\PersonaBorradoController;
use App\Http\Controllers\Personas\PersonaController;
use App\Http\Controllers\Puestos\PuestoController;
use App\Http\Controllers\Usuarios\CuentaController;
use App\Http\Controllers\Usuarios\UsuarioController;
use Illuminate\Support\Facades\Route;

// Los identificadores en las direcciones son siempre números
Route::pattern('zona', '[0-9]+');
Route::pattern('coordinacion', '[0-9]+');
Route::pattern('cargo', '[0-9]+');
Route::pattern('grado', '[0-9]+');
Route::pattern('institucion', '[0-9]+');
Route::pattern('organizacion', '[0-9]+');
Route::pattern('puesto', '[0-9]+');
Route::pattern('persona', '[0-9]+');
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
    // Mi actividad: la bitácora de lo que hizo la propia cuenta (cualquier cuenta)
    Route::get('/mi-actividad', [BitacoraController::class, 'propia'])->name('actividad.index');
    Route::get('/mi-actividad/csv', [BitacoraController::class, 'propiaCsv'])->name('actividad.csv');

    // Bitácora completa (solo superadministrador): solo se consulta
    Route::middleware(Privilegio::VerBitacora->middleware())->group(function () {
        Route::get('/bitacora', [BitacoraController::class, 'index'])->name('bitacora.index');
        Route::get('/bitacora/csv', [BitacoraController::class, 'csv'])->name('bitacora.csv');
    });

    // Instituciones (M4): pantalla propia para registrar, consultar y editar
    Route::middleware(Privilegio::GestionarInstituciones->middleware())->group(function () {
        Route::patch('/instituciones/{institucion}/estado', [InstitucionController::class, 'cambiarEstado'])->name('instituciones.estado');
        Route::patch('/instituciones/{institucion}/razon-social', [InstitucionController::class, 'cambiarRazonSocial'])->name('instituciones.razon-social');
        Route::patch('/instituciones/{institucion}/participacion', [InstitucionController::class, 'cambiarParticipacion'])->name('instituciones.participacion');
        Route::resource('instituciones', InstitucionController::class)
            ->parameters(['instituciones' => 'institucion'])
            ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

        // Organizaciones (por ejemplo, CACECA): todo desde la lista, en ventanas
        Route::patch('/organizaciones/{organizacion}/estado', [OrganizacionController::class, 'cambiarEstado'])->name('organizaciones.estado');
        Route::resource('organizaciones', OrganizacionController::class)
            ->parameters(['organizaciones' => 'organizacion'])
            ->only(['index', 'store', 'update', 'destroy']);
    });

    // Personas (M5): lista, consulta (solo lectura) y edición con el formulario completo
    Route::middleware(Privilegio::GestionarPersonas->middleware())->group(function () {
        Route::get('/personas', [PersonaController::class, 'index'])->name('personas.index');
        Route::get('/personas/create', [PersonaController::class, 'create'])->name('personas.create');
        Route::get('/personas/coincidencias', [PersonaController::class, 'coincidencias'])->name('personas.coincidencias');
        Route::get('/personas/titular-de/{institucion}', [PersonaController::class, 'titularDe'])->whereNumber('institucion')->name('personas.titular');
        Route::post('/personas', [PersonaController::class, 'store'])->name('personas.store');
        Route::get('/personas/{persona}', [PersonaController::class, 'show'])->name('personas.show');
        Route::get('/personas/{persona}/edit', [PersonaController::class, 'edit'])->name('personas.edit');
        Route::put('/personas/{persona}', [PersonaController::class, 'update'])->name('personas.update');
        // Borrar (M8): sin cuenta se elimina; con cuenta se oculta
        Route::delete('/personas/{persona}', [PersonaBorradoController::class, 'destroy'])->name('personas.destroy');
    });

    // Personas ocultas y cancelación ARCO (M8): solo el superadministrador
    Route::middleware([Privilegio::GestionarPersonas->middleware(), Privilegio::GestionarUsuarios->middleware()])->group(function () {
        Route::get('/personas-ocultas', [PersonaBorradoController::class, 'ocultas'])->name('personas.ocultas');
        Route::patch('/personas/{persona}/mostrar', [PersonaBorradoController::class, 'mostrar'])->name('personas.mostrar');
        Route::middleware(Privilegio::EliminarDefinitivo->middleware())->group(function () {
            Route::delete('/personas/{persona}/definitiva', [PersonaBorradoController::class, 'definitiva'])->name('personas.definitiva');
            Route::delete('/personas/{persona}/arco', [PersonaBorradoController::class, 'arco'])->name('personas.arco');
        });
    });

    // Designaciones (M6) de una persona, desde su ficha: agregar, terminar (deja el puesto) y borrar la errónea
    Route::middleware([Privilegio::GestionarPersonas->middleware(), Privilegio::GestionarDesignaciones->middleware()])->group(function () {
        Route::get('/personas/{persona}/designaciones/create', [DesignacionController::class, 'create'])->name('personas.designaciones.create');
        Route::post('/personas/{persona}/designaciones', [DesignacionController::class, 'store'])->name('personas.designaciones.store');
        Route::patch('/personas/{persona}/designaciones/{designacion}/terminar', [DesignacionController::class, 'terminar'])
            ->whereNumber('designacion')->name('personas.designaciones.terminar');
        Route::delete('/personas/{persona}/designaciones/{designacion}', [DesignacionController::class, 'destroy'])
            ->whereNumber('designacion')->name('personas.designaciones.destroy');
    });

    // Consultar directorios (M10): cada directorio en un año, para ver, copiar correos o exportar a CSV
    Route::middleware(Privilegio::VerDirectorios->middleware())->group(function () {
        Route::get('/directorios', [ConsultaDirectorioController::class, 'index'])->name('directorios.index');
        Route::get('/directorios/csv', [ConsultaDirectorioController::class, 'csv'])->name('directorios.csv');
    });

    // Directorios de puestos (M7): qué puestos aparecen en cada uno y en qué orden
    Route::middleware(Privilegio::GestionarDirectorios->middleware())->group(function () {
        Route::get('/directorios/orden', [OrdenDirectorioController::class, 'index'])->name('directorios.orden');
        Route::post('/directorios/{tipo}/puestos', [OrdenDirectorioController::class, 'agregar'])->whereNumber('tipo')->name('directorios.agregar');
        Route::delete('/directorios/{tipo}/puestos/{puesto}', [OrdenDirectorioController::class, 'quitar'])->whereNumber('tipo')->name('directorios.quitar');
        Route::put('/directorios/{tipo}/orden', [OrdenDirectorioController::class, 'guardarOrden'])->whereNumber('tipo')->name('directorios.guardar-orden');
    });

    // Puestos (M6): un cargo en un ámbito concreto; todo desde la lista, en ventanas
    Route::middleware(Privilegio::GestionarPuestos->middleware())->group(function () {
        Route::patch('/puestos/{puesto}/estado', [PuestoController::class, 'cambiarEstado'])->name('puestos.estado');
        Route::resource('puestos', PuestoController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    });

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

    // Usuarios (solo superadministrador): cuentas de las personas; no se borran, se desactivan
    // Crear, editar, activar o desactivar una cuenta pide confirmar la contraseña (15 minutos)
    Route::middleware(Privilegio::GestionarUsuarios->middleware())->group(function () {
        Route::get('/usuarios/coincidencias', [UsuarioController::class, 'coincidencias'])->name('usuarios.coincidencias');
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::middleware('contrasenia.confirmada')->group(function () {
            Route::patch('/usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->whereNumber('usuario')->name('usuarios.estado');
            Route::resource('usuarios', UsuarioController::class)->only(['create', 'store', 'edit', 'update'])->whereNumber('usuario');
        });
    });

    Route::get('/confirmar-contrasenia', [ConfirmarContraseniaController::class, 'mostrar'])->name('contrasenia.confirmar');
    Route::post('/confirmar-contrasenia', [ConfirmarContraseniaController::class, 'confirmar'])->name('contrasenia.confirmar.guardar');

    // Cambiar contraseña: cualquier cuenta, la propia
    Route::get('/cuenta/contrasenia', [CuentaController::class, 'editarContrasenia'])->name('cuenta.contrasenia');
    Route::put('/cuenta/contrasenia', [CuentaController::class, 'actualizarContrasenia'])->name('cuenta.contrasenia.actualizar');
});

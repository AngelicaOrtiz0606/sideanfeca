<?php

use App\Http\Middleware\AsegurarCuentaActiva;
use App\Http\Middleware\EncabezadosSeguridad;
use App\Http\Middleware\SinCacheConSesion;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'cuenta.activa' => AsegurarCuentaActiva::class,
        ]);

        // Encabezados de seguridad del navegador en todas las pantallas, y sin
        // copias guardadas (la flecha de regreso no muestra pantallas viejas)
        $middleware->web(append: [EncabezadosSeguridad::class, SinCacheConSesion::class]);

        // Quien no ha iniciado sesión va al login; quien ya la inició, al inicio
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('inicio'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Las contraseñas nunca se guardan en la sesión al regresar al formulario con errores
        $exceptions->dontFlash([
            'contrasenia',
            'contrasenia_confirmation',
            'contrasenia_actual',
        ]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // La base rechazó un dato repetido que la validación no alcanzó a ver
        // (dos personas guardando lo mismo al mismo tiempo): se regresa a la
        // pantalla con lo escrito y un mensaje claro, en vez de una página de error.
        // La transacción ya se deshizo, bitácora incluida.
        $exceptions->render(function (UniqueConstraintViolationException $error, Request $request) {
            if ($request->expectsJson() || $request->isMethod('GET')) {
                return null;
            }

            return back()
                ->withInput($request->except(['contrasenia', 'contrasenia_confirmation', 'contrasenia_actual']))
                ->withErrors(['general' => 'Alguien acaba de registrar ese mismo dato. Revisa la lista e inténtalo de nuevo.']);
        });

        // La base rechazó un borrado porque, en ese momento, alguien le agregó
        // algo relacionado (llave foránea, SQLSTATE 23503).
        $exceptions->render(function (QueryException $error, Request $request) {
            if ($request->expectsJson() || $request->isMethod('GET') || (string) $error->getCode() !== '23503') {
                return null;
            }

            return back()->with('aviso', 'No se pudo completar porque el registro ya tiene datos relacionados. Revisa la lista.');
        });
    })->create();

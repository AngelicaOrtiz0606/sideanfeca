{{--
    Layout de las pantallas públicas: inicio de sesión, recuperación de
    contraseña y, en M10, los directorios públicos.
    Uso: @extends('layouts.publico'), con @section('titulo') y @section('contenido').

    Las pantallas de acceso (login y recuperación) usan @section('acceso', true):
    no llevan encabezado y se muestran divididas en dos paneles.
--}}
@php($esAcceso = View::hasSection('acceso'))
<!DOCTYPE html>
<html lang="es" id="inicio-pagina">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    {{-- Los enlaces de recuperación llevan un token: no se comparte la dirección con otros sitios --}}
    <meta name="referrer" content="no-referrer">
    <title>@yield('titulo', 'SIDEANFECA') · SIDEANFECA</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo_anfeca.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="cuerpo {{ $esAcceso ? 'cuerpo--acceso' : '' }}">
    <a href="#contenido" class="saltar">Saltar al contenido</a>

    @if ($esAcceso)
        <div class="acceso">
            <x-acceso-marca />

            <main id="contenido" class="acceso__panel" tabindex="-1">
                <div class="acceso__formulario">
                    @yield('contenido')
                </div>
            </main>
        </div>

        <x-footer class="pie--centrado" />
    @else
        <x-header :con-menu="false" />

        <main id="contenido" class="contenido contenido--publico" tabindex="-1">
            @yield('contenido')
        </main>

        <x-footer class="pie--centrado" />
    @endif

    <x-boton-subir />
</body>
</html>

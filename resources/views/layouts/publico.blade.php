{{--
    Layout de las pantallas públicas: inicio de sesión, recuperación de
    contraseña y, en M10, los directorios públicos.
    Uso: @extends('layouts.publico'), con @section('titulo') y @section('contenido').
--}}
<!DOCTYPE html>
<html lang="es" id="inicio-pagina">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('titulo', 'SIDEANFECA') · SIDEANFECA</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo_anfeca.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="cuerpo">
    <a href="#contenido" class="saltar">Saltar al contenido</a>

    {{-- Una página puede omitir el encabezado con @section('sin_encabezado', true) --}}
    @unless (View::hasSection('sin_encabezado'))
        <x-header :con-menu="false" />
    @endunless

    <main id="contenido" class="@yield('clase_contenido', 'contenido contenido--publico')" tabindex="-1">
        @yield('contenido')
    </main>

    <x-footer class="pie--centrado" />
</body>
</html>

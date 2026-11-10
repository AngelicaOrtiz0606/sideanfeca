{{--
    Layout de las pantallas internas (requieren sesión).
    Uso en una página:
        @extends('layouts.admin')
        @section('titulo', 'Nombre de la pantalla')
        @section('contenido') ... @endsection
--}}
<!DOCTYPE html>
<html lang="es" id="inicio-pagina">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('titulo', 'Inicio') · SIDEANFECA</title>
    <link rel="icon" type="image/png" href="{{ imagen('logo') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="cuerpo" data-con-sesion>
    <a href="#contenido" class="saltar">Saltar al contenido</a>

    <x-header />

    <div class="estructura">
        <x-menu />

        <div class="estructura__principal">
            <main id="contenido" class="contenido" tabindex="-1">
                @yield('contenido')
            </main>

            <x-footer />
        </div>
    </div>

    <x-boton-subir />
</body>
</html>

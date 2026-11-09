@extends('layouts.admin')

@section('titulo', 'Inicio')

@section('contenido')
    {{-- Página temporal. La pantalla de inicio de la sección 10 (saludo,
         contadores y bitácora) la reemplaza en el siguiente lote de M1. --}}
    <h1 class="pagina__titulo">Inicio</h1>
    <p class="pagina__texto">
        Sesión iniciada. Aquí aparecerán el saludo, los contadores de instituciones y
        coordinaciones y, para el superadmin, la bitácora reciente.
    </p>
@endsection

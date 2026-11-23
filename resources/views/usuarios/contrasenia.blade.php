{{--
    Cambiar contraseña (cualquier cuenta, la propia): la actual y la nueva dos
    veces. Al guardarla, las sesiones abiertas en otras computadoras se cierran.
--}}
@extends('layouts.admin')

@section('titulo', 'Cambiar contraseña')

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Cambiar contraseña</h1>
            <p class="pagina__texto">Al cambiarla, si tenías el sistema abierto en otra computadora, ahí se cerrará la sesión.</p>
        </div>
    </header>

    <x-aviso-estado />

    <form method="POST" action="{{ route('cuenta.contrasenia.actualizar') }}" class="formulario tarjeta formulario--angosto" novalidate data-validar>
        @csrf
        @method('PUT')

        <x-campo-contrasenia id="actual" name="actual" etiqueta="Contraseña actual" autocomplete="current-password" :obligatorio="true" />
        <x-campo-contrasenia id="contrasenia" name="contrasenia" etiqueta="Contraseña nueva" autocomplete="new-password" regla="nueva" :obligatorio="true" />
        <x-campo-contrasenia id="contrasenia_confirmation" name="contrasenia_confirmation" etiqueta="Confirma la contraseña nueva"
                             autocomplete="new-password" igual-a="contrasenia" :obligatorio="true" />

        <div class="formulario__acciones">
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="llave" />
                Cambiar contraseña
            </button>
        </div>
    </form>
@endsection

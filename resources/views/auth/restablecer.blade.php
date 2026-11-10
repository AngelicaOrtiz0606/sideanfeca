@extends('layouts.publico')

@section('titulo', 'Crear contraseña nueva')
@section('acceso', true)

@section('contenido')
    <a href="{{ route('login') }}" class="enlace-regresar acceso__regresar">
        <x-icono nombre="regresar" />
        <span>Volver a iniciar sesión</span>
    </a>

    <h1 class="acceso__titulo">Crear contraseña nueva</h1>
    <p class="acceso__instruccion">Escríbela dos veces para confirmarla.</p>

    <form
        method="POST"
        action="{{ route('contrasenia.actualizar', ['solicitud' => $solicitud, 'token' => $token]) }}"
        class="formulario formulario--amplio"
        novalidate
        data-validar
    >
        @csrf

        <x-campo-contrasenia
            id="contrasenia"
            name="contrasenia"
            etiqueta="Contraseña nueva"
            autocomplete="new-password"
            regla="nueva"
        />

        <x-campo-contrasenia
            id="contrasenia_confirmation"
            name="contrasenia_confirmation"
            etiqueta="Confirma la contraseña nueva"
            autocomplete="new-password"
            igual-a="contrasenia"
        />

        <button type="submit" class="boton boton--primario boton--grande boton--ancho">
            <span>Guardar contraseña</span>
        </button>
    </form>

@endsection

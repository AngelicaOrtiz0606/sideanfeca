@extends('layouts.publico')

@section('titulo', 'Crear contraseña nueva')
@section('acceso', true)

@section('contenido')
    <h1 class="acceso__titulo">Crear contraseña nueva</h1>
    <p class="acceso__instruccion">Escribe tu contraseña nueva dos veces para confirmarla.</p>

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
            ayuda="Al menos 8 caracteres, con letras y números."
            regla="nueva"
        />

        <x-campo-contrasenia
            id="contrasenia_confirmation"
            name="contrasenia_confirmation"
            etiqueta="Confirma la contraseña nueva"
            autocomplete="new-password"
            igual-a="contrasenia"
        />

        <button type="submit" class="boton boton--primario boton--grande boton--ancho" aria-describedby="pista-restablecer">
            <span>Guardar contraseña</span>
        </button>
        <p id="pista-restablecer" class="formulario__pista" data-pista hidden>Escribe la contraseña nueva dos veces, con al menos 8 caracteres, letras y números.</p>
    </form>

    <p class="acceso__pie">
        <a href="{{ route('login') }}" class="enlace-regresar">
            <x-icono nombre="regresar" />
            <span>Volver a iniciar sesión</span>
        </a>
    </p>
@endsection

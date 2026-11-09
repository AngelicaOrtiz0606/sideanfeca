@extends('layouts.publico')

@section('titulo', 'Recuperar contraseña')
@section('acceso', true)

@section('contenido')
    <a href="{{ route('login') }}" class="enlace-regresar acceso__regresar">
        <x-icono nombre="regresar" />
        <span>Volver a iniciar sesión</span>
    </a>

    <h1 class="acceso__titulo">Recuperar contraseña</h1>
    <p class="acceso__instruccion">
        Escribe el correo de tu cuenta. Te enviaremos un enlace para crear una contraseña nueva.
    </p>

    <x-aviso-estado />

    <form method="POST" action="{{ route('contrasenia.enviar') }}" class="formulario formulario--amplio" novalidate data-validar>
        @csrf

        <x-campo-correo autocomplete="email" autofocus />

        <button type="submit" class="boton boton--primario boton--grande boton--ancho">
            <x-icono nombre="correo" />
            <span>Enviar enlace</span>
        </button>
    </form>

@endsection

@extends('layouts.publico')

@section('titulo', 'Iniciar sesión')
@section('acceso', true)

@section('contenido')
    <h1 class="acceso__titulo">Iniciar sesión</h1>
    <p class="acceso__instruccion">Escribe tu correo y tu contraseña para entrar al sistema.</p>

    <x-aviso-estado />

    <form method="POST" action="{{ route('login.iniciar') }}" class="formulario formulario--amplio" novalidate data-validar>
        @csrf

        <x-campo-correo autocomplete="username" autofocus />

        <x-campo-contrasenia id="contrasenia" name="contrasenia" etiqueta="Contraseña" autocomplete="current-password">
            <x-slot:enlace>
                <a href="{{ route('contrasenia.solicitar') }}" class="campo__enlace">¿Olvidaste tu contraseña?</a>
            </x-slot:enlace>
        </x-campo-contrasenia>

        <button type="submit" class="boton boton--primario boton--grande boton--ancho">
            <span>Iniciar sesión</span>
            <x-icono nombre="entrar" />
        </button>
    </form>
@endsection

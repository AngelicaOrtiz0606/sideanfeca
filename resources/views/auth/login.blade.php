@extends('layouts.publico')

@section('titulo', 'Iniciar sesión')
@section('acceso', true)

@section('contenido')
    <h1 class="acceso__titulo">Iniciar sesión</h1>
    <p class="acceso__instruccion">Escribe tu correo y tu contraseña para entrar al sistema.</p>

    <x-aviso-estado />

    <form method="POST" action="{{ route('login.iniciar') }}" class="formulario formulario--amplio" novalidate data-validar>
        @csrf

        <div class="campo">
            <label for="correo" class="campo__etiqueta">Correo electrónico</label>
            <input
                id="correo"
                name="correo"
                type="email"
                value="{{ old('correo') }}"
                class="campo__entrada"
                autocomplete="username"
                inputmode="email"
                autocapitalize="none"
                spellcheck="false"
                maxlength="254"
                required
                autofocus
                @error('correo') aria-invalid="true" aria-describedby="correo-error" @enderror
            >
            @error('correo')
                <p id="correo-error" class="campo__error" role="alert">
                    <x-icono nombre="alerta" />
                    <span>{{ $message }}</span>
                </p>
            @enderror
        </div>

        <x-campo-contrasenia id="contrasenia" name="contrasenia" etiqueta="Contraseña" autocomplete="current-password">
            <x-slot:enlace>
                <a href="{{ route('contrasenia.solicitar') }}" class="campo__enlace">¿Olvidaste tu contraseña?</a>
            </x-slot:enlace>
        </x-campo-contrasenia>

        <button type="submit" class="boton boton--primario boton--grande boton--ancho" aria-describedby="pista-login">
            <span>Iniciar sesión</span>
            <x-icono nombre="entrar" />
        </button>
        <p id="pista-login" class="formulario__pista" data-pista hidden>Escribe tu correo y tu contraseña para continuar.</p>
    </form>
@endsection

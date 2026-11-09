@extends('layouts.publico')

@section('titulo', 'Recuperar contraseña')
@section('acceso', true)

@section('contenido')
    <h1 class="acceso__titulo">Recuperar contraseña</h1>
    <p class="acceso__instruccion">
        Escribe el correo de tu cuenta. Te enviaremos un enlace para crear una contraseña nueva.
    </p>

    <x-aviso-estado />

    <form method="POST" action="{{ route('contrasenia.enviar') }}" class="formulario formulario--amplio" novalidate data-validar>
        @csrf

        <div class="campo">
            <label for="correo" class="campo__etiqueta">Correo electrónico</label>
            <input
                id="correo"
                name="correo"
                type="email"
                value="{{ old('correo') }}"
                class="campo__entrada"
                autocomplete="email"
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

        <button type="submit" class="boton boton--primario boton--grande boton--ancho" aria-describedby="pista-recuperar">
            <x-icono nombre="correo" />
            <span>Enviar enlace</span>
        </button>
        <p id="pista-recuperar" class="formulario__pista" data-pista hidden>Escribe un correo válido para continuar.</p>
    </form>

    <p class="acceso__pie">
        <a href="{{ route('login') }}" class="enlace-regresar">
            <x-icono nombre="regresar" />
            <span>Volver a iniciar sesión</span>
        </a>
    </p>
@endsection

@extends('layouts.publico')

@section('titulo', 'Iniciar sesión')

{{-- El panel rojo ya presenta el sistema: esta página no repite el encabezado --}}
@section('sin_encabezado', true)
@section('clase_contenido', 'acceso')
@section('clase_cuerpo', 'cuerpo cuerpo--acceso')
@section('clase_pie', 'pie--compacto')

@section('contenido')
    <x-acceso-marca />

    <section class="acceso__panel" aria-labelledby="acceso-titulo">
        <div class="acceso__formulario">
            <h1 id="acceso-titulo" class="acceso__titulo">Iniciar sesión</h1>
            <p class="acceso__instruccion">Escribe tu correo y tu contraseña para entrar al sistema.</p>

            <form method="POST" action="{{ route('login.iniciar') }}" class="formulario formulario--amplio" novalidate>
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

                <div class="campo">
                    <div class="campo__encabezado">
                        <label for="contrasenia" class="campo__etiqueta">Contraseña</label>
                        <a href="{{ route('contrasenia.solicitar') }}" class="campo__enlace">¿Olvidaste tu contraseña?</a>
                    </div>
                    <div class="campo__con-boton">
                        <input
                            id="contrasenia"
                            name="contrasenia"
                            type="password"
                            class="campo__entrada"
                            autocomplete="current-password"
                            maxlength="255"
                            required
                            data-contrasenia
                            aria-describedby="aviso-mayusculas @error('contrasenia') contrasenia-error @enderror"
                            @error('contrasenia') aria-invalid="true" @enderror
                        >
                        <button
                            type="button"
                            class="campo__boton"
                            aria-controls="contrasenia"
                            aria-pressed="false"
                            data-mostrar-contrasenia
                        >
                            <x-icono nombre="ojo" data-icono-mostrar />
                            <x-icono nombre="ojo-tachado" data-icono-ocultar hidden />
                            <span data-texto-boton>Mostrar</span>
                        </button>
                    </div>
                    <p id="aviso-mayusculas" class="campo__aviso" aria-live="polite" data-aviso-mayusculas></p>
                    @error('contrasenia')
                        <p id="contrasenia-error" class="campo__error" role="alert">
                            <x-icono nombre="alerta" />
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <button type="submit" class="boton boton--primario boton--grande boton--ancho">
                    <span>Iniciar sesión</span>
                    <x-icono nombre="entrar" />
                </button>
            </form>

            <p class="acceso__ayuda">
                ¿Tienes problemas para entrar? Comunícate con la persona responsable del sistema en tu institución.
            </p>
        </div>
    </section>
@endsection

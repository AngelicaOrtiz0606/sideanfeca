{{--
    Confirmar la contraseña antes de crear, editar, activar o desactivar
    cuentas. Dura 15 minutos; después se vuelve a pedir.
--}}
@extends('layouts.admin')

@section('titulo', 'Confirma tu contraseña')

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Confirma tu contraseña</h1>
            <p class="pagina__texto">Para cambiar cuentas de usuario, escribe tu contraseña. Así, si alguien encuentra tu sesión abierta, no puede cambiar correos ni dar acceso a otras personas. No se vuelve a pedir durante 15 minutos.</p>
        </div>
    </header>

    <x-aviso-estado />

    <form method="POST" action="{{ route('contrasenia.confirmar.guardar') }}" class="formulario tarjeta formulario--angosto" novalidate data-validar>
        @csrf

        <x-campo-contrasenia id="contrasenia" name="contrasenia" etiqueta="Tu contraseña" autocomplete="current-password" :obligatorio="true" />

        <div class="formulario__acciones">
            <a href="{{ route('usuarios.index') }}" class="boton boton--secundario">Cancelar</a>
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="candado" />
                Confirmar
            </button>
        </div>
    </form>
@endsection

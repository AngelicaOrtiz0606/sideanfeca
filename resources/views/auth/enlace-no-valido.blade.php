@extends('layouts.publico')

@section('titulo', 'Enlace no válido')
@section('acceso', true)

@section('contenido')
    <h1 class="acceso__titulo">El enlace ya no sirve</h1>

    <div class="aviso aviso--error" role="alert">
        <x-icono nombre="alerta" />
        <span>{{ $mensaje }}</span>
    </div>

    <a href="{{ route('contrasenia.solicitar') }}" class="boton boton--primario boton--grande boton--ancho">
        <x-icono nombre="correo" />
        <span>Pedir un enlace nuevo</span>
    </a>

    <p class="acceso__pie">
        <a href="{{ route('login') }}" class="enlace-regresar">
            <x-icono nombre="regresar" />
            <span>Volver a iniciar sesión</span>
        </a>
    </p>
@endsection

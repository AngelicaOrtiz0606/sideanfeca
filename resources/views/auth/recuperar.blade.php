@extends('layouts.publico')

@section('titulo', 'Recuperar contraseña')

@section('sin_encabezado', true)
@section('clase_contenido', 'acceso')
@section('clase_cuerpo', 'cuerpo cuerpo--acceso')
@section('clase_pie', 'pie--compacto')

@section('contenido')
    <x-acceso-marca />

    <section class="acceso__panel" aria-labelledby="recuperar-titulo">
        <div class="acceso__formulario">
            <h1 id="recuperar-titulo" class="acceso__titulo">Recuperar contraseña</h1>

            {{-- Página temporal. El flujo por correo (código de 15 minutos) se
                 programa en su propio lote de M1 y reemplaza este contenido. --}}
            <p class="acceso__instruccion">
                Muy pronto podrás recibir aquí un código en tu correo para crear una contraseña nueva.
            </p>
            <p class="acceso__instruccion">
                Mientras tanto, comunícate con la persona responsable del sistema en tu institución.
            </p>

            <a href="{{ route('login') }}" class="boton boton--secundario boton--ancho">
                <x-icono nombre="regresar" />
                <span>Volver a iniciar sesión</span>
            </a>
        </div>
    </section>
@endsection

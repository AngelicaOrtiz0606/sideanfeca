{{--
    Pantalla provisional de un módulo que todavía no se programa.
    La ruta ya existe y ya está protegida con su privilegio.
--}}
@extends('layouts.admin')

@section('titulo', $titulo)

@section('contenido')
    <section class="aviso-pagina" aria-labelledby="aviso-titulo">
        <div class="aviso-pagina__ilustracion aviso-pagina__ilustracion--suave" aria-hidden="true">
            <span class="aviso-pagina__sello">
                <x-icono nombre="reloj" />
            </span>
        </div>

        <h1 id="aviso-titulo" class="aviso-pagina__titulo">{{ $titulo }}</h1>

        <p class="aviso-pagina__texto">Esta sección estará disponible próximamente.</p>

        <div class="aviso-pagina__acciones">
            <a href="{{ route('inicio') }}" class="boton boton--secundario">
                <x-icono nombre="casa" />
                Volver al inicio
            </a>
        </div>
    </section>
@endsection

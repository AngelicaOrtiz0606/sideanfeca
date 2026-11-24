{{--
    Agregar designación (desde la ficha de una persona): una o varias filas,
    con el mismo componente que Nueva persona (FilasDesignacion). La
    pertenencia es la que ya tiene; un cargo institucional va a su institución.
--}}
@extends('layouts.admin')

@section('titulo', 'Agregar designación · '.$nombre)

@php
    $pertenece = $persona->institucion ?? $persona->organizacion;
    $regresar = route('personas.show', ['persona' => $persona->id, 'pestana' => 'designaciones']);
@endphp

@section('contenido')
    <a href="{{ $regresar }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        {{ $nombre }}
    </a>

    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Agregar designación</h1>
            <p class="pagina__texto">
                {{ $nombre }}@if ($pertenece), de {{ $pertenece }}@endif.
                Los campos con <span class="campo__obligatorio" aria-hidden="true">*</span> son obligatorios.
            </p>
        </div>
    </header>

    @if (! $pertenece)
        <section class="tarjeta">
            <p class="aviso aviso--atencion" role="alert">
                Para recibir una designación debe pertenecer a una institución u organización.
                <a href="{{ route('personas.edit', $persona->id) }}">Edita sus datos</a> para elegirla.
            </p>
        </section>
    @else
        <form method="POST" action="{{ route('personas.designaciones.store', $persona->id) }}" class="formulario" novalidate data-validar>
            @csrf
            <x-errores-formulario />

            <x-seccion-formulario id="seccion-designaciones" numero="1" titulo="Designaciones" descripcion="El cargo y su ámbito, y desde cuándo. Si el puesto aún no existe, se crea al guardar.">
                @include('personas._designaciones', ['notaInstitucional' => $persona->institucion
                    ? "Es un cargo de su institución: {$persona->institucion}."
                    : 'Un cargo institucional necesita que pertenezca a una institución; hoy pertenece a una organización.',
                    'institucion' => $persona->institucion_id])
            </x-seccion-formulario>

            <div class="tarjeta cierre-registro">
                <div class="cierre-registro__acciones">
                    <a href="{{ $regresar }}" class="boton boton--secundario">Cancelar</a>
                    <button type="submit" class="boton boton--primario">
                        <x-icono nombre="correcto" />
                        Guardar
                    </button>
                </div>
            </div>
        </form>
    @endif
@endsection

{{-- Registro y edición de una coordinación nacional. Una nueva queda al final del orden. --}}
@extends('layouts.admin')

@php($esNueva = ! $coordinacion->exists)

@section('titulo', $esNueva ? 'Nueva coordinación' : 'Editar coordinación')

@section('contenido')
    <a href="{{ $esNueva ? route('coordinaciones.index') : route('coordinaciones.show', $coordinacion) }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        {{ $esNueva ? 'Coordinaciones nacionales' : $coordinacion->cona_nombre }}
    </a>

    <header class="pagina__encabezado">
        <div>
            @unless ($esNueva)
                <p class="pagina__antetitulo">Coordinación nacional · Lugar {{ $coordinacion->cona_orden }}</p>
            @endunless
            <h1 class="pagina__titulo">{{ $esNueva ? 'Nueva coordinación' : 'Editar coordinación' }}</h1>
        </div>
    </header>

    <form method="POST"
          action="{{ $esNueva ? route('coordinaciones.store') : route('coordinaciones.update', $coordinacion) }}"
          class="tarjeta formulario formulario--pantalla formulario--angosto"
          novalidate data-validar>
        @csrf
        @unless ($esNueva)
            @method('PUT')
        @endunless

        <x-errores-formulario />

        <div class="campo">
            <label for="nombre" class="campo__etiqueta">Nombre</label>
            <input id="nombre" name="nombre" type="text" maxlength="70" required autocomplete="off"
                   class="campo__entrada" value="{{ old('nombre', $coordinacion->cona_nombre) }}"
                   @error('nombre') aria-invalid="true" @enderror>
        </div>

        <div class="campo">
            <label for="descripcion" class="campo__etiqueta">Descripción <span class="campo__opcional">(opcional)</span></label>
            <input id="descripcion" name="descripcion" type="text" maxlength="100" autocomplete="off"
                   class="campo__entrada" value="{{ old('descripcion', $coordinacion->cona_descripcion) }}"
                   @error('descripcion') aria-invalid="true" @enderror>
        </div>

        <div class="formulario__acciones">
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="correcto" />
                {{ $esNueva ? 'Registrar coordinación' : 'Guardar cambios' }}
            </button>
            <a href="{{ $esNueva ? route('coordinaciones.index') : route('coordinaciones.show', $coordinacion) }}" class="boton boton--secundario">Cancelar</a>
        </div>
    </form>
@endsection

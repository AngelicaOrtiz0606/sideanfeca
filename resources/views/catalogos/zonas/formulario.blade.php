{{--
    Registro y edición de una zona regional.
    Las entidades marcadas pasan a esta zona. Las que ya están aquí aparecen
    marcadas y fijas: cada entidad siempre tiene una zona, así que para sacarla
    se marca en la zona a la que va.
--}}
@extends('layouts.admin')

@php
    $esNueva = ! $zona->exists;
    $elegidas = collect(old('entidades', []))->map(fn ($id) => (int) $id);
@endphp

@section('titulo', $esNueva ? 'Nueva zona' : 'Editar zona')

@section('contenido')
    <a href="{{ $esNueva ? route('zonas.index') : route('zonas.show', $zona) }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        {{ $esNueva ? 'Zonas regionales' : $zona->zore_nombre }}
    </a>

    <header class="pagina__encabezado">
        <div>
            @unless ($esNueva)
                <p class="pagina__antetitulo">Zona regional {{ $zona->zore_numero }}</p>
            @endunless
            <h1 class="pagina__titulo">{{ $esNueva ? 'Nueva zona' : 'Editar zona' }}</h1>
        </div>
    </header>

    <form method="POST"
          action="{{ $esNueva ? route('zonas.store') : route('zonas.update', $zona) }}"
          class="tarjeta formulario formulario--pantalla"
          novalidate data-validar>
        @csrf
        @unless ($esNueva)
            @method('PUT')
        @endunless

        <x-errores-formulario />

        <div class="formulario__fila">
            <div class="campo campo--corto">
                <label for="numero" class="campo__etiqueta">Número</label>
                <input id="numero" name="numero" type="number" inputmode="numeric" min="1" max="7" step="1" required
                       class="campo__entrada" value="{{ old('numero', $zona->zore_numero) }}"
                       @error('numero') aria-invalid="true" @enderror>
            </div>

            <div class="campo">
                <label for="nombre" class="campo__etiqueta">Nombre</label>
                <input id="nombre" name="nombre" type="text" maxlength="60" required autocomplete="off"
                       class="campo__entrada" value="{{ old('nombre', $zona->zore_nombre) }}"
                       @error('nombre') aria-invalid="true" @enderror>
            </div>
        </div>

        <fieldset class="selector-entidades">
            <legend class="campo__etiqueta">Entidades federativas</legend>
            <p class="campo__ayuda">Marca las que pasan a esta zona. Para sacar una entidad de aquí, márcala en la zona a la que va.</p>

            <ul class="selector-entidades__lista">
                @foreach ($entidades as $entidad)
                    @php
                        $enEstaZona = $zona->exists && $entidad->enfe_id_zona_regional === $zona->zore_id_zona_regional;
                        $id = 'entidad-'.$entidad->enfe_id_entidad_federativa;
                    @endphp
                    <li>
                        <label for="{{ $id }}" class="opcion-entidad {{ $enEstaZona ? 'opcion-entidad--fija' : '' }}">
                            <input id="{{ $id }}" type="checkbox" name="entidades[]" value="{{ $entidad->enfe_id_entidad_federativa }}"
                                   @checked($enEstaZona || $elegidas->contains($entidad->enfe_id_entidad_federativa))
                                   @disabled($enEstaZona)
                                   @unless ($enEstaZona)
                                       data-entidad="{{ $entidad->enfe_nombre }}"
                                       data-zona-actual="{{ $entidad->zona?->etiqueta() }}"
                                   @endunless>
                            <span class="opcion-entidad__nombre">{{ $entidad->enfe_nombre }}</span>
                            <span class="opcion-entidad__zona">{{ $enEstaZona ? 'En esta zona' : 'Zona '.$entidad->zona?->zore_numero }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        </fieldset>

        <div class="formulario__acciones">
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="correcto" />
                {{ $esNueva ? 'Registrar zona' : 'Guardar cambios' }}
            </button>
            <a href="{{ $esNueva ? route('zonas.index') : route('zonas.show', $zona) }}" class="boton boton--secundario">Cancelar</a>
        </div>
    </form>

    {{-- Confirmación al marcar una entidad que hoy pertenece a otra zona --}}
    <dialog id="dialogo-cambio-zona" class="dialogo" aria-labelledby="dialogo-cambio-zona-titulo">
        <form method="dialog" class="formulario">
            <h2 id="dialogo-cambio-zona-titulo" class="dialogo__titulo">Esta entidad ya tiene zona</h2>
            <p class="dialogo__texto" data-texto-cambio-zona></p>

            <div class="dialogo__acciones">
                <button type="submit" value="no" class="boton boton--secundario">Cancelar</button>
                <button type="submit" value="si" class="boton boton--primario">Sí, pasarla a esta zona</button>
            </div>
        </form>
    </dialog>
@endsection

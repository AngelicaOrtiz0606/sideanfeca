{{--
    Buscador de la universidad a la que pertenece una facultad o campus:
    escribes y la lista se va filtrando por nombre, zona o entidad. Cada
    opción muestra su zona y su entidad, para distinguir universidades con el
    mismo nombre (resources/js/modulos/buscador-universidad.js).

    Props: universidades (id, nombre, zona_numero, zona_nombre, entidad, participacion),
           elegida (id), desactivado.
--}}
@props(['universidades', 'elegida' => null, 'desactivado' => false])

@php
    $detalle = fn ($u) => collect([
        $u->zona_numero ? "Zona {$u->zona_numero} · {$u->zona_nombre}" : null,
        $u->entidad,
        $u->participacion,
    ])->filter()->implode(' · ');
    $actual = $elegida ? $universidades->firstWhere('id', (int) $elegida) : null;
@endphp

<div class="buscador" data-buscador-universidad>
    <input type="hidden" name="universidad" value="{{ $actual?->id }}" data-buscador-valor @disabled($desactivado)>

    <input id="universidad" type="text" role="combobox" autocomplete="off" class="campo__entrada buscador__entrada"
           aria-expanded="false" aria-controls="lista-universidades" aria-autocomplete="list"
           placeholder="Escribe el nombre, la zona o la entidad" value="{{ $actual?->nombre }}"
           data-buscador-texto required @disabled($desactivado)
           @if ($errors->has('universidad')) aria-invalid="true" @endif>

    <p class="campo__ayuda" data-buscador-detalle>{{ $actual ? $detalle($actual) : '' }}</p>

    <ul id="lista-universidades" class="buscador__lista" role="listbox" aria-label="Universidades" hidden>
        @forelse ($universidades as $universidad)
            <li class="buscador__opcion" role="option" id="universidad-{{ $universidad->id }}" aria-selected="false"
                data-id="{{ $universidad->id }}" data-nombre="{{ $universidad->nombre }}" data-detalle="{{ $detalle($universidad) }}">
                <span class="buscador__nombre">{{ $universidad->nombre }}</span>
                <span class="buscador__detalle">{{ $detalle($universidad) }}</span>
            </li>
        @empty
        @endforelse
        <li class="buscador__vacio" data-buscador-vacio hidden>Ninguna universidad coincide. Revisa el nombre o regístrala primero.</li>
    </ul>
</div>

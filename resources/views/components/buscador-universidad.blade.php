{{--
    Buscador de la universidad a la que pertenece una facultad o campus:
    escribes y la lista se va filtrando por nombre, zona o entidad. Cada
    opción muestra su zona y su entidad, para distinguir universidades con el
    mismo nombre (resources/js/modulos/buscador-universidad.js).

    También sirve para elegir cualquier institución (por ejemplo, la de un
    puesto institucional): nombre, id, plural y vacio cambian el campo y los textos.

    Props: universidades (id, nombre, zona_numero, zona_nombre, entidad, participacion; opcional tipo y universidad),
           elegida (id), desactivado, nombre (campo que se envía), id, plural, vacio.
--}}
@props(['universidades', 'elegida' => null, 'desactivado' => false, 'nombre' => 'universidad', 'id' => 'universidad',
    'plural' => 'Universidades', 'vacio' => 'Ninguna universidad coincide. Revisa el nombre o regístrala primero.',
    'mensaje' => 'Elige una universidad de la lista.'])

@php
    $detalle = fn ($u) => collect([
        isset($u->universidad) && $u->universidad ? ($u->tipo ?? '').' · '.$u->universidad : null,
        $u->zona_numero ? "Zona {$u->zona_numero} · {$u->zona_nombre}" : null,
        // La entidad no se repite si se llama igual que la zona (por ejemplo, Ciudad de México)
        $u->entidad !== ($u->zona_nombre ?? null) ? $u->entidad : null,
        $u->participacion,
    ])->filter()->implode(' · ');
    $actual = $elegida ? $universidades->firstWhere('id', (int) $elegida) : null;
@endphp

<div class="buscador" data-buscador-universidad data-mensaje="{{ $mensaje }}">
    <input type="hidden" name="{{ $nombre }}" value="{{ $actual?->id }}" data-buscador-valor @disabled($desactivado)>

    <input id="{{ $id }}" type="text" role="combobox" autocomplete="off" class="campo__entrada buscador__entrada"
           aria-expanded="false" aria-controls="lista-{{ $id }}" aria-autocomplete="list"
           placeholder="Escribe el nombre, la zona o la entidad" value="{{ $actual?->nombre }}"
           data-buscador-texto required @disabled($desactivado)
           @if ($errors->has($nombre)) aria-invalid="true" @endif>

    <p class="campo__ayuda" data-buscador-detalle>{{ $actual ? $detalle($actual) : '' }}</p>

    <ul id="lista-{{ $id }}" class="buscador__lista" role="listbox" aria-label="{{ $plural }}" hidden>
        @forelse ($universidades as $universidad)
            <li class="buscador__opcion" role="option" id="{{ $id }}-{{ $universidad->id }}" aria-selected="false"
                data-id="{{ $universidad->id }}" data-nombre="{{ $universidad->nombre }}" data-detalle="{{ $detalle($universidad) }}">
                <span class="buscador__nombre">{{ $universidad->nombre }}</span>
                <span class="buscador__detalle">{{ $detalle($universidad) }}</span>
            </li>
        @empty
        @endforelse
        <li class="buscador__vacio" data-buscador-vacio hidden>{{ $vacio }}</li>
    </ul>
</div>

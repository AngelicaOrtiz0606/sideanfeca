{{--
    Personas: lista con filtros en el servidor (pueden ser cientos), de 10 en 10.
    El buscador se aplica solo al dejar de escribir; las listas, al cambiarlas.
    Cada fila: Consultar (ficha de solo lectura) y Editar (el formulario
    completo, ya lleno). Las personas ocultas no aparecen (M8).
--}}
@extends('layouts.admin')

@section('titulo', 'Personas')

@php
    $hayFiltros = collect($filtros)->filter()->isNotEmpty();
    $activos = collect($filtros)->except('texto')->filter()->count();
@endphp

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Personas</h1>
            <p class="pagina__texto">Quienes ocupan o han ocupado un cargo en ANFECA, con sus grados y contactos.</p>
        </div>
    </header>

    <x-aviso-estado />

    <section class="tarjeta tarjeta--tabla" aria-label="Lista de personas">
        <form method="GET" action="{{ route('personas.index') }}" class="filtros filtros--servidor" role="search" data-filtros-servidor>
            <label for="buscar-persona" class="solo-lectores">Buscar por nombre o CURP</label>
            <input id="buscar-persona" name="texto" type="search" class="campo__entrada filtros__buscar"
                   placeholder="Buscar por nombre o CURP" value="{{ $filtros['texto'] }}" maxlength="100"
                   data-filtro-escrito @if ($filtros['texto'] !== null) autofocus @endif>

            <button type="button" class="filtros__resumen" aria-expanded="{{ $activos > 0 ? 'true' : 'false' }}" aria-controls="filtros-opciones" data-filtros-boton>
                <x-icono nombre="ordenar" />
                Filtros @if ($activos > 0)<span class="filtro__cifra">({{ $activos }})</span>@endif
            </button>
            <div class="filtros__opciones" id="filtros-opciones" data-filtros-opciones @if ($activos === 0) data-cerrado @endif>
                <label class="filtros__selector">
                    <span class="filtros__etiqueta">Grado</span>
                    <select name="grado" class="campo__entrada">
                        <option value="">Todos</option>
                        @foreach ($grados as $grado)
                            <option value="{{ $grado->id }}" @selected($filtros['grado'] === $grado->id)>{{ $grado->nombre }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="filtros__selector">
                    <span class="filtros__etiqueta">Zona</span>
                    <select name="zona" class="campo__entrada">
                        <option value="">Todas</option>
                        @foreach ($zonas as $zona)
                            <option value="{{ $zona->id }}" @selected($filtros['zona'] === $zona->id)>Zona {{ $zona->numero }} · {{ $zona->nombre }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="filtros__selector">
                    <span class="filtros__etiqueta">Cargo</span>
                    <select name="situacion" class="campo__entrada">
                        <option value="">Con o sin cargo</option>
                        <option value="con-cargo" @selected($filtros['situacion'] === 'con-cargo')>Con cargo vigente</option>
                        <option value="sin-cargo" @selected($filtros['situacion'] === 'sin-cargo')>Sin cargo vigente</option>
                    </select>
                </label>
            </div>

            @if ($hayFiltros)
                <a href="{{ route('personas.index') }}" class="boton boton--discreto filtros__borrar">
                    <x-icono nombre="cerrar" />
                    Borrar filtros
                </a>
            @endif
        </form>

        @if ($personas->isEmpty())
            <p class="vacio vacio--en-tabla">
                {{ $hayFiltros ? 'Ninguna persona coincide con los filtros.' : 'Todavía no hay personas.' }}
            </p>
        @else
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Persona</th>
                        <th scope="col">Pertenece a</th>
                        <th scope="col">Cargo vigente</th>
                        <th scope="col" class="tabla__acciones"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($personas as $persona)
                        @php
                            $nombre = trim("{$persona->nombre} {$persona->primer_apellido} {$persona->segundo_apellido}");
                        @endphp
                        <tr>
                            <th scope="row" data-etiqueta="Persona">
                                <a href="{{ route('personas.show', $persona->id) }}" class="tabla__principal tabla__enlace">{{ $nombre }}</a>
                            </th>
                            <td data-etiqueta="Pertenece a">
                                @if ($persona->institucion)
                                    {{ $persona->institucion }}
                                    @if ($persona->zona_numero)
                                        <span class="tabla__detalle">Zona {{ $persona->zona_numero }} · {{ $persona->zona_nombre }}</span>
                                    @endif
                                @elseif ($persona->organizacion)
                                    {{ $persona->organizacion }}
                                    <span class="tabla__detalle">Organización</span>
                                @else
                                    <span class="tabla__vacio">—</span>
                                @endif
                            </td>
                            <td data-etiqueta="Cargo vigente">
                                @forelse ($persona->cargos as $cargo)
                                    <span class="tabla__linea">{{ $cargo }}</span>
                                @empty
                                    <span class="tabla__vacio">Sin cargo vigente</span>
                                @endforelse
                            </td>
                            <td class="tabla__acciones">
                                <div class="acciones-fila">
                                    <a href="{{ route('personas.show', $persona->id) }}" class="accion accion--consultar">
                                        <x-icono nombre="ojo" />
                                        <span class="accion__texto">Consultar <span class="solo-lectores">{{ $nombre }}</span></span>
                                    </a>
                                    <a href="{{ route('personas.edit', $persona->id) }}" class="accion accion--editar">
                                        <x-icono nombre="lapiz" />
                                        <span class="accion__texto">Editar <span class="solo-lectores">{{ $nombre }}</span></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <x-paginacion :paginas="$personas" />
        @endif
    </section>
@endsection

{{--
    Consultar directorios (M10): el directorio elegido como está hoy (solo lo
    vigente), con sus filtros. Desde aquí se copian sus correos o se exporta a CSV
    (todo lo filtrado, no solo la página). Las personas ocultas aparecen como
    "Vacante" y solo se muestran los contactos visibles, igual que en el sitio.
--}}
@extends('layouts.admin')

@section('titulo', 'Consultar directorios')

@php
    $base = ['directorio' => $elegido->id];
    $consulta = array_filter($filtros + ['directorio' => $elegido->id]);
    $hayFiltros = collect($filtros)->filter()->isNotEmpty();
@endphp

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Directorios</h1>
            <p class="pagina__texto">Cada directorio como está hoy: quién ocupa cada puesto. Las personas ocultas aparecen como «Vacante» y solo se muestran los contactos visibles.</p>
        </div>

        <div class="pagina__acciones">
            <button type="button" class="boton boton--secundario" data-abrir-dialogo="dialogo-correos" @disabled($correos === [])>
                <x-icono nombre="copiar" />
                Copiar correos
            </button>
            @if ($filas->isEmpty())
                <span class="boton boton--secundario" aria-disabled="true">
                    <x-icono nombre="descargar" />
                    Exportar CSV
                </span>
            @else
                <a href="{{ route('directorios.csv', $consulta) }}" class="boton boton--secundario" download>
                    <x-icono nombre="descargar" />
                    Exportar CSV
                </a>
            @endif
        </div>
    </header>

    <nav class="pestanas__lista" aria-label="Directorios">
        @foreach ($tipos as $tipo)
            <a href="{{ route('directorios.index', ['directorio' => $tipo->id]) }}" class="pestanas__pestana"
               @if ($elegido->id === $tipo->id) aria-current="page" @endif>
                {{ $tipo->nombre }}
            </a>
        @endforeach
    </nav>

    <section class="tarjeta tarjeta--tabla" aria-labelledby="directorio-titulo">
        <h2 id="directorio-titulo" class="solo-lectores">{{ $elegido->nombre }}</h2>

        <form method="GET" action="{{ route('directorios.index') }}" class="filtros filtros--servidor" data-filtros-servidor>
            <input type="hidden" name="directorio" value="{{ $elegido->id }}">

            @isset($usa['coordinacion'])
                <label class="filtros__selector">
                    <span class="filtros__etiqueta">Coordinación</span>
                    <select name="coordinacion" class="campo__entrada">
                        <option value="">Todas</option>
                        @foreach ($coordinaciones as $coordinacion)
                            <option value="{{ $coordinacion->id }}" @selected($filtros['coordinacion'] === $coordinacion->id)>{{ $coordinacion->nombre }}</option>
                        @endforeach
                    </select>
                </label>
            @endisset

            @isset($usa['zona'])
                <label class="filtros__selector">
                    <span class="filtros__etiqueta">Zona</span>
                    <select name="zona" class="campo__entrada">
                        <option value="">Todas</option>
                        @foreach ($zonas as $zona)
                            <option value="{{ $zona->id }}" @selected($filtros['zona'] === $zona->id)>Zona {{ $zona->numero }} · {{ $zona->nombre }}</option>
                        @endforeach
                    </select>
                </label>
            @endisset

            @isset($usa['entidad'])
                <label class="filtros__selector">
                    <span class="filtros__etiqueta">Entidad</span>
                    <select name="entidad" class="campo__entrada">
                        <option value="">Todas</option>
                        @foreach ($entidades as $entidad)
                            <option value="{{ $entidad->id }}" @selected($filtros['entidad'] === $entidad->id)>{{ $entidad->nombre }}</option>
                        @endforeach
                    </select>
                </label>
            @endisset

            @isset($usa['participacion'])
                <label class="filtros__selector">
                    <span class="filtros__etiqueta">Participación</span>
                    <select name="participacion" class="campo__entrada">
                        <option value="">Afiliadas y observadoras</option>
                        @foreach ($participaciones as $participacion)
                            <option value="{{ $participacion }}" @selected($filtros['participacion'] === $participacion)>{{ $participacion === 'Afiliada' ? 'Afiliadas' : 'Observadoras' }}</option>
                        @endforeach
                    </select>
                </label>
            @endisset

            @if ($hayFiltros)
                <a href="{{ route('directorios.index', $base) }}" class="boton boton--discreto filtros__borrar">
                    <x-icono nombre="cerrar" />
                    Borrar filtros
                </a>
            @endif
        </form>

        @if ($filas->isEmpty())
            <p class="vacio vacio--en-tabla">
                @if ($esInstituciones)
                    Ninguna institución {{ $hayFiltros ? 'coincide con los filtros' : 'es afiliada u observadora' }} hoy.
                @else
                    {{ $hayFiltros ? 'Ningún puesto coincide con los filtros.' : 'Este directorio todavía no tiene puestos. Agrégalos en Ordenar puestos.' }}
                @endif
            </p>
        @else
            <table class="tabla tabla--directorio">
                <caption class="solo-lectores">{{ $elegido->nombre }}, hoy</caption>
                <thead>
                    <tr>
                        @if ($esInstituciones)
                            <th scope="col">Número</th>
                            <th scope="col">Institución</th>
                            <th scope="col">Titular</th>
                        @else
                            <th scope="col">Puesto</th>
                            <th scope="col">Nombre</th>
                        @endif
                        <th scope="col">Contacto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pagina as $fila)
                        <tr>
                            @if ($esInstituciones)
                                <td data-etiqueta="Número">
                                    @if ($fila->numero)
                                        <span class="numero-afiliacion">{{ $fila->numero }}</span>
                                    @else
                                        <span class="tabla__vacio" aria-label="Sin número">—</span>
                                    @endif
                                </td>
                                <th scope="row" data-etiqueta="Institución">
                                    <span class="tabla__principal">{{ $fila->institucion }}</span>
                                    @if ($fila->universidad)
                                        <span class="tabla__detalle">{{ $fila->universidad }}</span>
                                    @endif
                                    <span class="tabla__detalle">
                                        <span class="participacion participacion--{{ strtolower($fila->participacion) }}">{{ $fila->participacion }}</span>
                                        {{ collect([$fila->zona, $fila->entidad])->filter()->implode(' · ') }}
                                    </span>
                                </th>
                                <td data-etiqueta="Titular">
                                    @if ($fila->vacante)
                                        <span class="tabla__vacio">Vacante</span>
                                    @else
                                        <span class="tabla__principal">{{ trim("{$fila->grado} {$fila->nombre}") }}</span>
                                        <span class="tabla__detalle">{{ $fila->puesto }}</span>
                                    @endif
                                </td>
                            @else
                                <th scope="row" data-etiqueta="Puesto">
                                    <span class="tabla__principal">{{ $fila->puesto }}</span>
                                </th>
                                <td data-etiqueta="Nombre">
                                    @if ($fila->vacante)
                                        <span class="tabla__vacio">Vacante</span>
                                    @else
                                        {{ trim("{$fila->grado} {$fila->nombre}") }}
                                        @if ($fila->pertenencia)
                                            <span class="tabla__detalle">{{ $fila->pertenencia }}</span>
                                        @endif
                                    @endif
                                </td>
                            @endif
                            <td data-etiqueta="Contacto">
                                @forelse ([...array_map(fn ($t) => ['telefono', $t, 'Teléfono'], $fila->telefonos), ...array_map(fn ($t) => ['celular', $t, 'Celular'], $fila->celulares), ...array_map(fn ($c) => ['correo', $c, 'Correo'], $fila->correos)] as [$tipo, $valor, $etiqueta])
                                    <span class="contacto-directorio" title="{{ $etiqueta }}">
                                        <x-icono :nombre="$tipo" />
                                        <span class="solo-lectores">{{ $etiqueta }}:</span>
                                        @if ($tipo === 'correo')
                                            <a href="mailto:{{ $valor }}" class="tabla__enlace-web">{{ $valor }}</a>
                                        @else
                                            {{ $valor }}
                                        @endif
                                    </span>
                                @empty
                                    <span class="tabla__vacio">—</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <x-paginacion :paginas="$pagina" />
        @endif
    </section>

    {{-- Los correos de todo lo filtrado (no solo de esta página) --}}
    <dialog id="dialogo-correos" class="dialogo" aria-labelledby="dialogo-correos-titulo">
        <div class="formulario">
            <h2 id="dialogo-correos-titulo" class="dialogo__titulo">Copiar correos</h2>
            <p class="dialogo__texto">
                {{ count($correos) === 1 ? '1 correo' : count($correos).' correos' }} de {{ $elegido->nombre }}, separados por punto y coma para pegarlos en tu correo.
            </p>
            <label for="correos-texto" class="solo-lectores">Correos</label>
            <textarea id="correos-texto" class="campo__entrada correos-copiar" rows="6" readonly data-correos>{{ implode('; ', $correos) }}</textarea>
            <p class="correos-copiar__aviso" role="status" data-correos-aviso></p>
            <div class="dialogo__acciones">
                <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cerrar</button>
                <button type="button" class="boton boton--primario" data-copiar-correos>
                    <x-icono nombre="copiar" />
                    Copiar
                </button>
            </div>
        </div>
    </dialog>
@endsection

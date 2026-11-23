{{--
    Bitácora completa (solo superadministrador) y "Mi actividad" ($propia:
    solo lo que hizo la cuenta, para cualquier cuenta). Cada acción en una línea,
    de la más reciente a la más antigua, de 10 en 10. Solo se consulta.
    Filtros: texto (quién, de quién o qué), acción, qué se modificó y fechas.
    Exportar CSV descarga todo lo filtrado, no solo la página.
--}}
@extends('layouts.admin')

@section('titulo', $propia ? 'Mi actividad' : 'Bitácora')

@php
    $activos = collect([$filtros['accion'], $filtros['tabla'], $filtros['desde'], $filtros['hasta']])->filter()->count();
    $hayFiltros = $filtros['texto'] !== null || $activos > 0;
    $consulta = array_filter(\Illuminate\Support\Arr::except($filtros, 'usuario'));
    $ruta = $propia ? 'actividad.index' : 'bitacora.index';
@endphp

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">{{ $propia ? 'Mi actividad' : 'Bitácora' }}</h1>
            <p class="pagina__texto">
                @if ($propia)
                    Todo lo que has registrado, cambiado o borrado en el sistema, con su fecha y su motivo.
                @else
                    Todo lo que se registra, cambia o borra en el sistema: quién, cuándo y por qué.
                @endif
                Solo se consulta; nadie la puede modificar.
            </p>
        </div>

        <div class="pagina__acciones">
            @if ($movimientos->isEmpty())
                <span class="boton boton--secundario" aria-disabled="true">
                    <x-icono nombre="descargar" />
                    Exportar CSV
                </span>
            @else
                <a href="{{ route($propia ? 'actividad.csv' : 'bitacora.csv', $consulta) }}" class="boton boton--secundario" download>
                    <x-icono nombre="descargar" />
                    Exportar CSV
                </a>
            @endif
        </div>
    </header>

    <section class="tarjeta tarjeta--tabla" aria-label="Movimientos de la bitácora">
        <form method="GET" action="{{ route($ruta) }}" class="filtros filtros--servidor" role="search" data-filtros-servidor>
            <label for="buscar-bitacora" class="solo-lectores">{{ $propia ? 'Buscar de quién o qué' : 'Buscar por quién, de quién o qué' }}</label>
            <input id="buscar-bitacora" name="texto" type="search" class="campo__entrada filtros__buscar"
                   placeholder="{{ $propia ? 'De quién o qué' : 'Quién, de quién o qué' }}" value="{{ $filtros['texto'] }}" maxlength="100"
                   data-filtro-escrito @if ($filtros['texto'] !== null) autofocus @endif>

            <button type="button" class="filtros__resumen" aria-expanded="{{ $activos > 0 ? 'true' : 'false' }}" aria-controls="filtros-opciones" data-filtros-boton>
                <x-icono nombre="ordenar" />
                Filtros @if ($activos > 0)<span class="filtro__cifra">({{ $activos }})</span>@endif
            </button>
            <div class="filtros__opciones" id="filtros-opciones" data-filtros-opciones @if ($activos === 0) data-cerrado @endif>
                <label class="filtros__selector">
                    <span class="filtros__etiqueta">Acción</span>
                    <select name="accion" class="campo__entrada">
                        <option value="">Todas</option>
                        @foreach ($acciones as $valor => $texto)
                            <option value="{{ $valor }}" @selected($filtros['accion'] === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="filtros__selector">
                    <span class="filtros__etiqueta">Sobre</span>
                    <select name="tabla" class="campo__entrada">
                        <option value="">Todo</option>
                        @foreach ($tablas as $valor => $texto)
                            <option value="{{ $valor }}" @selected($filtros['tabla'] === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </label>

                <label @class(['filtros__selector', 'filtros__selector--fecha', 'filtros__selector--en-uso' => $filtros['desde']])>
                    <span class="filtros__etiqueta">Desde</span>
                    <input type="date" name="desde" class="campo__entrada" value="{{ $filtros['desde'] }}" max="{{ now()->toDateString() }}">
                </label>

                <label @class(['filtros__selector', 'filtros__selector--fecha', 'filtros__selector--en-uso' => $filtros['hasta']])>
                    <span class="filtros__etiqueta">Hasta</span>
                    <input type="date" name="hasta" class="campo__entrada" value="{{ $filtros['hasta'] }}" max="{{ now()->toDateString() }}">
                </label>
            </div>

            @if ($hayFiltros)
                <a href="{{ route($ruta) }}" class="boton boton--discreto filtros__borrar">
                    <x-icono nombre="cerrar" />
                    Borrar filtros
                </a>
            @endif
        </form>

        @if ($movimientos->isEmpty())
            <p class="vacio vacio--en-tabla">{{ $hayFiltros ? 'Ningún movimiento coincide con los filtros.' : ($propia ? 'Todavía no has hecho cambios en el sistema.' : 'Todavía no hay movimientos registrados.') }}</p>
        @else
            <ol class="actividad bitacora-completa" aria-label="Movimientos de la bitácora">
                @foreach ($movimientos as $movimiento)
                    @include('bitacora._movimiento')
                @endforeach
            </ol>

            <x-paginacion :paginas="$movimientos" />
        @endif
    </section>
@endsection

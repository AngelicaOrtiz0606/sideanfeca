@extends('layouts.admin')

@section('titulo', 'Inicio')

{{-- Pantalla de inicio (sección 10), con el estilo del prototipo: franja de
     bienvenida, tarjetas de contadores y lista de actividad reciente. --}}

@php
    $hoy = now()->locale('es');

    $tarjetas = [
        ['clave' => 'afiliadas', 'etiqueta' => 'Instituciones afiliadas', 'icono' => 'institucion', 'tono' => 'marca'],
        ['clave' => 'observadoras', 'etiqueta' => 'Instituciones observadoras', 'icono' => 'institucion', 'tono' => 'vino'],
        ['clave' => 'coordinaciones', 'etiqueta' => 'Coordinaciones nacionales', 'icono' => 'organigrama', 'tono' => 'gris'],
    ];
@endphp

@section('contenido')
    <section class="bienvenida" aria-labelledby="bienvenida-titulo">
        <div class="bienvenida__texto">
            <h1 id="bienvenida-titulo">{{ $genero === 'F' ? 'Bienvenida' : 'Bienvenido' }}, {{ $nombre }}</h1>
            <p>
                @if ($inicioAnterior)
                    Tu inicio de sesión anterior fue el <time datetime="{{ $inicioAnterior->toIso8601String() }}">{{ $inicioAnterior->locale('es')->translatedFormat('j \d\e F \d\e Y \a \l\a\s H:i') }}</time>.
                @else
                    Este es tu primer inicio de sesión.
                @endif
            </p>
        </div>
        <p class="bienvenida__fecha">
            <x-icono nombre="calendario" />
            <time datetime="{{ $hoy->toDateString() }}">{{ ucfirst($hoy->translatedFormat('l j \d\e F \d\e Y')) }}</time>
        </p>
    </section>

    <section aria-labelledby="resumen-titulo" class="inicio__seccion">
        <div class="seccion__encabezado">
            <h2 id="resumen-titulo" class="seccion__titulo">Resumen</h2>
            <p class="seccion__nota"><span class="punto punto--activo" aria-hidden="true"></span>Solo se cuentan las activas.</p>
        </div>

        <ul class="contadores" role="list">
            @foreach ($tarjetas as $tarjeta)
                @php
                    $activas = $contadores[$tarjeta['clave']];
                    $desactivadas = $contadores[$tarjeta['clave'].'_total'] - $activas;
                @endphp
                <li class="contador">
                    <span class="contador__icono" data-tono="{{ $tarjeta['tono'] }}" aria-hidden="true">
                        <x-icono :nombre="$tarjeta['icono']" />
                    </span>
                    <div class="contador__datos">
                        <p class="contador__etiqueta">{{ $tarjeta['etiqueta'] }}</p>
                        <p class="contador__valor" data-contar="{{ $activas }}">{{ number_format($activas) }}</p>
                        <p class="contador__detalle">
                            <span class="punto" aria-hidden="true"></span>
                            Desactivadas: {{ number_format($desactivadas) }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    @php $pendientes = collect($porAtender)->sum('cantidad'); @endphp
    @if ($porAtender !== [])
        <section aria-labelledby="atender-titulo" class="inicio__seccion tarjeta-seccion">
            <div class="tarjeta-seccion__encabezado">
                <h2 id="atender-titulo" class="seccion__titulo">Por atender</h2>
            </div>

            @if ($pendientes === 0)
                <p class="vacio">Todo al día: no hay puestos vacantes, instituciones sin titular ni personas sin correo.</p>
            @else
                <ul class="pendientes">
                    @foreach ($porAtender as $pendiente)
                        @continue($pendiente['cantidad'] === 0)
                        <li class="pendiente">
                            <p class="pendiente__titulo">
                                <span class="pendiente__cifra">{{ number_format($pendiente['cantidad']) }}</span>
                                {{ $pendiente['texto'] }}
                            </p>
                            <ul class="pendiente__enlaces">
                                @foreach ($pendiente['enlaces'] as $enlace)
                                    <li><a href="{{ $enlace['url'] }}">{{ $enlace['texto'] }}</a></li>
                                @endforeach
                                @if ($pendiente['todos'])
                                    <li><a href="{{ $pendiente['todos'] }}" class="enlace-flecha">Ver todas</a></li>
                                @endif
                            </ul>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    {{-- Quien ve la bitácora ve la actividad de todos; los demás, la suya --}}
    <section aria-labelledby="bitacora-titulo" class="inicio__seccion tarjeta-seccion">
        <div class="tarjeta-seccion__encabezado">
            <h2 id="bitacora-titulo" class="seccion__titulo">{{ $verBitacora ? 'Actividad reciente' : 'Mi actividad reciente' }}</h2>
            @if ($hayMasMovimientos)
                <a href="{{ $verBitacora ? route('bitacora.index') : route('actividad.index') }}" class="enlace-flecha">{{ $verBitacora ? 'Ver toda la bitácora' : 'Ver toda mi actividad' }}</a>
            @endif
        </div>

        @if ($movimientos->isEmpty())
            <p class="vacio">{{ $verBitacora ? 'Todavía no hay movimientos registrados.' : 'Todavía no has hecho cambios en el sistema.' }}</p>
        @else
            <ol class="actividad" aria-label="Últimos {{ $movimientos->count() }} movimientos">
                @foreach ($movimientos as $movimiento)
                    @include('bitacora._movimiento')
                @endforeach
            </ol>
        @endif
    </section>
@endsection

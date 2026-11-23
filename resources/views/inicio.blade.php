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

        <dl class="contadores">
            @foreach ($tarjetas as $tarjeta)
                @php
                    $activas = $contadores[$tarjeta['clave']];
                    $desactivadas = $contadores[$tarjeta['clave'].'_total'] - $activas;
                @endphp
                <div class="contador">
                    <span class="contador__icono" data-tono="{{ $tarjeta['tono'] }}" aria-hidden="true">
                        <x-icono :nombre="$tarjeta['icono']" />
                    </span>
                    <div class="contador__datos">
                        <dt class="contador__etiqueta">{{ $tarjeta['etiqueta'] }}</dt>
                        <dd class="contador__valor" data-contar="{{ $activas }}">{{ number_format($activas) }}</dd>
                        <dd class="contador__detalle">
                            <span class="punto" aria-hidden="true"></span>
                            Desactivadas: {{ number_format($desactivadas) }}
                        </dd>
                    </div>
                </div>
            @endforeach
        </dl>
    </section>

    @if ($verBitacora)
        <section aria-labelledby="bitacora-titulo" class="inicio__seccion tarjeta-seccion">
            <div class="tarjeta-seccion__encabezado">
                <h2 id="bitacora-titulo" class="seccion__titulo">Actividad reciente</h2>
                @if ($hayMasMovimientos)
                    <a href="{{ route('bitacora.index') }}" class="enlace-flecha">Ver toda la bitácora</a>
                @endif
            </div>

            @if ($movimientos->isEmpty())
                <p class="vacio">Todavía no hay movimientos registrados.</p>
            @else
                <ol class="actividad" aria-label="Últimos {{ $movimientos->count() }} movimientos de la bitácora">
                    @foreach ($movimientos as $movimiento)
                        @include('bitacora._movimiento')
                    @endforeach
                </ol>
            @endif
        </section>
    @endif
@endsection

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
                @if ($hayMasMovimientos && Route::has('bitacora.index'))
                    <a href="{{ route('bitacora.index') }}" class="enlace-flecha">Ver más</a>
                @endif
            </div>

            @if ($movimientos->isEmpty())
                <p class="vacio">Todavía no hay movimientos registrados.</p>
            @else
                <ol class="actividad" aria-label="Últimos {{ $movimientos->count() }} movimientos de la bitácora">
                    @foreach ($movimientos as $movimiento)
                        <li class="actividad__item">
                            <div class="actividad__cuerpo">
                                <p class="actividad__frase">
                                    <strong>{{ $movimiento['quien'] }}</strong>
                                    <span class="actividad__verbo" data-tono="{{ $movimiento['tono'] }}">{{ $movimiento['verbo'] }}</span>
                                    {{ $movimiento['objeto'] }}.
                                </p>

                                <p class="actividad__meta">
                                    <time datetime="{{ $movimiento['fecha']->toIso8601String() }}">{{ $movimiento['fecha']->format('d/m/Y') }} · {{ $movimiento['fecha']->format('H:i') }} hrs</time>
                                    @if ($movimiento['motivo'])
                                        <span>Motivo: {{ $movimiento['motivo'] }}</span>
                                    @endif
                                </p>

                                {{-- Los cambios se muestran solo si la persona los abre, para no saturar la lista --}}
                                @if ($movimiento['detalles'] !== [])
                                    @php($totalCambios = count($movimiento['detalles']) + $movimiento['mas_detalles'])
                                    <details class="actividad__cambios">
                                        <summary>Ver {{ $totalCambios === 1 ? 'el cambio' : "los {$totalCambios} cambios" }}</summary>
                                        <ul>
                                            @foreach ($movimiento['detalles'] as $detalle)
                                                <li>{{ $detalle }}</li>
                                            @endforeach
                                            @if ($movimiento['mas_detalles'] > 0)
                                                <li>Y {{ $movimiento['mas_detalles'] }} más en la bitácora completa.</li>
                                            @endif
                                        </ul>
                                    </details>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>

                {{-- Mientras no exista la consulta completa (M9), se avisa que hay más movimientos --}}
                @if ($hayMasMovimientos && ! Route::has('bitacora.index'))
                    <p class="actividad__mas">Se muestran los 5 movimientos más recientes. La consulta completa de la bitácora estará disponible próximamente.</p>
                @endif
            @endif
        </section>
    @endif
@endsection

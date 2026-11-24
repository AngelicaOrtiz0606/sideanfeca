{{--
    Consulta de un puesto (solo lectura): sus datos y el historial de las
    personas que lo han ocupado, de la más reciente a la más antigua.
    Una persona oculta se lista sin su nombre. Se edita desde la lista.
--}}
@extends('layouts.admin')

@section('titulo', $nombre)

@php
    $fecha = fn (?string $dia) => $dia === null ? null : \Illuminate\Support\Carbon::parse($dia)->locale('es')->translatedFormat('j \d\e F \d\e Y');
    $hoy = now()->toDateString();
@endphp

@section('contenido')
    <a href="{{ route('puestos.index') }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        Puestos
    </a>

    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">{{ $puesto->cargo }}</h1>
            <div class="pagina__detalles">
                <span class="nivel-cargo nivel-cargo--{{ strtolower($puesto->nivel) }}">{{ $puesto->nivel }}</span>
                <span @class(['estado', 'estado--activo' => $puesto->activo])>{{ $puesto->activo ? 'Activo' : 'Desactivado' }}</span>
                @if ($puesto->titular)
                    <span class="insignia-titular">Titular de la institución</span>
                @endif
            </div>
        </div>
    </header>

    <x-aviso-estado />

    <div class="ficha">
        <section class="tarjeta" aria-labelledby="ficha-puesto">
            <div class="ficha__encabezado">
                <h2 id="ficha-puesto" class="ficha__titulo">Puesto</h2>
            </div>
            <dl class="ficha__datos">
                <div>
                    <dt>Cargo</dt>
                    <dd>{{ $puesto->cargo }}@if (filled($puesto->cargo_f) && $puesto->cargo_f !== $puesto->cargo) / {{ $puesto->cargo_f }}@endif</dd>
                </div>
                @if ($puesto->institucion)
                    <div>
                        <dt>Institución</dt>
                        <dd><a href="{{ route('instituciones.show', $puesto->institucion_id) }}" class="ficha__enlace">{{ $puesto->institucion }}</a></dd>
                    </div>
                @endif
                @if ($puesto->organizacion)
                    <div>
                        <dt>Organización</dt>
                        <dd>{{ $puesto->organizacion }}</dd>
                    </div>
                @endif
                @if ($puesto->zona_numero)
                    <div>
                        <dt>Zona</dt>
                        <dd>Zona {{ $puesto->zona_numero }} · {{ $puesto->zona_nombre }}</dd>
                    </div>
                @endif
                @if ($puesto->coordinacion)
                    <div>
                        <dt>Coordinación</dt>
                        <dd><span class="etiqueta-coordinacion">{{ $puesto->coordinacion }}</span></dd>
                    </div>
                @endif
                @if ($puesto->nivel !== 'Institucional')
                    <div>
                        <dt>Aparece en</dt>
                        <dd>{{ $directorios === [] ? 'Ningún directorio' : implode(', ', $directorios) }}</dd>
                    </div>
                @endif
            </dl>
        </section>
    </div>

    <section class="tarjeta tarjeta--tabla" aria-labelledby="historial-titulo">
        <div class="tarjeta__barra">
            <div>
                <h2 id="historial-titulo" class="ficha__titulo">Quiénes lo han ocupado</h2>
                <p class="tarjeta__texto">De la más reciente a la más antigua.</p>
            </div>
        </div>

        @if ($historial->isEmpty())
            <p class="vacio vacio--en-tabla">Nadie ha ocupado este puesto todavía.</p>
        @else
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Persona</th>
                        <th scope="col">Periodo</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($historial as $designacion)
                        @php
                            $vigente = $designacion->desde <= $hoy && ($designacion->hasta === null || $designacion->hasta >= $hoy);
                            $porVenir = $designacion->desde > $hoy;
                        @endphp
                        <tr>
                            <th scope="row" data-etiqueta="Persona">
                                @if ($designacion->oculta)
                                    <span class="tabla__vacio">Persona oculta</span>
                                @else
                                    @can(\App\Enums\Privilegio::GestionarPersonas->value)
                                        <a href="{{ route('personas.show', ['persona' => $designacion->persona, 'pestana' => 'designaciones']) }}" class="tabla__principal tabla__enlace">{{ $designacion->nombre }}</a>
                                    @else
                                        <span class="tabla__principal">{{ $designacion->nombre }}</span>
                                    @endcan
                                @endif
                            </th>
                            <td data-etiqueta="Periodo">{{ $fecha($designacion->desde) }} – {{ $designacion->hasta ? $fecha($designacion->hasta) : 'a la fecha' }}</td>
                            <td data-etiqueta="Estado">
                                <span @class(['estado', 'estado--activo' => $vigente])>{{ $vigente ? 'Vigente' : ($porVenir ? 'Por iniciar' : 'Terminada') }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <x-paginacion :paginas="$historial" />
        @endif
    </section>
@endsection

{{--
    Consulta de una institución (solo lectura), por pestañas: General,
    Personas (de hoy y anteriores), Participación, Razón social y, si es
    universidad, Sedes. Todo se cambia
    desde "Editar", que abre el mismo formulario del registro ya lleno.
    Las pestañas son enlaces (?pestana=…); pestanas.js las cambia sin recargar.
--}}
@extends('layouts.admin')

@section('titulo', $institucion->nombre)

@php
    $fecha = fn (?string $dia) => $dia === null ? null : \Illuminate\Support\Carbon::parse($dia)->locale('es')->translatedFormat('j \d\e F \d\e Y');
    $domicilio = $institucion->domicilio;
@endphp

@section('contenido')
    <a href="{{ route('instituciones.index') }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        Instituciones
    </a>

    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">{{ $institucion->nombre }}</h1>
            <div class="pagina__detalles">
                <span class="participacion participacion--{{ strtolower($institucion->participacion) }}">{{ $institucion->participacion }}</span>
                <span @class(['estado', 'estado--activo' => $institucion->activa])>{{ $institucion->activa ? 'Activa' : 'Desactivada' }}</span>
            </div>
        </div>

        <div class="pagina__acciones">
            <a href="{{ route('instituciones.edit', $institucion->id) }}" class="boton boton--primario">
                <x-icono nombre="lapiz" />
                Editar
            </a>
        </div>
    </header>

    <x-aviso-estado />

    <div class="pestanas" data-pestanas>
        <nav class="pestanas__lista" role="tablist" aria-label="Información de la institución">
            @foreach ($pestanas as $clave => $titulo)
                <a href="{{ route('instituciones.show', ['institucion' => $institucion->id, 'pestana' => $clave]) }}"
                   id="pestana-{{ $clave }}" class="pestanas__pestana" role="tab"
                   aria-controls="panel-{{ $clave }}" aria-selected="{{ $pestana === $clave ? 'true' : 'false' }}"
                   @if ($pestana !== $clave) tabindex="-1" @endif data-pestana="{{ $clave }}">
                    {{ $titulo }}
                    @if ($clave === 'sedes')
                        <span class="pestanas__cifra">{{ $sedes->total() }}</span>
                    @elseif ($clave === 'personas')
                        <span class="pestanas__cifra">{{ $personas->total() }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        {{-- General --}}
        <section id="panel-general" class="pestanas__panel" role="tabpanel" aria-labelledby="pestana-general" @if ($pestana !== 'general') hidden @endif>
            <div class="ficha">
                <section class="tarjeta" aria-labelledby="ficha-identificacion">
                    <div class="ficha__encabezado">
                        <h2 id="ficha-identificacion" class="ficha__titulo">Identificación</h2>
                    </div>
                    <dl class="ficha__datos">
                        <div>
                            <dt>Tipo</dt>
                            <dd>{{ $institucion->tipo }}</dd>
                        </div>
                        @if ($institucion->universidad)
                            <div>
                                <dt>Universidad</dt>
                                <dd><a href="{{ route('instituciones.show', $institucion->universidad_id) }}" class="ficha__enlace">{{ $institucion->universidad }}</a></dd>
                            </div>
                        @endif
                        <div>
                            <dt>Sector</dt>
                            <dd>{{ $sectores[$institucion->sector] ?? $institucion->sector }}</dd>
                        </div>
                        @if ($institucion->numero)
                            <div>
                                <dt>Número de afiliación</dt>
                                <dd><span class="numero-afiliacion">{{ $institucion->numero }}</span></dd>
                            </div>
                        @endif
                    </dl>
                </section>

                <section class="tarjeta" aria-labelledby="ficha-ubicacion">
                    <div class="ficha__encabezado">
                        <h2 id="ficha-ubicacion" class="ficha__titulo">Ubicación</h2>
                    </div>
                    @if ($domicilio)
                        <address class="ficha__domicilio">
                            {{ $domicilio->calle }} {{ $domicilio->num_ext }}@if ($domicilio->num_int), int. {{ $domicilio->num_int }}@endif<br>
                            Col. {{ $domicilio->colonia }}, C.P. {{ $domicilio->codigo_postal }}<br>
                            {{ $domicilio->municipio }}, {{ $domicilio->entidad }}
                        </address>
                    @else
                        <p class="ficha__vacio">Sin domicilio. Una matriz no lo necesita.</p>
                    @endif
                    @if ($institucion->zona_numero)
                        <dl class="ficha__datos">
                            <div>
                                <dt>Zona regional</dt>
                                <dd><span class="numero-zona">{{ $institucion->zona_numero }}</span> {{ $institucion->zona_nombre }}</dd>
                            </div>
                        </dl>
                    @endif
                </section>

                <section class="tarjeta" aria-labelledby="ficha-web">
                    <div class="ficha__encabezado">
                        <h2 id="ficha-web" class="ficha__titulo">Sitio web</h2>
                    </div>
                    @if ($institucion->sitio_web)
                        <a href="{{ $institucion->sitio_web }}" class="ficha__enlace ficha__enlace--web" target="_blank" rel="noopener noreferrer">{{ $institucion->sitio_web }}</a>
                    @else
                        <p class="ficha__vacio">Sin sitio web.</p>
                    @endif
                </section>
            </div>
        </section>

        {{-- Participación --}}
        <section id="panel-participacion" class="pestanas__panel" role="tabpanel" aria-labelledby="pestana-participacion" @if ($pestana !== 'participacion') hidden @endif>
            <div class="tarjeta historial">
                <ol class="linea-tiempo">
                    @foreach ($participaciones as $periodo)
                        <li @class(['linea-tiempo__item', 'linea-tiempo__item--actual' => $periodo->hasta === null])>
                            <span class="participacion participacion--{{ strtolower($periodo->tipo) }}">{{ $periodo->tipo }}</span>
                            <span class="linea-tiempo__fechas">
                                {{ $fecha($periodo->desde) }} – {{ $periodo->hasta ? $fecha($periodo->hasta) : 'hoy' }}
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Razón social --}}
        <section id="panel-razon-social" class="pestanas__panel" role="tabpanel" aria-labelledby="pestana-razon-social" @if ($pestana !== 'razon-social') hidden @endif>
            <div class="tarjeta historial">
                <ol class="linea-tiempo">
                    @foreach ($nombres as $nombre)
                        <li @class(['linea-tiempo__item', 'linea-tiempo__item--actual' => $nombre->hasta === null])>
                            <span class="linea-tiempo__titulo">
                                <span class="linea-tiempo__nombre">{{ $nombre->nombre }}</span>
                                @if ($nombre->hasta === null)
                                @endif
                            </span>
                            <span class="linea-tiempo__fechas">
                                {{ $fecha($nombre->desde) }} – {{ $nombre->hasta ? $fecha($nombre->hasta) : 'hoy' }}
                            </span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Personas: quienes ocupan u ocuparon un puesto de la institución --}}
        <section id="panel-personas" class="pestanas__panel" role="tabpanel" aria-labelledby="pestana-personas" @if ($pestana !== 'personas') hidden @endif>
            <div class="tarjeta tarjeta--tabla">
                <div class="tarjeta__barra">
                    <p class="tarjeta__texto">Primero quienes están hoy (el titular va primero) y después quienes estuvieron antes.</p>
                </div>

                @if ($personas->isEmpty())
                    <p class="vacio vacio--en-tabla">Nadie ha ocupado todavía un puesto de esta institución.</p>
                @else
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th scope="col">Persona</th>
                                <th scope="col">Cargo</th>
                                <th scope="col">Periodo</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($personas as $persona)
                                <tr>
                                    <th scope="row" data-etiqueta="Persona">
                                        @if ($persona->oculta)
                                            <span class="tabla__vacio">Persona oculta</span>
                                        @else
                                            @can(\App\Enums\Privilegio::GestionarPersonas->value)
                                                <a href="{{ route('personas.show', $persona->persona) }}" class="tabla__principal tabla__enlace">{{ $persona->nombre }}</a>
                                            @else
                                                <span class="tabla__principal">{{ $persona->nombre }}</span>
                                            @endcan
                                        @endif
                                    </th>
                                    <td data-etiqueta="Cargo">
                                        {{ $persona->cargo }}
                                        @if ($persona->titular)
                                            <span class="insignia-titular">Titular</span>
                                        @endif
                                    </td>
                                    <td data-etiqueta="Periodo">{{ $fecha($persona->desde) }} – {{ $persona->hasta ? $fecha($persona->hasta) : 'a la fecha' }}</td>
                                    <td data-etiqueta="Estado">
                                        <span @class(['estado', 'estado--activo' => $persona->vigente])>{{ $persona->vigente ? 'Vigente' : ($persona->por_iniciar ? 'Por iniciar' : 'Terminada') }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <x-paginacion :paginas="$personas" />
                @endif
            </div>
        </section>

        {{-- Sedes --}}
        @if (array_key_exists('sedes', $pestanas))
            <section id="panel-sedes" class="pestanas__panel" role="tabpanel" aria-labelledby="pestana-sedes" @if ($pestana !== 'sedes') hidden @endif>
                <div class="tarjeta tarjeta--tabla">
                    @if ($sedes->isEmpty())
                        <p class="vacio vacio--en-tabla">No tiene facultades ni campus registrados.</p>
                    @else
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th scope="col">Número</th>
                                    <th scope="col">Facultad o campus</th>
                                    <th scope="col">Participación</th>
                                    <th scope="col">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sedes as $sede)
                                    <tr @class(['tabla__fila--inactiva' => ! $sede->activa])>
                                        <td data-etiqueta="Número">
                                            @if ($sede->numero)
                                                <span class="numero-afiliacion">{{ $sede->numero }}</span>
                                            @else
                                                <span class="tabla__vacio" aria-label="Sin número">—</span>
                                            @endif
                                        </td>
                                        <th scope="row" data-etiqueta="Facultad o campus">
                                            <a href="{{ route('instituciones.show', $sede->id) }}" class="tabla__principal tabla__enlace">{{ $sede->nombre }}</a>
                                            <span class="tabla__detalle">{{ $sede->tipo }}</span>
                                        </th>
                                        <td data-etiqueta="Participación">
                                            <span class="participacion participacion--{{ strtolower($sede->participacion) }}">{{ $sede->participacion }}</span>
                                        </td>
                                        <td data-etiqueta="Estado">
                                            <span @class(['estado', 'estado--activo' => $sede->activa])>{{ $sede->activa ? 'Activa' : 'Desactivada' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <x-paginacion :paginas="$sedes" />
                    @endif
                </div>
            </section>
        @endif
    </div>

@endsection

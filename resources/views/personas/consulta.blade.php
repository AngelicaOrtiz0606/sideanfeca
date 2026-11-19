{{--
    Consulta de una persona (solo lectura), por pestañas: General (datos,
    pertenencia y grados), Contactos y Cargos. Todo se cambia desde "Editar",
    que abre el formulario completo ya lleno.
--}}
@extends('layouts.admin')

@section('titulo', $nombre)

@php
    $fecha = fn (?string $dia) => $dia === null ? null : \Illuminate\Support\Carbon::parse($dia)->locale('es')->translatedFormat('j \d\e F \d\e Y');
    $hoy = now()->toDateString();
@endphp

@section('contenido')
    <a href="{{ route('personas.index') }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        Personas
    </a>

    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">{{ $nombre }}</h1>
            <div class="pagina__detalles">
                <span @class(['estado', 'estado--activo' => $persona->cargos->isNotEmpty()])>{{ $persona->cargos->isNotEmpty() ? 'Con cargo vigente' : 'Sin cargo vigente' }}</span>
            </div>
        </div>

        <div class="pagina__acciones">
            <a href="{{ route('personas.edit', $persona->id) }}" class="boton boton--primario">
                <x-icono nombre="lapiz" />
                Editar
            </a>
        </div>
    </header>

    <x-aviso-estado />

    <div class="pestanas" data-pestanas>
        <nav class="pestanas__lista" role="tablist" aria-label="Información de la persona">
            @foreach ($pestanas as $clave => $titulo)
                <a href="{{ route('personas.show', ['persona' => $persona->id, 'pestana' => $clave]) }}"
                   id="pestana-{{ $clave }}" class="pestanas__pestana" role="tab"
                   aria-controls="panel-{{ $clave }}" aria-selected="{{ $pestana === $clave ? 'true' : 'false' }}"
                   @if ($pestana !== $clave) tabindex="-1" @endif data-pestana="{{ $clave }}">
                    {{ $titulo }}
                    @if ($clave === 'contactos')
                        <span class="pestanas__cifra">{{ $contactos->count() }}</span>
                    @elseif ($clave === 'cargos')
                        <span class="pestanas__cifra">{{ $cargos->count() }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        {{-- General --}}
        <section id="panel-general" class="pestanas__panel" role="tabpanel" aria-labelledby="pestana-general" @if ($pestana !== 'general') hidden @endif>
            <div class="ficha">
                <section class="tarjeta" aria-labelledby="ficha-datos">
                    <div class="ficha__encabezado">
                        <h2 id="ficha-datos" class="ficha__titulo">Datos personales</h2>
                    </div>
                    <dl class="ficha__datos">
                        <div>
                            <dt>Género</dt>
                            <dd>{{ $generos[$persona->genero] ?? $persona->genero }}</dd>
                        </div>
                        <div>
                            <dt>CURP</dt>
                            <dd>{{ $persona->curp ?? 'Sin CURP' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="tarjeta" aria-labelledby="ficha-pertenencia">
                    <div class="ficha__encabezado">
                        <h2 id="ficha-pertenencia" class="ficha__titulo">Pertenece a</h2>
                    </div>
                    @if ($persona->institucion)
                        <a href="{{ route('instituciones.show', $persona->institucion_id) }}" class="ficha__enlace">{{ $persona->institucion }}</a>
                        @if ($persona->zona_numero)
                            <p class="ficha__vacio">Zona {{ $persona->zona_numero }} · {{ $persona->zona_nombre }}</p>
                        @endif
                    @elseif ($persona->organizacion)
                        <p>{{ $persona->organizacion }}</p>
                        <p class="ficha__vacio">Organización</p>
                    @else
                        <p class="ficha__vacio">Sin pertenencia. Para recibir un cargo necesita pertenecer a una institución u organización.</p>
                    @endif
                </section>

                <section class="tarjeta" aria-labelledby="ficha-grados">
                    <div class="ficha__encabezado">
                        <h2 id="ficha-grados" class="ficha__titulo">Grados académicos</h2>
                    </div>
                    @if ($persona->grados->isEmpty())
                        <p class="ficha__vacio">Sin grados registrados.</p>
                    @else
                        <ul class="ficha__lista">
                            @foreach ($persona->grados as $grado)
                                <li>{{ $grado->nombre }} <span class="ficha__abreviatura">{{ $persona->genero === 'F' ? $grado->abr_f : $grado->abr_m }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </section>

        {{-- Contactos --}}
        <section id="panel-contactos" class="pestanas__panel" role="tabpanel" aria-labelledby="pestana-contactos" @if ($pestana !== 'contactos') hidden @endif>
            <div class="tarjeta tarjeta--tabla">
                <p class="tarjeta__nota">Solo los visibles aparecen en el sitio de ANFECA.</p>

                @if ($contactos->isEmpty())
                    <p class="vacio vacio--en-tabla">Sin contactos registrados.</p>
                @else
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th scope="col">Tipo</th>
                                <th scope="col">Contacto</th>
                                <th scope="col">En el sitio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($contactos as $contacto)
                                @php
                                    $texto = $contacto->tipo === 'Correo' ? $contacto->valor
                                        : trim(($contacto->lada ? "({$contacto->lada}) " : '').$contacto->valor.($contacto->extension ? " ext. {$contacto->extension}" : ''));
                                @endphp
                                <tr>
                                    <th scope="row" data-etiqueta="Tipo"><span class="tabla__principal">{{ $contacto->tipo }}</span></th>
                                    <td data-etiqueta="Contacto">{{ $texto }}</td>
                                    <td data-etiqueta="En el sitio">
                                        <span @class(['estado', 'estado--activo' => $contacto->visible])>{{ $contacto->visible ? 'Visible' : 'Oculto' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>

        {{-- Cargos --}}
        <section id="panel-cargos" class="pestanas__panel" role="tabpanel" aria-labelledby="pestana-cargos" @if ($pestana !== 'cargos') hidden @endif>
            <div class="tarjeta">
                @if ($cargos->isEmpty())
                    <p class="vacio">Todavía no tiene cargos.</p>
                @else
                    <ol class="linea-tiempo">
                        @foreach ($cargos as $cargo)
                            @php
                                $vigente = $cargo->desde <= $hoy && ($cargo->hasta === null || $cargo->hasta >= $hoy);
                            @endphp
                            <li @class(['linea-tiempo__item', 'linea-tiempo__item--actual' => $vigente])>
                                <span class="linea-tiempo__nombre">{{ $cargo->nombre }}</span>
                                <span class="linea-tiempo__fechas">{{ $fecha($cargo->desde) }} – {{ $cargo->hasta ? $fecha($cargo->hasta) : 'hoy' }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </section>
    </div>

@endsection

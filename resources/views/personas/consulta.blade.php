{{--
    Consulta de una persona, por pestañas: General (datos, pertenencia y
    grados), Contactos y Designaciones. Los datos se cambian desde "Editar"
    (el formulario completo, ya lleno). En Designaciones: agregar, terminar
    (la persona deja el puesto) y borrar la capturada por error.
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
                <span @class(['estado', 'estado--activo' => $persona->cargos->isNotEmpty()])>{{ $persona->cargos->isNotEmpty() ? 'Con designación vigente' : 'Sin designación vigente' }}</span>
            </div>
        </div>

        <div class="pagina__acciones">
            @can(\App\Enums\Privilegio::GestionarUsuarios->value)
                @can(\App\Enums\Privilegio::EliminarDefinitivo->value)
                    <button type="button" class="boton boton--discreto"
                            data-abrir-dialogo="dialogo-arco"
                            data-accion="{{ route('personas.arco', $persona->id) }}"
                            data-texto="«{{ $nombre }}»"
                            data-valores="{{ json_encode(['_id' => $persona->id, 'confirmacion' => '']) }}">
                        Cancelación ARCO
                    </button>
                @endcan
            @endcan
            <button type="button" @class(['boton', 'boton--secundario', 'accion--bloqueada' => $motivoParaNoBorrarPersona])
                    data-abrir-dialogo="dialogo-borrar-persona"
                    data-accion="{{ route('personas.destroy', $persona->id) }}"
                    data-titulo="¿Borrar a {{ $nombre }}?"
                    data-texto="{{ $efectoDeBorrar }} Queda en la bitácora con su motivo."
                    data-bloqueo="{{ $motivoParaNoBorrarPersona }}"
                    data-valores="{{ json_encode(['_id' => $persona->id, 'motivo' => '']) }}">
                <x-icono nombre="papelera" />
                Borrar
            </button>
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
                    @elseif ($clave === 'designaciones')
                        <span class="pestanas__cifra">{{ $designaciones->count() }}</span>
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

        {{-- Designaciones: los puestos que ocupa u ocupó --}}
        <section id="panel-designaciones" class="pestanas__panel" role="tabpanel" aria-labelledby="pestana-designaciones" @if ($pestana !== 'designaciones') hidden @endif>
            <div class="tarjeta tarjeta--tabla">
                @can(\App\Enums\Privilegio::GestionarDesignaciones->value)
                    <div class="tarjeta__barra">
                        <p class="tarjeta__texto">Los puestos que ocupa u ocupó en ANFECA.</p>
                        <a href="{{ route('personas.designaciones.create', $persona->id) }}" class="boton boton--secundario">
                            <x-icono nombre="mas" />
                            Agregar designación
                        </a>
                    </div>
                @endcan

                @if ($designaciones->isEmpty())
                    <p class="vacio vacio--en-tabla">Todavía no tiene designaciones.</p>
                @else
                    <table class="tabla">
                        <thead>
                            <tr>
                                <th scope="col">Puesto</th>
                                <th scope="col">Periodo</th>
                                <th scope="col">Estado</th>
                                @can(\App\Enums\Privilegio::GestionarDesignaciones->value)
                                    <th scope="col" class="tabla__acciones"><span class="solo-lectores">Acciones</span></th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($designaciones as $designacion)
                                @php
                                    $vigente = $designacion->desde <= $hoy && ($designacion->hasta === null || $designacion->hasta >= $hoy);
                                    $porVenir = $designacion->desde > $hoy;
                                    // El primer día que ya no lo ocupa debe ser después de su inicio
                                    $minimo = \Illuminate\Support\Carbon::parse($designacion->desde)->addDay()->toDateString();
                                @endphp
                                <tr>
                                    <th scope="row" data-etiqueta="Puesto"><span class="tabla__principal">{{ $designacion->nombre }}</span></th>
                                    <td data-etiqueta="Periodo">{{ $fecha($designacion->desde) }} – {{ $designacion->hasta ? $fecha($designacion->hasta) : 'a la fecha' }}</td>
                                    <td data-etiqueta="Estado">
                                        <span @class(['estado', 'estado--activo' => $vigente])>{{ $vigente ? 'Vigente' : ($porVenir ? 'Por iniciar' : 'Terminada') }}</span>
                                    </td>
                                    @can(\App\Enums\Privilegio::GestionarDesignaciones->value)
                                        <td class="tabla__acciones">
                                            <div class="acciones-fila">
                                                @if ($designacion->hasta === null)
                                                    <button type="button" class="accion accion--desactivar"
                                                            data-abrir-dialogo="dialogo-terminar"
                                                            data-accion="{{ route('personas.designaciones.terminar', [$persona->id, $designacion->id]) }}"
                                                            data-texto="{{ $designacion->nombre }}, desde el {{ $fecha($designacion->desde) }}."
                                                            data-valores="{{ json_encode(['fecha' => max($hoy, $minimo)]) }}"
                                                            data-atributos="{{ json_encode(['fecha' => ['min' => $minimo]]) }}">
                                                        <x-icono nombre="pausa" />
                                                        <span class="accion__texto">Terminar designación <span class="solo-lectores">{{ $designacion->nombre }}</span></span>
                                                    </button>
                                                @endif
                                                <button type="button" @class(['accion', 'accion--borrar', 'accion--a-la-derecha', 'accion--bloqueada' => $motivoParaNoBorrar])
                                                        data-abrir-dialogo="dialogo-borrar"
                                                        data-bloqueo="{{ $motivoParaNoBorrar }}"
                                                        data-accion="{{ route('personas.designaciones.destroy', [$persona->id, $designacion->id]) }}"
                                                        data-titulo="¿Borrar la designación {{ $designacion->nombre }}?"
                                                        data-texto="Solo si se capturó por error: la designación desaparece de su historial. Si la persona dejó el puesto, usa «Terminar designación». Queda en la bitácora con su motivo."
                                                        data-valores="{{ json_encode(['_id' => $designacion->id, 'motivo' => '']) }}">
                                                    <x-icono nombre="papelera" />
                                                    <span class="accion__texto">Borrar designación <span class="solo-lectores">{{ $designacion->nombre }}</span></span>
                                                </button>
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>
    </div>

    @include('catalogos._dialogo-borrar', [
        'dialogo' => 'dialogo-borrar-persona',
        'motivos' => $motivosParaBorrarPersona,
        'abrir' => $errors->has('motivo') && (int) old('_id') === (int) $persona->id,
        'accion' => route('personas.destroy', $persona->id),
        'titulo' => "¿Borrar a {$nombre}?",
    ])
    @can(\App\Enums\Privilegio::GestionarUsuarios->value)
        @include('personas._dialogo-arco', [
            'abrir' => $errors->has('confirmacion'),
            'accion' => route('personas.arco', $persona->id),
        ])
    @endcan

    @can(\App\Enums\Privilegio::GestionarDesignaciones->value)
        @include('personas._dialogo-terminar')
        @include('catalogos._dialogo-borrar', [
            'motivos' => $motivosParaBorrar,
            'abrir' => false,
            'accion' => '',
            'titulo' => 'Borrar designación',
        ])
    @endcan
@endsection

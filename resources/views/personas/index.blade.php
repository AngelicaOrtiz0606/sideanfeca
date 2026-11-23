{{--
    Personas: lista con filtros en el servidor (pueden ser cientos), de 10 en 10.
    El buscador se aplica solo al dejar de escribir; las listas, al cambiarlas.
    Filtros: el buscador (nombre, CURP, correo, institución u organización, o
    cargo vigente), el cargo, la zona (de su institución o de su cargo) y el
    estado (con o sin designación vigente).
    Cada fila: Consultar (ficha), Editar (el formulario completo, ya lleno) y
    Borrar (sin cuenta se elimina; con cuenta se oculta; no con una
    designación vigente). Las ocultas solo las ve el superadministrador, en
    "Personas ocultas".
--}}
@extends('layouts.admin')

@section('titulo', 'Personas')

@inject('servicioPersonas', 'App\Services\Personas\PersonaService')

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

        <div class="pagina__acciones">
            @can(\App\Enums\Privilegio::GestionarUsuarios->value)
                <a href="{{ route('personas.ocultas') }}" class="boton boton--secundario">
                    <x-icono nombre="ojo-tachado" />
                    Personas ocultas
                </a>
            @endcan
            <a href="{{ route('personas.create') }}" class="boton boton--primario">
                <x-icono nombre="mas" />
                Nueva persona
            </a>
        </div>
    </header>

    <x-aviso-estado />

    <section class="tarjeta tarjeta--tabla" aria-label="Lista de personas">
        <form method="GET" action="{{ route('personas.index') }}" class="filtros filtros--servidor" role="search" data-filtros-servidor>
            <label for="buscar-persona" class="solo-lectores">Buscar por nombre, CURP, correo, institución u organización, o cargo</label>
            <input id="buscar-persona" name="texto" type="search" class="campo__entrada filtros__buscar"
                   placeholder="Nombre, CURP, correo o cargo" value="{{ $filtros['texto'] }}" maxlength="100"
                   data-filtro-escrito @if ($filtros['texto'] !== null) autofocus @endif>

            <button type="button" class="filtros__resumen" aria-expanded="{{ $activos > 0 ? 'true' : 'false' }}" aria-controls="filtros-opciones" data-filtros-boton>
                <x-icono nombre="ordenar" />
                Filtros @if ($activos > 0)<span class="filtro__cifra">({{ $activos }})</span>@endif
            </button>
            <div class="filtros__opciones" id="filtros-opciones" data-filtros-opciones @if ($activos === 0) data-cerrado @endif>
                <label class="filtros__selector">
                    <span class="filtros__etiqueta">Cargo</span>
                    <select name="cargo" class="campo__entrada">
                        <option value="">Todos</option>
                        @foreach (['Nacional' => 'Nacionales', 'Regional' => 'Regionales', 'Institucional' => 'De una institución'] as $nivel => $grupo)
                            <optgroup label="{{ $grupo }}">
                                @foreach ($cargos->where('nivel', $nivel) as $cargo)
                                    <option value="{{ $cargo->id }}" @selected($filtros['cargo'] === $cargo->id)>{{ $cargo->nombre }}</option>
                                @endforeach
                            </optgroup>
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
                    <span class="filtros__etiqueta">Estado</span>
                    <select name="situacion" class="campo__entrada">
                        <option value="">Todas</option>
                        <option value="con-cargo" @selected($filtros['situacion'] === 'con-cargo')>Con designación vigente</option>
                        <option value="sin-cargo" @selected($filtros['situacion'] === 'sin-cargo')>Sin designación vigente</option>
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
                        <th scope="col">Designación vigente</th>
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
                            <td data-etiqueta="Designación vigente">
                                @forelse ($persona->cargos as $cargo)
                                    <span class="tabla__linea">{{ $cargo }}</span>
                                @empty
                                    <span class="tabla__vacio">Sin designación vigente</span>
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
                                    @php
                                        $bloqueo = $servicioPersonas->motivoParaNoBorrar(auth()->user(), $persona);
                                    @endphp
                                    <button type="button" @class(['accion', 'accion--borrar', 'accion--a-la-derecha', 'accion--bloqueada' => $bloqueo])
                                            data-abrir-dialogo="dialogo-borrar"
                                            data-accion="{{ route('personas.destroy', $persona->id) }}"
                                            data-titulo="¿Borrar a {{ $nombre }}?"
                                            data-texto="{{ $servicioPersonas->efectoDeBorrar($persona) }} Queda en la bitácora con su motivo."
                                            data-bloqueo="{{ $bloqueo }}"
                                            data-valores="{{ json_encode(['_id' => $persona->id, 'motivo' => '']) }}">
                                        <x-icono nombre="papelera" />
                                        <span class="accion__texto">Borrar <span class="solo-lectores">{{ $nombre }}</span></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <x-paginacion :paginas="$personas" />
        @endif
    </section>

    @php
        $borrarConError = $errors->has('motivo') ? $personas->getCollection()->firstWhere('id', (int) old('_id')) : null;
    @endphp
    @include('catalogos._dialogo-borrar', [
        'motivos' => $motivos,
        'abrir' => $borrarConError !== null,
        'accion' => $borrarConError ? route('personas.destroy', $borrarConError->id) : '',
        'titulo' => $borrarConError ? "¿Borrar a {$servicioPersonas->nombreCompleto($borrarConError)}?" : 'Borrar persona',
    ])
@endsection

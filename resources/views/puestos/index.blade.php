{{--
    Catálogo de puestos: un cargo en un ámbito concreto (una institución, una
    zona con o sin coordinación, o nacional). Todo desde la lista: "Nuevo
    puesto" y "Editar" abren una ventana; "Desactivar" pide confirmación y
    "Activar" es un clic; "Borrar" pide el motivo o explica por qué no se puede.
--}}
@extends('layouts.admin')

@section('titulo', 'Puestos')

@php
    $borrarConError = $errors->has('motivo') ? $puestos->firstWhere('id', (int) old('_id')) : null;
@endphp

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Puestos</h1>
            <p class="pagina__texto">Cada cargo en su ámbito: una institución, una zona regional o nacional. A los puestos se designan las personas.</p>
        </div>

        <div class="pagina__acciones">
            <button type="button" class="boton boton--primario"
                    data-abrir-dialogo="dialogo-puesto"
                    data-accion="{{ route('puestos.store') }}" data-metodo="POST"
                    data-titulo="Nuevo puesto" data-boton="Registrar puesto" data-ambito-fijo=""
                    data-valores="{{ json_encode(['cargo' => '', 'zona' => '', 'coordinacion' => '', 'institucion' => '', 'organizacion' => '', 'titular' => false, '_id' => '']) }}">
                <x-icono nombre="mas" />
                Nuevo puesto
            </button>
        </div>
    </header>

    <x-aviso-estado />

    <section class="tarjeta tarjeta--tabla" aria-label="Lista de puestos">
        @if ($puestos->isEmpty())
            <p class="vacio vacio--en-tabla">Todavía no hay puestos. Registra el primero con <strong>Nuevo puesto</strong>.</p>
        @else
            @php
                $activos = collect([$filtros['nivel'], $filtros['coordinacion'], $filtros['zona']])->filter()->count() + ($filtros['orden'] !== 'puesto' ? 1 : 0);
                $hayFiltros = $filtros['texto'] !== null || $activos > 0;
            @endphp
            <form method="GET" action="{{ route('puestos.index') }}" class="filtros filtros--servidor" role="search" data-filtros-servidor>
                <label for="buscar-puesto" class="solo-lectores">Buscar puesto o persona</label>
                <input id="buscar-puesto" name="texto" type="search" class="campo__entrada filtros__buscar"
                       placeholder="Buscar puesto o persona" value="{{ $filtros['texto'] }}" maxlength="100"
                       data-filtro-escrito @if ($filtros['texto'] !== null) autofocus @endif>

                <button type="button" class="filtros__resumen" aria-expanded="{{ $activos > 0 ? 'true' : 'false' }}" aria-controls="filtros-opciones" data-filtros-boton>
                    <x-icono nombre="ordenar" />
                    Filtros @if ($activos > 0)<span class="filtro__cifra">({{ $activos }})</span>@endif
                </button>
                <div class="filtros__opciones" id="filtros-opciones" data-filtros-opciones @if ($activos === 0) data-cerrado @endif>
                    <label class="filtros__selector">
                        <span class="filtros__etiqueta">Nivel</span>
                        <select name="nivel" class="campo__entrada">
                            <option value="">Todos</option>
                            @foreach ($niveles as $nivel)
                                <option value="{{ $nivel }}" @selected($filtros['nivel'] === $nivel)>{{ $nivel }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="filtros__selector">
                        <span class="filtros__etiqueta">Coordinación</span>
                        <select name="coordinacion" class="campo__entrada">
                            <option value="">Todas</option>
                            @foreach ($coordinaciones as $coordinacion)
                                <option value="{{ $coordinacion->id }}" @selected($filtros['coordinacion'] === $coordinacion->id)>{{ $coordinacion->nombre }}</option>
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
                        <span class="filtros__etiqueta">Ordenar por</span>
                        <select name="orden" class="campo__entrada">
                            @foreach ($ordenes as $valor => $texto)
                                {{-- El orden de siempre va sin valor: así no se marca como filtro elegido --}}
                                <option value="{{ $valor === 'puesto' ? '' : $valor }}" @selected($filtros['orden'] === $valor)>{{ $texto }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                @if ($hayFiltros)
                    <a href="{{ route('puestos.index') }}" class="boton boton--discreto filtros__borrar">
                        <x-icono nombre="cerrar" />
                        Borrar filtros
                    </a>
                @endif
            </form>
            @if ($pagina->isEmpty())
                <p class="vacio vacio--en-tabla">Ningún puesto coincide con la búsqueda.</p>
            @else
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col" @if ($filtros['orden'] === 'puesto') aria-sort="ascending" @endif>Puesto</th>
                        <th scope="col" @if ($filtros['orden'] === 'coordinacion') aria-sort="ascending" @endif>Coordinación</th>
                        <th scope="col">Nivel</th>
                        <th scope="col" @if ($filtros['orden'] === 'ocupante') aria-sort="ascending" @endif>Ocupado por</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="tabla__acciones"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pagina as $puesto)
                        @php
                            $id = $puesto->id;
                            $nombre = $puesto->cargo.' · '.$puesto->ambito;
                        @endphp
                        <tr @class(['tabla__fila--inactiva' => ! $puesto->activo])>
                            <th scope="row" data-etiqueta="Puesto">
                                <a href="{{ route('puestos.show', $id) }}" class="tabla__principal tabla__enlace">{{ $puesto->cargo }}</a>
                                {{-- Debajo, solo lo que no va en otra columna: la zona o la institución --}}
                                @if ($puesto->zona_numero || $puesto->institucion || $puesto->organizacion)
                                    <span class="tabla__detalle">
                                        {{ $puesto->institucion ?? $puesto->organizacion ?? "Zona {$puesto->zona_numero} · {$puesto->zona_nombre}" }}
                                        @if ($puesto->titular)
                                            <span class="insignia-titular">Titular</span>
                                        @endif
                                    </span>
                                @endif
                            </th>
                            <td data-etiqueta="Coordinación">
                                @if ($puesto->coordinacion)
                                    <span class="etiqueta-coordinacion">{{ $puesto->coordinacion }}</span>
                                @else
                                    <span class="tabla__vacio">—</span>
                                @endif
                            </td>
                            <td data-etiqueta="Nivel"><span class="nivel-cargo nivel-cargo--{{ strtolower($puesto->nivel) }}">{{ $puesto->nivel }}</span></td>
                            <td data-etiqueta="Ocupado por">
                                @if ($puesto->ocupante)
                                    {{ $puesto->ocupante }}
                                @else
                                    <span class="tabla__vacio">Vacante</span>
                                @endif
                            </td>
                            <td data-etiqueta="Estado">
                                <span @class(['estado', 'estado--activo' => $puesto->activo])>{{ $puesto->activo ? 'Activo' : 'Desactivado' }}</span>
                            </td>
                            <td class="tabla__acciones">
                                <div class="acciones-fila">
                                    <a href="{{ route('puestos.show', $id) }}" class="accion accion--consultar">
                                        <x-icono nombre="ojo" />
                                        <span class="accion__texto">Consultar <span class="solo-lectores">{{ $nombre }}</span></span>
                                    </a>

                                    <button type="button" class="accion accion--editar"
                                            data-abrir-dialogo="dialogo-puesto"
                                            data-accion="{{ route('puestos.update', $id) }}" data-metodo="PUT"
                                            data-titulo="Editar puesto" data-boton="Guardar cambios"
                                            data-ambito-fijo="{{ $puesto->designaciones > 0 ? 'Ya tiene designaciones, así que el cargo y el ámbito no cambian. Si el puesto cambió, desactívalo y registra otro.' : '' }}"
                                            data-valores="{{ json_encode(['cargo' => $puesto->cargo_id, 'zona' => $puesto->zona_id ?? '', 'coordinacion' => $puesto->coordinacion_id ?? '', 'institucion' => $puesto->institucion_id ?? '', 'organizacion' => $puesto->organizacion_id ?? '', 'titular' => (bool) $puesto->titular, '_id' => $id]) }}">
                                        <x-icono nombre="lapiz" />
                                        <span class="accion__texto">Editar <span class="solo-lectores">{{ $nombre }}</span></span>
                                    </button>

                                    @if ($puesto->activo)
                                        <button type="button" class="accion accion--desactivar"
                                                data-abrir-dialogo="dialogo-desactivar"
                                                data-accion="{{ route('puestos.estado', $id) }}"
                                                data-titulo="¿Desactivar el puesto {{ $nombre }}?">
                                            <x-icono nombre="pausa" />
                                            <span class="accion__texto">Desactivar <span class="solo-lectores">{{ $nombre }}</span></span>
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('puestos.estado', $id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="activa" value="1">
                                            <button type="submit" class="accion accion--activar">
                                                <x-icono nombre="restaurar" />
                                                <span class="accion__texto">Activar <span class="solo-lectores">{{ $nombre }}</span></span>
                                            </button>
                                        </form>
                                    @endif

                                    <button type="button" @class(['accion', 'accion--borrar', 'accion--bloqueada' => $puesto->motivo_para_no_borrar])
                                            data-abrir-dialogo="dialogo-borrar"
                                            data-accion="{{ route('puestos.destroy', $id) }}"
                                            data-titulo="¿Borrar el puesto {{ $nombre }}?"
                                            data-bloqueo="{{ $puesto->motivo_para_no_borrar }}"
                                            data-valores="{{ json_encode(['_id' => $id, 'motivo' => '']) }}">
                                        <x-icono nombre="papelera" />
                                        <span class="accion__texto">Borrar <span class="solo-lectores">{{ $nombre }}</span></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <x-paginacion :paginas="$pagina" />
            @endif
        @endif
    </section>

    @include('puestos._dialogo-puesto')
    @include('catalogos._dialogo-desactivar', ['texto' => 'No se borra: conserva su historia (quiénes lo ocuparon) y lo puedes volver a activar. Mientras esté desactivado no recibe designaciones nuevas.'])
    @include('catalogos._dialogo-borrar', [
        'motivos' => $motivos,
        'abrir' => $borrarConError !== null,
        'accion' => $borrarConError ? route('puestos.destroy', $borrarConError->id) : '',
        'titulo' => $borrarConError ? "¿Borrar el puesto {$borrarConError->cargo} · {$borrarConError->ambito}?" : 'Borrar puesto',
    ])
@endsection

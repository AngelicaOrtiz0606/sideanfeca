{{--
    Catálogo de cargos. Todo se hace desde aquí: "Nuevo cargo" y "Editar"
    abren una ventana; "Desactivar" pide confirmación y "Activar" es un clic;
    "Borrar" pide el motivo o explica por qué no se puede.
    Arriba de la tabla: filtro rápido por nombre y por nivel.
--}}
@extends('layouts.admin')

@section('titulo', 'Cargos')

@php
    $borrarConError = $errors->has('motivo') ? $cargos->firstWhere('carg_id_cargo', (int) old('_id')) : null;
@endphp

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Cargos</h1>
            <p class="pagina__texto">Los cargos que se asignan a los puestos de los directorios, en masculino y en femenino.</p>
        </div>

        <div class="pagina__acciones">
            <button type="button" class="boton boton--primario"
                    data-abrir-dialogo="dialogo-cargo"
                    data-accion="{{ route('cargos.store') }}" data-metodo="POST" data-puestos="0"
                    data-titulo="Nuevo cargo" data-boton="Registrar cargo"
                    data-valores="{{ json_encode(['masculino' => '', 'femenino' => '', 'nivel' => '', '_id' => '']) }}">
                <x-icono nombre="mas" />
                Nuevo cargo
            </button>
        </div>
    </header>

    <x-aviso-estado />

    <section class="tarjeta tarjeta--tabla" aria-label="Lista de cargos" data-filtro-tabla>
        @if ($cargos->isEmpty())
            <p class="vacio vacio--en-tabla">Todavía no hay cargos. Registra el primero con <strong>Nuevo cargo</strong>.</p>
        @else
            <div class="filtros">
                <label for="buscar-cargo" class="solo-lectores">Buscar cargo</label>
                <input id="buscar-cargo" type="search" class="campo__entrada filtros__buscar" placeholder="Buscar cargo" data-filtro-texto>

                <div class="filtros__grupo" role="group" aria-label="Filtrar por nivel">
                    <button type="button" class="filtro" aria-pressed="true" data-filtro-valor="">Todos <span class="filtro__cifra">{{ $cargos->count() }}</span></button>
                    @foreach ($niveles as $nivel)
                        <button type="button" class="filtro" aria-pressed="false" data-filtro-valor="{{ $nivel }}">
                            {{ $nivel }} <span class="filtro__cifra">{{ $cargos->where('carg_nivel', $nivel)->count() }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Masculino</th>
                        <th scope="col">Femenino</th>
                        <th scope="col">Nivel</th>
                        <th scope="col" class="tabla__cifra">Puestos</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="tabla__acciones"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cargos as $cargo)
                        @php
                            $id = $cargo->carg_id_cargo;
                        @endphp
                        <tr @class(['tabla__fila--inactiva' => ! $cargo->carg_activo])
                            data-filtro-fila data-grupo="{{ $cargo->carg_nivel }}"
                            data-texto="{{ $cargo->carg_nombre_m }} {{ $cargo->carg_nombre_f }}">
                            <th scope="row" data-etiqueta="Masculino"><span class="tabla__principal">{{ $cargo->carg_nombre_m }}</span></th>
                            <td data-etiqueta="Femenino">{{ $cargo->carg_nombre_f }}</td>
                            <td data-etiqueta="Nivel"><span class="nivel-cargo nivel-cargo--{{ strtolower($cargo->carg_nivel) }}">{{ $cargo->carg_nivel }}</span></td>
                            <td class="tabla__cifra" data-etiqueta="Puestos">{{ $cargo->puestos }}</td>
                            <td data-etiqueta="Estado">
                                <span @class(['estado', 'estado--activo' => $cargo->carg_activo])>{{ $cargo->carg_activo ? 'Activo' : 'Desactivado' }}</span>
                            </td>
                            <td class="tabla__acciones">
                                <div class="acciones-fila">
                                    <button type="button" class="accion accion--editar"
                                            data-abrir-dialogo="dialogo-cargo"
                                            data-accion="{{ route('cargos.update', $id) }}" data-metodo="PUT" data-puestos="{{ $cargo->puestos }}"
                                            data-titulo="Editar cargo" data-boton="Guardar cambios"
                                            data-valores="{{ json_encode(['masculino' => $cargo->carg_nombre_m, 'femenino' => $cargo->carg_nombre_f, 'nivel' => $cargo->carg_nivel, '_id' => $id]) }}">
                                        <x-icono nombre="lapiz" />
                                        <span class="accion__texto">Editar <span class="solo-lectores">{{ $cargo->carg_nombre_m }}</span></span>
                                    </button>

                                    @if ($cargo->carg_activo)
                                        <button type="button" class="accion accion--desactivar"
                                                data-abrir-dialogo="dialogo-desactivar"
                                                data-accion="{{ route('cargos.estado', $id) }}"
                                                data-titulo="¿Desactivar el cargo {{ $cargo->carg_nombre_m }}?">
                                            <x-icono nombre="pausa" />
                                            <span class="accion__texto">Desactivar <span class="solo-lectores">{{ $cargo->carg_nombre_m }}</span></span>
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('cargos.estado', $id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="activa" value="1">
                                            <button type="submit" class="accion accion--activar">
                                                <x-icono nombre="restaurar" />
                                                <span class="accion__texto">Activar <span class="solo-lectores">{{ $cargo->carg_nombre_m }}</span></span>
                                            </button>
                                        </form>
                                    @endif

                                    <button type="button" @class(['accion', 'accion--borrar', 'accion--bloqueada' => $cargo->motivo_para_no_borrar])
                                            data-abrir-dialogo="dialogo-borrar"
                                            data-accion="{{ route('cargos.destroy', $id) }}"
                                            data-titulo="¿Borrar el cargo {{ $cargo->carg_nombre_m }}?"
                                            data-bloqueo="{{ $cargo->motivo_para_no_borrar }}"
                                            data-valores="{{ json_encode(['_id' => $id, 'motivo' => '']) }}">
                                        <x-icono nombre="papelera" />
                                        <span class="accion__texto">Borrar <span class="solo-lectores">{{ $cargo->carg_nombre_m }}</span></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="vacio vacio--en-tabla" data-filtro-vacio hidden>Ningún cargo coincide con la búsqueda.</p>
        @endif
    </section>

    @include('catalogos.cargos._dialogo-cargo')
    @include('catalogos._dialogo-desactivar', ['texto' => 'No se borra: sus puestos y su historial se conservan, y lo puedes volver a activar cuando quieras.'])
    @include('catalogos._dialogo-borrar', [
        'motivos' => $motivos,
        'abrir' => $borrarConError !== null,
        'accion' => $borrarConError ? route('cargos.destroy', $borrarConError->carg_id_cargo) : '',
        'titulo' => $borrarConError ? "¿Borrar el cargo {$borrarConError->carg_nombre_m}?" : 'Borrar cargo',
    ])
@endsection

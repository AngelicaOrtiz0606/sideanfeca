{{--
    Catálogo de grados académicos. Todo se hace desde aquí: "Nuevo grado" y
    "Editar" abren una ventana; "Desactivar" pide confirmación y "Activar" es
    un clic; "Borrar" pide el motivo o explica por qué no se puede.
--}}
@extends('layouts.admin')

@section('titulo', 'Grados académicos')

@php
    $borrarConError = $errors->has('motivo') ? $grados->firstWhere('niac_id_nivel_academico', (int) old('_id')) : null;
@endphp

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Grados académicos</h1>
            <p class="pagina__texto">Los grados que se asignan a las personas, con la abreviatura que va antes de su nombre.</p>
        </div>

        <div class="pagina__acciones">
            <button type="button" class="boton boton--primario"
                    data-abrir-dialogo="dialogo-grado"
                    data-accion="{{ route('grados.store') }}" data-metodo="POST"
                    data-titulo="Nuevo grado académico" data-boton="Registrar grado"
                    data-valores="{{ json_encode(['nombre' => '', 'masculina' => '', 'femenina' => '', '_id' => '']) }}">
                <x-icono nombre="mas" />
                Nuevo grado
            </button>
        </div>
    </header>

    <x-aviso-estado />

    <section class="tarjeta tarjeta--tabla" aria-label="Lista de grados académicos">
        @if ($grados->isEmpty())
            <p class="vacio vacio--en-tabla">Todavía no hay grados académicos. Registra el primero con <strong>Nuevo grado</strong>.</p>
        @else
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Grado</th>
                        <th scope="col">Abreviatura</th>
                        <th scope="col" class="tabla__cifra">Personas</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="tabla__acciones"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grados as $grado)
                        @php($id = $grado->niac_id_nivel_academico)
                        <tr @class(['tabla__fila--inactiva' => ! $grado->niac_activo])>
                            <th scope="row" data-etiqueta="Grado"><span class="tabla__principal">{{ $grado->niac_nombre }}</span></th>
                            <td data-etiqueta="Abreviatura">{{ $grado->niac_abr_m }} <span class="tabla__separador">/</span> {{ $grado->niac_abr_f }}</td>
                            <td class="tabla__cifra" data-etiqueta="Personas">{{ $grado->personas }}</td>
                            <td data-etiqueta="Estado">
                                <span @class(['estado', 'estado--activo' => $grado->niac_activo])>{{ $grado->niac_activo ? 'Activo' : 'Desactivado' }}</span>
                            </td>
                            <td class="tabla__acciones">
                                <div class="acciones-fila">
                                    <button type="button" class="accion accion--editar"
                                            data-abrir-dialogo="dialogo-grado"
                                            data-accion="{{ route('grados.update', $id) }}" data-metodo="PUT"
                                            data-titulo="Editar grado académico" data-boton="Guardar cambios"
                                            data-valores="{{ json_encode(['nombre' => $grado->niac_nombre, 'masculina' => $grado->niac_abr_m, 'femenina' => $grado->niac_abr_f, '_id' => $id]) }}">
                                        <x-icono nombre="lapiz" />
                                        <span class="accion__texto">Editar <span class="solo-lectores">{{ $grado->niac_nombre }}</span></span>
                                    </button>

                                    @if ($grado->niac_activo)
                                        <button type="button" class="accion accion--desactivar"
                                                data-abrir-dialogo="dialogo-desactivar"
                                                data-accion="{{ route('grados.estado', $id) }}"
                                                data-titulo="¿Desactivar el grado {{ $grado->niac_nombre }}?">
                                            <x-icono nombre="pausa" />
                                            <span class="accion__texto">Desactivar <span class="solo-lectores">{{ $grado->niac_nombre }}</span></span>
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('grados.estado', $id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="activa" value="1">
                                            <button type="submit" class="accion accion--activar">
                                                <x-icono nombre="restaurar" />
                                                <span class="accion__texto">Activar <span class="solo-lectores">{{ $grado->niac_nombre }}</span></span>
                                            </button>
                                        </form>
                                    @endif

                                    <button type="button" @class(['accion', 'accion--borrar', 'accion--bloqueada' => $grado->motivo_para_no_borrar])
                                            data-abrir-dialogo="dialogo-borrar"
                                            data-accion="{{ route('grados.destroy', $id) }}"
                                            data-titulo="¿Borrar el grado {{ $grado->niac_nombre }}?"
                                            data-bloqueo="{{ $grado->motivo_para_no_borrar }}"
                                            data-valores="{{ json_encode(['_id' => $id, 'motivo' => '']) }}">
                                        <x-icono nombre="papelera" />
                                        <span class="accion__texto">Borrar <span class="solo-lectores">{{ $grado->niac_nombre }}</span></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @include('catalogos.grados._dialogo-grado')
    @include('catalogos._dialogo-desactivar', ['texto' => 'No se borra: las personas que ya lo tienen lo conservan, y lo puedes volver a activar cuando quieras.'])
    @include('catalogos._dialogo-borrar', [
        'motivos' => $motivos,
        'abrir' => $borrarConError !== null,
        'accion' => $borrarConError ? route('grados.destroy', $borrarConError->niac_id_nivel_academico) : '',
        'titulo' => $borrarConError ? "¿Borrar el grado {$borrarConError->niac_nombre}?" : 'Borrar grado',
    ])
@endsection

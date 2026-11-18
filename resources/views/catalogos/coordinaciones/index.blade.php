{{--
    Catálogo de coordinaciones nacionales, en su orden. Todo se hace desde aquí:
    "Nueva coordinación", "Editar" y "Cambiar el orden" abren una ventana; "Desactivar" pide
    confirmación y "Activar" es un clic; "Borrar" pide el motivo o explica por
    qué no se puede. El nombre lleva a la consulta (sus puestos).
--}}
@extends('layouts.admin')

@section('titulo', 'Coordinaciones nacionales')

@php
    $borrarConError = $errors->has('motivo') ? $coordinaciones->firstWhere('cona_id_coordinacion_nacional', (int) old('_id')) : null;
@endphp

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Coordinaciones nacionales</h1>
            <p class="pagina__texto">Se muestran en el orden en que aparecen en los directorios.</p>
        </div>

        <div class="pagina__acciones">
            <button type="button" class="boton boton--discreto" data-abrir-dialogo="dialogo-orden">
                <x-icono nombre="ordenar" />
                Cambiar el orden
            </button>
            <button type="button" class="boton boton--primario"
                    data-abrir-dialogo="dialogo-coordinacion"
                    data-accion="{{ route('coordinaciones.store') }}" data-metodo="POST"
                    data-titulo="Nueva coordinación" data-texto="Quedará al final del orden." data-boton="Registrar coordinación"
                    data-valores="{{ json_encode(['nombre' => '', 'descripcion' => '', '_id' => '']) }}">
                <x-icono nombre="mas" />
                Nueva coordinación
            </button>
        </div>
    </header>

    <x-aviso-estado />

    {{-- Errores al guardar el orden (lista alterada) --}}
    @if ($errors->has('orden') || $errors->has('orden.*'))
        <x-errores-formulario />
    @endif

    <section class="tarjeta tarjeta--tabla" aria-label="Lista de coordinaciones nacionales">
        @if ($coordinaciones->isEmpty())
            <p class="vacio">Todavía no hay coordinaciones registradas.</p>
        @else
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col" class="tabla__numero">Orden</th>
                        <th scope="col">Coordinación</th>
                        <th scope="col" class="tabla__cifra">Puestos</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="tabla__acciones"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($coordinaciones as $coordinacion)
                        @php
                            $id = $coordinacion->cona_id_coordinacion_nacional;
                        @endphp
                        <tr @class(['tabla__fila--inactiva' => ! $coordinacion->cona_activo])>
                            <td class="tabla__numero" data-etiqueta="Orden">
                                <span class="numero-orden">{{ $coordinacion->cona_orden }}</span>
                            </td>
                            <th scope="row" data-etiqueta="Coordinación">
                                <a href="{{ route('coordinaciones.show', $id) }}" class="tabla__enlace">{{ $coordinacion->cona_nombre }}</a>
                                @if ($coordinacion->cona_descripcion)
                                    <span class="tabla__detalle">{{ $coordinacion->cona_descripcion }}</span>
                                @endif
                            </th>
                            <td class="tabla__cifra" data-etiqueta="Puestos">{{ $coordinacion->puestos }}</td>
                            <td data-etiqueta="Estado">
                                <span @class(['estado', 'estado--activo' => $coordinacion->cona_activo])>{{ $coordinacion->cona_activo ? 'Activa' : 'Desactivada' }}</span>
                            </td>
                            <td class="tabla__acciones">
                                <div class="acciones-fila">
                                    <button type="button" class="accion accion--editar"
                                            data-abrir-dialogo="dialogo-coordinacion"
                                            data-accion="{{ route('coordinaciones.update', $id) }}" data-metodo="PUT"
                                            data-titulo="Editar coordinación" data-texto="" data-boton="Guardar cambios"
                                            data-valores="{{ json_encode(['nombre' => $coordinacion->cona_nombre, 'descripcion' => $coordinacion->cona_descripcion, '_id' => $id]) }}">
                                        <x-icono nombre="lapiz" />
                                        <span class="accion__texto">Editar <span class="solo-lectores">{{ $coordinacion->cona_nombre }}</span></span>
                                    </button>

                                    @if ($coordinacion->cona_activo)
                                        <button type="button" class="accion accion--desactivar"
                                                data-abrir-dialogo="dialogo-desactivar"
                                                data-accion="{{ route('coordinaciones.estado', $id) }}"
                                                data-titulo="¿Desactivar {{ $coordinacion->cona_nombre }}?">
                                            <x-icono nombre="pausa" />
                                            <span class="accion__texto">Desactivar <span class="solo-lectores">{{ $coordinacion->cona_nombre }}</span></span>
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('coordinaciones.estado', $id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="activa" value="1">
                                            <button type="submit" class="accion accion--activar">
                                                <x-icono nombre="restaurar" />
                                                <span class="accion__texto">Activar <span class="solo-lectores">{{ $coordinacion->cona_nombre }}</span></span>
                                            </button>
                                        </form>
                                    @endif

                                    <button type="button" @class(['accion', 'accion--borrar', 'accion--bloqueada' => $coordinacion->motivo_para_no_borrar])
                                            data-abrir-dialogo="dialogo-borrar"
                                            data-accion="{{ route('coordinaciones.destroy', $id) }}"
                                            data-titulo="¿Borrar {{ $coordinacion->cona_nombre }}?"
                                            data-bloqueo="{{ $coordinacion->motivo_para_no_borrar }}"
                                            data-valores="{{ json_encode(['_id' => $id, 'motivo' => '']) }}">
                                        <x-icono nombre="papelera" />
                                        <span class="accion__texto">Borrar <span class="solo-lectores">{{ $coordinacion->cona_nombre }}</span></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @include('catalogos.coordinaciones._dialogo-coordinacion')
    @include('catalogos.coordinaciones._dialogo-orden')
    @include('catalogos._dialogo-desactivar', ['texto' => 'No se borra: sus puestos y su historial se conservan, y la puedes volver a activar cuando quieras.'])

    @include('catalogos._dialogo-borrar', [
        'motivos' => $motivos,
        'abrir' => $borrarConError !== null,
        'accion' => $borrarConError ? route('coordinaciones.destroy', $borrarConError->cona_id_coordinacion_nacional) : '',
        'titulo' => $borrarConError ? "¿Borrar {$borrarConError->cona_nombre}?" : 'Borrar coordinación',
    ])
@endsection

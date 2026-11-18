{{--
    Organizaciones (por ejemplo, CACECA): no son instituciones educativas, no
    tienen número de afiliación ni zona; solo nombre, domicilio y sitio web.
    Todo se hace desde aquí: "Nueva organización" y "Editar" abren una ventana;
    "Desactivar" pide confirmación y "Activar" es un clic; "Borrar" pide el
    motivo o explica por qué no se puede (si ya tiene personas).
--}}
@extends('layouts.admin')

@section('titulo', 'Organizaciones')

@php
    $borrarConError = $errors->has('motivo') ? $organizaciones->firstWhere('id', (int) old('_id')) : null;
    $vacia = ['nombre' => '', 'sitio_web' => '', 'codigo_postal' => '', 'colonia' => '', 'calle' => '', 'num_ext' => '', 'num_int' => '', '_id' => ''];
@endphp

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Organizaciones</h1>
            <p class="pagina__texto">Organismos que no son instituciones educativas, como CACECA.</p>
        </div>

        <div class="pagina__acciones">
            <button type="button" class="boton boton--primario"
                    data-abrir-dialogo="dialogo-organizacion"
                    data-accion="{{ route('organizaciones.store') }}" data-metodo="POST"
                    data-titulo="Nueva organización" data-boton="Registrar organización"
                    data-valores="{{ json_encode($vacia) }}">
                <x-icono nombre="mas" />
                Nueva organización
            </button>
        </div>
    </header>

    <x-aviso-estado />

    <section class="tarjeta tarjeta--tabla" aria-label="Lista de organizaciones" data-filtro-tabla>
        @if ($organizaciones->isEmpty())
            <p class="vacio vacio--en-tabla">Todavía no hay organizaciones. Registra la primera con <strong>Nueva organización</strong>.</p>
        @else
            <div class="filtros">
                <label for="buscar-organizacion" class="solo-lectores">Buscar organización</label>
                <input id="buscar-organizacion" type="search" class="campo__entrada filtros__buscar" placeholder="Buscar por nombre o entidad" data-filtro-texto>
            </div>

            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Organización</th>
                        <th scope="col">Ubicación</th>
                        <th scope="col" class="tabla__cifra">Personas</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="tabla__acciones"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($organizaciones as $organizacion)
                        @php
                            $id = $organizacion->id;
                        @endphp
                        <tr @class(['tabla__fila--inactiva' => ! $organizacion->activa])
                            data-filtro-fila data-texto="{{ $organizacion->nombre }} {{ $organizacion->municipio }} {{ $organizacion->entidad }}">
                            <th scope="row" data-etiqueta="Organización">
                                <span class="tabla__principal">{{ $organizacion->nombre }}</span>
                                @if ($organizacion->sitio_web)
                                    <a href="{{ $organizacion->sitio_web }}" class="tabla__detalle tabla__enlace-web" target="_blank" rel="noopener noreferrer">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($organizacion->sitio_web, '/')) }}</a>
                                @endif
                            </th>
                            <td data-etiqueta="Ubicación">
                                {{ $organizacion->municipio }}, {{ $organizacion->entidad }}
                            </td>
                            <td class="tabla__cifra" data-etiqueta="Personas">{{ $organizacion->personas }}</td>
                            <td data-etiqueta="Estado">
                                <span @class(['estado', 'estado--activo' => $organizacion->activa])>{{ $organizacion->activa ? 'Activa' : 'Desactivada' }}</span>
                            </td>
                            <td class="tabla__acciones">
                                <div class="acciones-fila">
                                    <button type="button" class="accion accion--editar"
                                            data-abrir-dialogo="dialogo-organizacion"
                                            data-accion="{{ route('organizaciones.update', $id) }}" data-metodo="PUT"
                                            data-titulo="Editar organización" data-boton="Guardar cambios"
                                            data-valores="{{ json_encode([
                                                'nombre' => $organizacion->nombre, 'sitio_web' => $organizacion->sitio_web ?? '',
                                                'codigo_postal' => $organizacion->codigo_postal, 'colonia' => $organizacion->colonia,
                                                'calle' => $organizacion->calle, 'num_ext' => $organizacion->num_ext, 'num_int' => $organizacion->num_int ?? '', '_id' => $id,
                                            ]) }}">
                                        <x-icono nombre="lapiz" />
                                        <span class="accion__texto">Editar <span class="solo-lectores">{{ $organizacion->nombre }}</span></span>
                                    </button>

                                    @if ($organizacion->activa)
                                        <button type="button" class="accion accion--desactivar"
                                                data-abrir-dialogo="dialogo-desactivar"
                                                data-accion="{{ route('organizaciones.estado', $id) }}"
                                                data-titulo="¿Desactivar {{ $organizacion->nombre }}?">
                                            <x-icono nombre="pausa" />
                                            <span class="accion__texto">Desactivar <span class="solo-lectores">{{ $organizacion->nombre }}</span></span>
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('organizaciones.estado', $id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="activa" value="1">
                                            <button type="submit" class="accion accion--activar">
                                                <x-icono nombre="restaurar" />
                                                <span class="accion__texto">Activar <span class="solo-lectores">{{ $organizacion->nombre }}</span></span>
                                            </button>
                                        </form>
                                    @endif

                                    <button type="button" @class(['accion', 'accion--borrar', 'accion--bloqueada' => $organizacion->motivo_para_no_borrar])
                                            data-abrir-dialogo="dialogo-borrar"
                                            data-accion="{{ route('organizaciones.destroy', $id) }}"
                                            data-titulo="¿Borrar {{ $organizacion->nombre }}?"
                                            data-bloqueo="{{ $organizacion->motivo_para_no_borrar }}"
                                            data-valores="{{ json_encode(['_id' => $id, 'motivo' => '']) }}">
                                        <x-icono nombre="papelera" />
                                        <span class="accion__texto">Borrar <span class="solo-lectores">{{ $organizacion->nombre }}</span></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="vacio vacio--en-tabla" data-filtro-vacio hidden>Ninguna organización coincide con la búsqueda.</p>
        @endif
    </section>

    @include('organizaciones._dialogo-organizacion')
    @include('catalogos._dialogo-desactivar', ['texto' => 'No se borra: las personas que pertenecen a ella la conservan, y la puedes volver a activar cuando quieras.'])
    @include('catalogos._dialogo-borrar', [
        'motivos' => $motivos,
        'abrir' => $borrarConError !== null,
        'accion' => $borrarConError ? route('organizaciones.destroy', $borrarConError->id) : '',
        'titulo' => $borrarConError ? "¿Borrar {$borrarConError->nombre}?" : 'Borrar organización',
    ])
@endsection

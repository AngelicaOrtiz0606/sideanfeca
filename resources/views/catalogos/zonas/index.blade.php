{{--
    Catálogo de zonas regionales. Todo se hace desde aquí:
    "Nueva zona" y "Editar" abren una ventana; "Borrar" pide el motivo o
    explica por qué no se puede. El nombre lleva a la consulta de la zona.
--}}
@extends('layouts.admin')

@section('titulo', 'Zonas regionales')

@php
    $borrarConError = $errors->has('motivo') ? $zonas->firstWhere('zore_id_zona_regional', (int) old('_id')) : null;
@endphp

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Zonas regionales</h1>
            <p class="pagina__texto">Las zonas de ANFECA y las entidades federativas de cada una.</p>
        </div>

        @if ($numerosLibres !== [])
            <div class="pagina__acciones">
                <button type="button" class="boton boton--primario"
                        data-abrir-dialogo="dialogo-zona"
                        data-accion="{{ route('zonas.store') }}" data-metodo="POST" data-zona=""
                        data-titulo="Nueva zona" data-boton="Registrar zona"
                        data-valores="{{ json_encode(['numero' => $numerosLibres[0], 'nombre' => '', '_id' => '']) }}">
                    <x-icono nombre="mas" />
                    Nueva zona
                </button>
            </div>
        @endif
    </header>

    <x-aviso-estado />

    <section class="tarjeta tarjeta--tabla" aria-label="Lista de zonas regionales">
        @if ($zonas->isEmpty())
            <p class="vacio">Todavía no hay zonas registradas.</p>
        @else
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col" class="tabla__numero">Número</th>
                        <th scope="col">Nombre</th>
                        <th scope="col" class="tabla__cifra">Entidades</th>
                        <th scope="col" class="tabla__cifra">Instituciones</th>
                        <th scope="col" class="tabla__acciones"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($zonas as $zona)
                        @php
                            $etiqueta = "Zona {$zona->zore_numero} · {$zona->zore_nombre}";
                        @endphp
                        <tr>
                            <td class="tabla__numero" data-etiqueta="Número">
                                <span class="numero-zona">{{ $zona->zore_numero }}</span>
                            </td>
                            <th scope="row" data-etiqueta="Nombre">
                                <a href="{{ route('zonas.show', $zona->zore_id_zona_regional) }}" class="tabla__enlace">{{ $zona->zore_nombre }}</a>
                            </th>
                            <td class="tabla__cifra" data-etiqueta="Entidades">{{ $zona->entidades }}</td>
                            <td class="tabla__cifra" data-etiqueta="Instituciones">{{ $zona->instituciones }}</td>
                            <td class="tabla__acciones">
                                <div class="acciones-fila">
                                    <button type="button" class="accion accion--editar"
                                            data-abrir-dialogo="dialogo-zona"
                                            data-accion="{{ route('zonas.update', $zona->zore_id_zona_regional) }}" data-metodo="PUT"
                                            data-zona="{{ $zona->zore_id_zona_regional }}"
                                            data-titulo="Editar zona" data-boton="Guardar cambios"
                                            data-valores="{{ json_encode(['numero' => $zona->zore_numero, 'nombre' => $zona->zore_nombre, '_id' => $zona->zore_id_zona_regional]) }}">
                                        <x-icono nombre="lapiz" />
                                        <span class="accion__texto">Editar <span class="solo-lectores">{{ $etiqueta }}</span></span>
                                    </button>
                                    <button type="button" @class(['accion', 'accion--borrar', 'accion--bloqueada' => $zona->motivo_para_no_borrar])
                                            data-abrir-dialogo="dialogo-borrar"
                                            data-accion="{{ route('zonas.destroy', $zona->zore_id_zona_regional) }}"
                                            data-titulo="¿Borrar la {{ $etiqueta }}?"
                                            data-bloqueo="{{ $zona->motivo_para_no_borrar }}"
                                            data-valores="{{ json_encode(['_id' => $zona->zore_id_zona_regional, 'motivo' => '']) }}">
                                        <x-icono nombre="papelera" />
                                        <span class="accion__texto">Borrar <span class="solo-lectores">{{ $etiqueta }}</span></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @include('catalogos.zonas._dialogo-zona')

    @include('catalogos._dialogo-borrar', [
        'motivos' => $motivos,
        'abrir' => $borrarConError !== null,
        'accion' => $borrarConError ? route('zonas.destroy', $borrarConError->zore_id_zona_regional) : '',
        'titulo' => $borrarConError ? "¿Borrar la Zona {$borrarConError->zore_numero} · {$borrarConError->zore_nombre}?" : 'Borrar zona',
    ])
@endsection

{{-- Catálogo de coordinaciones nacionales, en su orden --}}
@extends('layouts.admin')

@section('titulo', 'Coordinaciones nacionales')

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Coordinaciones nacionales</h1>
            <p class="pagina__texto">Se muestran en el orden en que aparecen en los directorios.</p>
        </div>

        <div class="pagina__acciones">
            <a href="{{ route('coordinaciones.orden') }}" class="boton boton--discreto">
                <x-icono nombre="ordenar" />
                Cambiar el orden
            </a>
            <a href="{{ route('coordinaciones.create') }}" class="boton boton--primario">
                <x-icono nombre="mas" />
                Nueva coordinación
            </a>
        </div>
    </header>

    <x-aviso-estado />

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
                        <tr @class(['tabla__fila--inactiva' => ! $coordinacion->cona_activo])>
                            <td class="tabla__numero" data-etiqueta="Orden">
                                <span class="numero-orden">{{ $coordinacion->cona_orden }}</span>
                            </td>
                            <th scope="row" data-etiqueta="Coordinación">
                                <a href="{{ route('coordinaciones.show', $coordinacion->cona_id_coordinacion_nacional) }}" class="tabla__enlace">{{ $coordinacion->cona_nombre }}</a>
                                @if ($coordinacion->cona_descripcion)
                                    <span class="tabla__detalle">{{ $coordinacion->cona_descripcion }}</span>
                                @endif
                            </th>
                            <td class="tabla__cifra" data-etiqueta="Puestos">{{ $coordinacion->puestos }}</td>
                            <td data-etiqueta="Estado">
                                <span @class(['estado', 'estado--activo' => $coordinacion->cona_activo])>{{ $coordinacion->cona_activo ? 'Activa' : 'Desactivada' }}</span>
                            </td>
                            <td class="tabla__acciones">
                                <a href="{{ route('coordinaciones.edit', $coordinacion->cona_id_coordinacion_nacional) }}" class="boton-accion">
                                    <x-icono nombre="lapiz" />
                                    Editar<span class="solo-lectores"> la coordinación {{ $coordinacion->cona_nombre }}</span>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection

{{-- Consulta de una coordinación nacional: sus puestos y quién los ocupa --}}
@extends('layouts.admin')

@section('titulo', $coordinacion->cona_nombre)

@section('contenido')
    <a href="{{ route('coordinaciones.index') }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        Coordinaciones nacionales
    </a>

    <header class="pagina__encabezado">
        <div>
            <p class="pagina__antetitulo">Coordinación nacional · Lugar {{ $coordinacion->cona_orden }}</p>
            <h1 class="pagina__titulo">{{ $coordinacion->cona_nombre }}</h1>
            <p class="pagina__detalles">
                <span @class(['estado', 'estado--activo' => $coordinacion->cona_activo])>{{ $coordinacion->cona_activo ? 'Activa' : 'Desactivada' }}</span>
                @if ($coordinacion->cona_descripcion)
                    <span>{{ $coordinacion->cona_descripcion }}</span>
                @endif
            </p>
        </div>

        <div class="pagina__acciones">
            <button type="button" class="boton boton--discreto"
                    data-abrir-dialogo="dialogo-coordinacion"
                    data-accion="{{ route('coordinaciones.update', $coordinacion) }}" data-metodo="PUT"
                    data-titulo="Editar coordinación" data-texto="" data-boton="Guardar cambios"
                    data-valores="{{ json_encode(['nombre' => $coordinacion->cona_nombre, 'descripcion' => $coordinacion->cona_descripcion, '_id' => $coordinacion->cona_id_coordinacion_nacional]) }}">
                <x-icono nombre="lapiz" />
                Editar coordinación
            </button>
        </div>
    </header>

    <x-aviso-estado />

    <section class="tarjeta" aria-labelledby="puestos-titulo">
        <div class="tarjeta__encabezado">
            <h2 id="puestos-titulo" class="seccion__titulo">Puestos</h2>
            <span class="cifra">{{ $puestos->count() }}</span>
        </div>

        @if ($puestos->isEmpty())
            <p class="vacio">Todavía no hay puestos en esta coordinación.</p>
        @else
            <table class="tabla tabla--simple">
                <thead>
                    <tr>
                        <th scope="col">Cargo</th>
                        <th scope="col">Zona</th>
                        <th scope="col">Lo ocupa</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($puestos as $puesto)
                        <tr>
                            <th scope="row" data-etiqueta="Cargo">{{ $puesto->pers_genero === 'F' ? $puesto->carg_nombre_f : $puesto->carg_nombre_m }}</th>
                            <td data-etiqueta="Zona">{{ $puesto->zore_numero ? "Zona {$puesto->zore_numero} · {$puesto->zore_nombre}" : 'Nacional' }}</td>
                            <td data-etiqueta="Lo ocupa">{{ $puesto->desde ? $puesto->persona : 'Sin designar' }}</td>
                            <td data-etiqueta="Estado">
                                <span @class(['estado', 'estado--activo' => $puesto->activo])>{{ $puesto->activo ? 'Activo' : 'Desactivado' }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @include('catalogos.coordinaciones._dialogo-coordinacion')
@endsection

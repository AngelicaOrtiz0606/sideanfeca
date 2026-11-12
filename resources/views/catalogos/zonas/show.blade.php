{{-- Consulta de una zona regional: sus entidades federativas e instituciones --}}
@extends('layouts.admin')

@section('titulo', $zona->zore_nombre)

@section('contenido')
    <a href="{{ route('zonas.index') }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        Zonas regionales
    </a>

    <header class="pagina__encabezado">
        <div>
            <p class="pagina__antetitulo">Zona regional {{ $zona->zore_numero }}</p>
            <h1 class="pagina__titulo">{{ $zona->zore_nombre }}</h1>
        </div>

        <div class="pagina__acciones">
            <button type="button" class="boton boton--discreto"
                    data-abrir-dialogo="dialogo-zona"
                    data-accion="{{ route('zonas.update', $zona) }}" data-metodo="PUT"
                    data-zona="{{ $zona->zore_id_zona_regional }}"
                    data-titulo="Editar zona" data-boton="Guardar cambios"
                    data-valores="{{ json_encode(['numero' => $zona->zore_numero, 'nombre' => $zona->zore_nombre, '_id' => $zona->zore_id_zona_regional]) }}">
                <x-icono nombre="lapiz" />
                Editar zona
            </button>
        </div>
    </header>

    <x-aviso-estado />

    <section class="tarjeta" aria-labelledby="entidades-titulo">
        <div class="tarjeta__encabezado">
            <h2 id="entidades-titulo" class="seccion__titulo">Entidades federativas</h2>
            <span class="cifra">{{ $entidades->count() }}</span>
        </div>

        @if ($entidades->isEmpty())
            <p class="vacio">Esta zona todavía no tiene entidades federativas.</p>
        @else
            <ul class="fichas-entidad">
                @foreach ($entidades as $entidad)
                    <li class="fichas-entidad__item">{{ $entidad->enfe_nombre }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="tarjeta" aria-labelledby="instituciones-titulo">
        <div class="tarjeta__encabezado">
            <h2 id="instituciones-titulo" class="seccion__titulo">Instituciones</h2>
            <span class="cifra">{{ $instituciones->count() }}</span>
        </div>

        @if ($instituciones->isEmpty())
            <p class="vacio">Todavía no hay instituciones en esta zona.</p>
        @else
            <table class="tabla tabla--simple">
                <thead>
                    <tr>
                        <th scope="col">Institución</th>
                        <th scope="col">Participación</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($instituciones as $institucion)
                        <tr>
                            <th scope="row" data-etiqueta="Institución">{{ $institucion->nombre ?? 'Sin razón social' }}</th>
                            <td data-etiqueta="Participación">{{ $institucion->participacion }}</td>
                            <td data-etiqueta="Estado">
                                <span class="estado {{ $institucion->activa ? 'estado--activo' : '' }}">{{ $institucion->activa ? 'Activa' : 'Desactivada' }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @include('catalogos.zonas._dialogo-zona', ['zonas' => $todasLasZonas, 'entidades' => $todasLasEntidades])
@endsection

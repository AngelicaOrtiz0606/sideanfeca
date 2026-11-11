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
            <a href="{{ route('coordinaciones.edit', $coordinacion) }}" class="boton boton--discreto">
                <x-icono nombre="lapiz" />
                Editar coordinación
            </a>
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

    @if ($coordinacion->cona_activo)
        <section class="tarjeta tarjeta--accion" aria-labelledby="estado-titulo">
            <div>
                <h2 id="estado-titulo" class="seccion__titulo">Desactivar coordinación</h2>
                <p class="tarjeta__texto">No se borra: sus puestos y su historial se conservan, y se puede volver a activar.</p>
            </div>
            <button type="button" class="boton boton--secundario" data-abrir-dialogo="dialogo-estado">
                <x-icono nombre="pausa" />
                Desactivar
            </button>
        </section>

        <dialog id="dialogo-estado" class="dialogo" aria-labelledby="dialogo-estado-titulo">
            <form method="POST" action="{{ route('coordinaciones.estado', $coordinacion) }}" class="formulario">
                @csrf
                @method('PATCH')
                <input type="hidden" name="activa" value="0">

                <h2 id="dialogo-estado-titulo" class="dialogo__titulo">¿Desactivar {{ $coordinacion->cona_nombre }}?</h2>
                <p class="dialogo__texto">Sus puestos y su historial se conservan. Puedes volver a activarla cuando quieras.</p>

                <div class="dialogo__acciones">
                    <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
                    <button type="submit" class="boton boton--primario">Desactivar</button>
                </div>
            </form>
        </dialog>
    @else
        <section class="tarjeta tarjeta--accion" aria-labelledby="estado-titulo">
            <div>
                <h2 id="estado-titulo" class="seccion__titulo">Coordinación desactivada</h2>
                <p class="tarjeta__texto">Sus puestos y su historial se conservaron. Al activarla vuelve a quedar disponible.</p>
            </div>
            <form method="POST" action="{{ route('coordinaciones.estado', $coordinacion) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="activa" value="1">
                <button type="submit" class="boton boton--primario">
                    <x-icono nombre="restaurar" />
                    Activar
                </button>
            </form>
        </section>
    @endif

    @if ($motivoParaNoBorrar !== null)
        <p class="nota-borrado">{{ $motivoParaNoBorrar }}</p>
    @else
        <section class="tarjeta tarjeta--peligro" aria-labelledby="borrar-titulo">
            <div>
                <h2 id="borrar-titulo" class="seccion__titulo">Borrar coordinación</h2>
                <p class="tarjeta__texto">No tiene puestos. El borrado queda en la bitácora con su motivo, y las que siguen suben un lugar.</p>
            </div>
            <button type="button" class="boton boton--peligro" data-abrir-dialogo="dialogo-borrar">
                <x-icono nombre="papelera" />
                Borrar coordinación
            </button>
        </section>

        <dialog id="dialogo-borrar" class="dialogo" aria-labelledby="dialogo-borrar-titulo" @if ($errors->has('motivo')) data-abrir-al-cargar @endif>
            <form method="POST" action="{{ route('coordinaciones.destroy', $coordinacion) }}" class="formulario" novalidate data-validar>
                @csrf
                @method('DELETE')

                <h2 id="dialogo-borrar-titulo" class="dialogo__titulo">¿Borrar {{ $coordinacion->cona_nombre }}?</h2>
                <p class="dialogo__texto">Esta acción no se puede deshacer.</p>

                <div class="campo">
                    <label for="motivo" class="campo__etiqueta">Motivo</label>
                    <select id="motivo" name="motivo" class="campo__entrada" required @error('motivo') aria-invalid="true" @enderror>
                        <option value="">Elige un motivo</option>
                        @foreach ($motivos as $motivo)
                            <option value="{{ $motivo }}" @selected(old('motivo') === $motivo)>{{ $motivo }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="dialogo__acciones">
                    <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
                    <button type="submit" class="boton boton--peligro">Borrar</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection

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
            <a href="{{ route('zonas.edit', $zona) }}" class="boton boton--discreto">
                <x-icono nombre="lapiz" />
                Editar zona
            </a>
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

    @if ($motivoParaNoBorrar !== null)
        <p class="nota-borrado">{{ $motivoParaNoBorrar }}</p>
    @else
        <section class="tarjeta tarjeta--peligro" aria-labelledby="borrar-titulo">
            <div>
                <h2 id="borrar-titulo" class="seccion__titulo">Borrar zona</h2>
                <p class="tarjeta__texto">La zona no tiene entidades, instituciones ni puestos. El borrado queda en la bitácora con su motivo.</p>
            </div>
            <button type="button" class="boton boton--peligro" data-abrir-dialogo="dialogo-borrar">
                <x-icono nombre="papelera" />
                Borrar zona
            </button>
        </section>

        <dialog id="dialogo-borrar" class="dialogo" aria-labelledby="dialogo-borrar-titulo" @if ($errors->has('motivo')) data-abrir-al-cargar @endif>
            <form method="POST" action="{{ route('zonas.destroy', $zona) }}" class="formulario" novalidate data-validar>
                @csrf
                @method('DELETE')

                <h2 id="dialogo-borrar-titulo" class="dialogo__titulo">¿Borrar la {{ $zona->etiqueta() }}?</h2>
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

{{--
    Cambiar el orden de las coordinaciones: arrastrando con el ratón o con
    las flechas (teclado y teléfono). Se guarda con reordenar_coordinaciones.
    Sin JavaScript, la lista se ve en su orden actual y no se puede mover.
--}}
@extends('layouts.admin')

@section('titulo', 'Orden de las coordinaciones')

@section('contenido')
    <a href="{{ route('coordinaciones.index') }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        Coordinaciones nacionales
    </a>

    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Orden de las coordinaciones</h1>
            <p class="pagina__texto">Arrastra cada coordinación a su lugar o usa las flechas. Los cambios se aplican al presionar <strong>Guardar orden</strong>.</p>
        </div>
    </header>

    <x-aviso-estado />
    <x-errores-formulario />

    <form method="POST" action="{{ route('coordinaciones.guardar-orden') }}" class="tarjeta formulario formulario--angosto" data-ordenar>
        @csrf
        @method('PUT')

        <ol class="lista-orden" data-ordenar-lista>
            @foreach ($coordinaciones as $coordinacion)
                <li class="lista-orden__item" draggable="true" data-ordenar-item>
                    <input type="hidden" name="orden[]" value="{{ $coordinacion->cona_id_coordinacion_nacional }}">
                    <span class="lista-orden__asa" aria-hidden="true"><x-icono nombre="arrastrar" /></span>
                    <span class="lista-orden__lugar" data-ordenar-lugar>{{ $loop->iteration }}</span>
                    <span class="lista-orden__nombre">
                        <span data-ordenar-nombre>{{ $coordinacion->cona_nombre }}</span>
                        @unless ($coordinacion->cona_activo)
                            <span class="estado">Desactivada</span>
                        @endunless
                    </span>
                    <span class="lista-orden__botones">
                        <button type="button" class="boton-icono boton-icono--pequeno" data-ordenar-subir>
                            <x-icono nombre="arriba" />
                            <span class="solo-lectores">Subir {{ $coordinacion->cona_nombre }}</span>
                        </button>
                        <button type="button" class="boton-icono boton-icono--pequeno" data-ordenar-bajar>
                            <x-icono nombre="abajo" />
                            <span class="solo-lectores">Bajar {{ $coordinacion->cona_nombre }}</span>
                        </button>
                    </span>
                </li>
            @endforeach
        </ol>

        <p class="solo-lectores" aria-live="polite" data-ordenar-anuncio></p>

        <div class="formulario__acciones">
            <button type="submit" class="boton boton--primario" data-ordenar-guardar disabled>
                <x-icono nombre="correcto" />
                Guardar orden
            </button>
            <a href="{{ route('coordinaciones.index') }}" class="boton boton--secundario">Cancelar</a>
        </div>
    </form>
@endsection

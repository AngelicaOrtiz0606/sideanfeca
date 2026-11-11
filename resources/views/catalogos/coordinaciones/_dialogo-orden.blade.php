{{--
    Ventana para cambiar el orden de las coordinaciones: arrastrando con el
    ratón o con las flechas (teclado y teléfono). Se guarda con
    reordenar_coordinaciones. Si se cancela, la lista regresa al orden original
    (resources/js/modulos/ordenar-lista.js).
    Parámetro: $coordinaciones (en su orden actual).
--}}
<dialog id="dialogo-orden" class="dialogo dialogo--orden" aria-labelledby="dialogo-orden-titulo">
    <form method="POST" action="{{ route('coordinaciones.guardar-orden') }}" class="formulario" data-ordenar>
        @csrf
        @method('PUT')

        <div class="dialogo__encabezado">
            <h2 id="dialogo-orden-titulo" class="dialogo__titulo">Cambiar el orden</h2>
            <button type="button" class="boton-icono boton-icono--pequeno" data-cerrar-dialogo>
                <x-icono nombre="cerrar" />
                <span class="solo-lectores">Cerrar</span>
            </button>
        </div>
        <p class="dialogo__texto">Arrastra cada coordinación a su lugar o usa las flechas. Es el orden en que aparecen en los directorios.</p>

        <div class="dialogo__desplazable">
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
        </div>

        <p class="solo-lectores" aria-live="polite" data-ordenar-anuncio></p>

        <div class="dialogo__acciones">
            <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton boton--primario" data-ordenar-guardar disabled>
                <x-icono nombre="correcto" />
                Guardar orden
            </button>
        </div>
    </form>
</dialog>

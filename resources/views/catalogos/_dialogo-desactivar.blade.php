{{--
    Confirmación para desactivar un registro de catálogo (activar no la necesita).
    La abre el botón Desactivar de cada fila, que le da la dirección y el título.
    Parámetro: $texto (qué pasa al desactivar; siempre: no se borra nada).
--}}
<dialog id="dialogo-desactivar" class="dialogo" aria-labelledby="dialogo-desactivar-titulo">
    <form method="POST" action="" class="formulario">
        @csrf
        @method('PATCH')
        <input type="hidden" name="activa" value="0">

        <h2 id="dialogo-desactivar-titulo" class="dialogo__titulo" data-dialogo-titulo>¿Desactivar?</h2>
        <p class="dialogo__texto">{{ $texto }}</p>

        <div class="dialogo__acciones">
            <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="pausa" />
                Desactivar
            </button>
        </div>
    </form>
</dialog>

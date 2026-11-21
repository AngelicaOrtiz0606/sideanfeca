{{--
    Terminar una designación: la persona deja el puesto. Se pide el primer día
    que ya no lo ocupa (la base guarda el anterior como su último día), así el
    puesto queda libre desde esa fecha para otra persona. La abre el botón
    "Terminar designación" de cada designación vigente (dialogos.js).
--}}
<dialog id="dialogo-terminar" class="dialogo" aria-labelledby="dialogo-terminar-titulo">
    <form method="POST" action="" class="formulario" novalidate data-validar>
        @csrf
        @method('PATCH')

        <h2 id="dialogo-terminar-titulo" class="dialogo__titulo">Terminar designación</h2>
        <p class="dialogo__texto" data-dialogo-texto></p>

        <div class="campo">
            <label for="terminar-fecha" class="campo__etiqueta">Ya no ocupa el puesto desde <x-obligatorio /></label>
            <input id="terminar-fecha" name="fecha" type="date" required class="campo__entrada" aria-describedby="terminar-fecha-ayuda">
            <p id="terminar-fecha-ayuda" class="campo__ayuda">El primer día que ya no lo ocupa. Desde ese día el puesto queda libre para otra persona.</p>
        </div>

        <div class="dialogo__acciones">
            <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="pausa" />
                Terminar designación
            </button>
        </div>
    </form>
</dialog>

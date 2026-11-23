{{--
    Cancelación ARCO (solo superadministrador): borra TODO lo personal de la
    persona, incluido nombre y CURP; si tiene cuenta, se anonimiza. No se
    puede deshacer. Doble confirmación: escribir su nombre completo.
    La abre el botón "Cancelación ARCO" (dialogos.js le pone la dirección,
    el texto con el nombre y el _id). Parámetros: $abrir y $accion (si el
    servidor la reabre porque el nombre no coincidió).
--}}
<dialog id="dialogo-arco" class="dialogo" aria-labelledby="dialogo-arco-titulo" @if ($abrir) data-abrir-al-cargar @endif>
    <form method="POST" action="{{ $accion }}" class="formulario" novalidate data-validar>
        @csrf
        @method('DELETE')
        <input type="hidden" name="_id" value="{{ old('_id') }}">

        <h2 id="dialogo-arco-titulo" class="dialogo__titulo">Cancelación ARCO</h2>
        <p class="dialogo__texto">
            Se borran <strong>todos</strong> sus datos personales: nombre, CURP, contactos, grados y designaciones.
            Si tiene cuenta, se desactiva y se anonimiza. Sus puestos quedan vacantes.
            En la bitácora solo queda su número. <strong>No se puede deshacer.</strong>
        </p>

        @if ($abrir)
            <x-errores-formulario />
        @endif

        <div class="campo">
            <label for="arco-confirmacion" class="campo__etiqueta">Para confirmar, escribe su nombre completo: <span data-dialogo-texto></span> <x-obligatorio /></label>
            <input id="arco-confirmacion" name="confirmacion" type="text" required autocomplete="off" class="campo__entrada"
                   @if ($abrir && $errors->has('confirmacion')) aria-invalid="true" @endif>
        </div>

        <div class="dialogo__acciones">
            <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton boton--peligro">
                <x-icono nombre="papelera" />
                Aplicar cancelación ARCO
            </button>
        </div>
    </form>
</dialog>

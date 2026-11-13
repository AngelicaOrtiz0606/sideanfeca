/*
 * Ventanas (<dialog>) de los catálogos: nuevo, editar, desactivar y borrar.
 *
 * Un solo botón abre la ventana y la prepara con sus atributos data-*:
 *
 *   data-abrir-dialogo="id"      la ventana que abre
 *   data-accion="/zonas/3"       a dónde se envía su formulario
 *   data-metodo="PUT"            método (se escribe en el campo _method)
 *   data-titulo="Editar zona"    texto del título ([data-dialogo-titulo])
 *   data-texto="..."             texto de apoyo ([data-dialogo-texto])
 *   data-boton="Guardar"         texto del botón de enviar ([data-dialogo-boton])
 *   data-valores='{"nombre":…}'  valores de los campos, por su atributo name
 *   data-bloqueo="No se puede…"  muestra [data-si-bloqueo] en lugar de [data-sin-bloqueo]
 *   data-etiqueta="…"            texto de la etiqueta variable ([data-dialogo-etiqueta])
 *   data-atributos='{"fecha":{"min":"2020-01-01"}}'  atributos de los campos, por su name
 *
 *   <button data-cerrar-dialogo>        la cierra (también la tecla Esc)
 *   <dialog data-abrir-al-cargar>       se abre sola al cargar (el servidor
 *                                       la marca si hubo errores al guardar)
 *
 * Antes de abrirse, la ventana recibe el evento "dialogo:preparado" para
 * que cada pantalla agregue lo suyo (por ejemplo, las entidades de una zona).
 */
function preparar(dialogo, boton) {
    const formulario = dialogo.querySelector('form');
    const datos = boton.dataset;

    if (formulario && datos.accion) {
        formulario.action = datos.accion;
    }

    const metodo = formulario?.querySelector('input[name="_method"]');
    if (metodo && datos.metodo) {
        metodo.value = datos.metodo;
    }

    [['titulo', '[data-dialogo-titulo]'], ['texto', '[data-dialogo-texto]'], ['boton', '[data-dialogo-boton]'], ['etiqueta', '[data-dialogo-etiqueta]']].forEach(([clave, selector]) => {
        const elemento = dialogo.querySelector(selector);
        if (elemento && datos[clave] !== undefined) {
            elemento.textContent = datos[clave];
        }
    });

    if (datos.valores && formulario) {
        const valores = JSON.parse(datos.valores);
        Object.entries(valores).forEach(([nombre, valor]) => {
            const campo = formulario.elements.namedItem(nombre);
            if (campo && 'value' in campo) {
                campo.value = valor ?? '';
            }
        });
    }

    if (datos.atributos && formulario) {
        Object.entries(JSON.parse(datos.atributos)).forEach(([nombre, atributos]) => {
            const campo = formulario.elements.namedItem(nombre);
            Object.entries(atributos).forEach(([atributo, valor]) => campo?.setAttribute(atributo, valor));
        });
    }

    // Una ventana que se vuelve a abrir empieza limpia: sin errores anteriores
    dialogo.querySelectorAll('[data-errores]').forEach((aviso) => aviso.remove());
    dialogo.querySelectorAll('[aria-invalid]').forEach((campo) => campo.removeAttribute('aria-invalid'));

    const bloqueada = datos.bloqueo !== undefined && datos.bloqueo !== '';
    dialogo.querySelectorAll('[data-si-bloqueo]').forEach((parte) => { parte.hidden = !bloqueada; });
    dialogo.querySelectorAll('[data-sin-bloqueo]').forEach((parte) => { parte.hidden = bloqueada; });
    const textoBloqueo = dialogo.querySelector('[data-texto-bloqueo]');
    if (textoBloqueo) {
        textoBloqueo.textContent = datos.bloqueo ?? '';
    }

    dialogo.dispatchEvent(new CustomEvent('dialogo:preparado', { bubbles: true, detail: { boton } }));

    // La validación discreta vuelve a revisar los campos ya llenos
    formulario?.dispatchEvent(new Event('change', { bubbles: true }));
}

document.addEventListener('click', (evento) => {
    const abrir = evento.target.closest('[data-abrir-dialogo]');
    if (abrir) {
        const dialogo = document.getElementById(abrir.dataset.abrirDialogo);
        if (dialogo) {
            evento.preventDefault();
            preparar(dialogo, abrir);
            dialogo.showModal();
            dialogo.querySelector('input:not([type="hidden"]):not([disabled]), select')?.focus();
        }
        return;
    }

    const cerrar = evento.target.closest('[data-cerrar-dialogo]');
    if (cerrar) {
        cerrar.closest('dialog')?.close();
    }
});

// Reabierta por un error: el cursor queda en el primer campo marcado en rojo
document.querySelectorAll('dialog[data-abrir-al-cargar]').forEach((dialogo) => {
    dialogo.showModal();
    dialogo.querySelector('[aria-invalid="true"], input:not([type="hidden"]):not([disabled]), select')?.focus();
});

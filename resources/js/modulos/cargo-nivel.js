/*
 * Ventana de cargo (#dialogo-cargo): el nivel.
 * - Si el cargo ya tiene puestos (data-puestos del botón que abre la ventana),
 *   el nivel queda fijo y se envía con un campo oculto.
 * - Para un cargo nuevo, ningún nivel aparece elegido.
 */
const ventana = document.getElementById('dialogo-cargo');

if (ventana) {
    const radios = [...ventana.querySelectorAll('input[type="radio"][name="nivel"]')];
    const oculto = ventana.querySelector('[data-nivel-oculto]');
    const nota = ventana.querySelector('[data-nivel-fijo]');

    ventana.addEventListener('dialogo:preparado', (evento) => {
        const datos = evento.detail.boton.dataset;
        const nivel = datos.valores ? JSON.parse(datos.valores).nivel ?? '' : '';
        const fijo = Number(datos.puestos ?? 0) > 0;

        radios.forEach((radio) => {
            radio.checked = radio.value === nivel;
            radio.disabled = fijo;
        });

        oculto.value = fijo ? nivel : '';
        oculto.disabled = !fijo;
        nota.hidden = !fijo;
    });
}

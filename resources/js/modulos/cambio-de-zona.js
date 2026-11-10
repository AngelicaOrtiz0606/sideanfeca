/*
 * Formulario de zona: al marcar una entidad federativa que ya pertenece a
 * otra zona, se pregunta si se quiere quitar de esa zona y pasar a esta.
 * Si la persona cancela (o cierra con Esc), la entidad se desmarca.
 *
 * Usa las casillas con data-entidad y data-zona-actual, y la ventana
 * #dialogo-cambio-zona.
 */
const dialogo = document.getElementById('dialogo-cambio-zona');

if (dialogo) {
    const texto = dialogo.querySelector('[data-texto-cambio-zona]');
    let casilla = null;

    document.querySelectorAll('input[data-zona-actual]').forEach((entrada) => {
        entrada.addEventListener('change', () => {
            if (!entrada.checked) {
                return;
            }

            casilla = entrada;
            texto.textContent = `${entrada.dataset.entidad} ya está asignada a la ${entrada.dataset.zonaActual}. ¿Quieres quitarla de esa zona y pasarla a esta?`;
            dialogo.returnValue = '';
            dialogo.showModal();
        });
    });

    dialogo.addEventListener('close', () => {
        if (casilla && dialogo.returnValue !== 'si') {
            casilla.checked = false;
            casilla.dispatchEvent(new Event('change', { bubbles: true }));
        }

        casilla?.focus();
        casilla = null;
    });
}

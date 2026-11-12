/*
 * Ventana de zona (#dialogo-zona): las 32 entidades federativas.
 *
 * - Al abrirla para una zona (botón con data-zona="id"), las entidades que ya
 *   están en ella aparecen marcadas y fijas ("En esta zona"); las demás,
 *   sin marcar y con la zona en la que están hoy. Para una zona nueva, todas
 *   aparecen sin marcar.
 * - Al marcar una entidad que pertenece a otra zona, se pregunta si se quiere
 *   quitar de esa zona y pasar a esta (#dialogo-cambio-zona). Si la persona
 *   cancela (o cierra con Esc), la entidad se desmarca.
 */
const ventana = document.getElementById('dialogo-zona');
const confirmacion = document.getElementById('dialogo-cambio-zona');

if (ventana && confirmacion) {
    const casillas = [...ventana.querySelectorAll('input[data-zona-id]')];
    const texto = confirmacion.querySelector('[data-texto-cambio-zona]');
    let pendiente = null;

    ventana.addEventListener('dialogo:preparado', (evento) => {
        const zona = evento.detail.boton.dataset.zona ?? '';

        casillas.forEach((casilla) => {
            const aqui = zona !== '' && casilla.dataset.zonaId === zona;
            casilla.checked = aqui;
            casilla.disabled = aqui;
            casilla.closest('label').classList.toggle('opcion-entidad--fija', aqui);
            casilla.closest('label').querySelector('[data-zona-leyenda]').textContent = aqui
                ? 'En esta zona'
                : `Zona ${casilla.dataset.zonaNumero}`;
        });
    });

    casillas.forEach((casilla) => {
        casilla.addEventListener('change', () => {
            if (!casilla.checked || casilla.disabled) {
                return;
            }

            pendiente = casilla;
            texto.textContent = `${casilla.dataset.entidad} ya está asignada a la ${casilla.dataset.zonaActual}. ¿Quieres quitarla de esa zona y pasarla a esta?`;
            confirmacion.returnValue = '';
            confirmacion.showModal();
        });
    });

    confirmacion.addEventListener('close', () => {
        if (pendiente && confirmacion.returnValue !== 'si') {
            pendiente.checked = false;
        }

        pendiente?.focus();
        pendiente = null;
    });
}

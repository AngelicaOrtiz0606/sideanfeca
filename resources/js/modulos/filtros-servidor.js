/*
 * Filtros que se aplican en el servidor (form[data-filtros-servidor]):
 * - al cambiar una lista desplegable se aplican solos; las fechas de un
 *   periodo se aplican con su botón «Ver periodo», para elegir las dos con calma;
 * - el buscador se aplica solo cuando se deja de escribir (o con Enter), y
 *   al recargar el cursor queda al final de lo escrito para seguir buscando.
 * Los filtros vacíos no se mandan, para que la dirección quede limpia.
 */
document.querySelectorAll('form[data-filtros-servidor]').forEach((formulario) => {
    // En teléfono el botón "Filtros" abre y cierra las listas (en computadora siempre se ven)
    const boton = formulario.querySelector('[data-filtros-boton]');
    const opciones = formulario.querySelector('[data-filtros-opciones]');
    boton?.addEventListener('click', () => {
        const abrir = opciones.hasAttribute('data-cerrado');
        opciones.toggleAttribute('data-cerrado', !abrir);
        boton.setAttribute('aria-expanded', String(abrir));
    });

    formulario.querySelectorAll('select').forEach((lista) => {
        lista.addEventListener('change', () => formulario.requestSubmit());
    });

    const buscador = formulario.querySelector('[data-filtro-escrito]');
    let espera = null;
    buscador?.addEventListener('input', () => {
        clearTimeout(espera);
        espera = setTimeout(() => formulario.requestSubmit(), 800);
    });
    // Al recargar después de buscar, el cursor queda al final de lo escrito para seguir escribiendo
    if (buscador?.hasAttribute('autofocus')) {
        const alFinal = () => buscador.setSelectionRange(buscador.value.length, buscador.value.length);
        buscador.focus();
        alFinal();
        requestAnimationFrame(alFinal);
    }

    formulario.addEventListener('submit', () => {
        formulario.querySelectorAll('input, select').forEach((campo) => {
            if (campo.name && campo.value === '') {
                campo.disabled = true;
            }
        });
    });
});

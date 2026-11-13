/*
 * Filtros que se aplican en el servidor (form[data-filtros-servidor]):
 * al cambiar una lista desplegable se aplica sola; el buscador se aplica al
 * presionar Enter o el botón Filtrar. Los filtros vacíos no se mandan, para
 * que la dirección quede limpia.
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

    formulario.addEventListener('submit', () => {
        formulario.querySelectorAll('input, select').forEach((campo) => {
            if (campo.name && campo.value === '') {
                campo.disabled = true;
            }
        });
    });
});

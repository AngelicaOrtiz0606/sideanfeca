/*
 * Ventana de puesto ([data-puesto]): según el nivel del cargo elegido se
 * muestra lo que aplica (institución y titular; zona; coordinación). Lo que
 * no aplica se oculta y se desactiva, así no se envía ni se valida.
 * Si el puesto ya tiene designaciones (data-ambito-fijo en el botón que abre
 * la ventana), el cargo y el ámbito quedan fijos y se explica por qué.
 */
document.querySelectorAll('form[data-puesto]').forEach((formulario) => {
    const cargo = formulario.querySelector('[data-puesto-cargo]');
    const guia = formulario.querySelector('[data-puesto-guia]');
    const nota = formulario.querySelector('[data-ambito-nota]');

    const actualizar = () => {
        const nivel = cargo.selectedOptions[0]?.dataset.nivel ?? '';
        formulario.querySelectorAll('[data-solo-nivel]').forEach((bloque) => {
            const visible = bloque.dataset.soloNivel.split(' ').includes(nivel);
            bloque.hidden = !visible;
            bloque.querySelectorAll('input, select').forEach((campo) => { campo.disabled = !visible; });
        });
        guia.textContent = nivel === '' ? 'El nivel del cargo decide qué más se pide.' : `Nivel ${nivel.toLowerCase()}.`;
        formulario.dispatchEvent(new Event('change', { bubbles: true }));
    };

    // Ámbito fijo: los campos se ven y se envían, pero no se pueden cambiar
    const fijar = (motivo) => {
        nota.textContent = motivo;
        nota.hidden = motivo === '';
        formulario.querySelectorAll('[data-ambito]').forEach((bloque) => {
            bloque.classList.toggle('campo--fijo', motivo !== '');
            bloque.querySelectorAll('input, select').forEach((campo) => {
                campo.tabIndex = motivo !== '' ? -1 : 0;
                campo.toggleAttribute('aria-readonly', motivo !== '');
            });
        });
    };

    cargo.addEventListener('change', actualizar);
    formulario.closest('dialog')?.addEventListener('dialogo:preparado', (evento) => {
        actualizar();
        fijar(evento.detail.boton.dataset.ambitoFijo ?? '');
    });
    formulario.addEventListener('reset', () => setTimeout(actualizar));
    actualizar();
});

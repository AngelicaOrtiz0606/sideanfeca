/*
 * Ventana de puesto ([data-puesto]): primero el nivel; la lista de cargos
 * muestra solo los de ese nivel. Según el nivel se muestra lo que aplica
 * (institución y titular; zona; coordinación). Lo que no aplica se oculta y
 * se desactiva, así no se envía ni se valida.
 * Si el puesto ya tiene designaciones (data-ambito-fijo en el botón que abre
 * la ventana), el cargo y el ámbito quedan fijos y se explica por qué.
 */
document.querySelectorAll('form[data-puesto]').forEach((formulario) => {
    const cargo = formulario.querySelector('[data-puesto-cargo]');
    const guia = formulario.querySelector('[data-puesto-guia]');
    const nota = formulario.querySelector('[data-ambito-nota]');

    const niveles = [...formulario.querySelectorAll('input[name="_nivel"]')];

    // Solo los cargos del nivel elegido; sin nivel, la lista espera
    const filtrarCargos = (nivel) => {
        cargo.querySelectorAll('optgroup').forEach((grupo) => {
            const delNivel = grupo.label === nivel;
            grupo.hidden = !delNivel;
            grupo.disabled = !delNivel;
        });
        if ((cargo.selectedOptions[0]?.dataset.nivel ?? nivel) !== nivel) {
            cargo.value = '';
        }
        cargo.disabled = nivel === '';
    };

    const actualizar = () => {
        // Al editar (o al volver con errores) el nivel sale del cargo ya elegido
        const delCargo = cargo.selectedOptions[0]?.dataset.nivel ?? '';
        if (delCargo !== '') {
            niveles.forEach((radio) => { radio.checked = radio.value === delCargo; });
        }
        filtrarCargos(niveles.find((radio) => radio.checked)?.value ?? '');

        const nivel = cargo.selectedOptions[0]?.dataset.nivel ?? '';
        formulario.querySelectorAll('[data-solo-nivel]').forEach((bloque) => {
            const visible = bloque.dataset.soloNivel.split(' ').includes(nivel);
            bloque.hidden = !visible;
            bloque.querySelectorAll('input, select').forEach((campo) => { campo.disabled = !visible; });
        });
        const elegido = niveles.find((radio) => radio.checked)?.value ?? '';
        guia.textContent = elegido === '' ? 'Primero elige el nivel.' : `Solo aparecen los cargos de nivel ${elegido.toLowerCase()}.`;
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
        // Con el ámbito fijo el nivel tampoco se cambia (el servidor no lo usa: sale del cargo)
        niveles.forEach((radio) => {
            if (!radio.checked) {
                radio.disabled = motivo !== '';
            }
        });
    };

    cargo.addEventListener('change', actualizar);
    niveles.forEach((radio) => radio.addEventListener('change', () => {
        filtrarCargos(radio.value);
        actualizar();
    }));
    formulario.closest('dialog')?.addEventListener('dialogo:preparado', (evento) => {
        actualizar();
        fijar(evento.detail.boton.dataset.ambitoFijo ?? '');
    });
    formulario.addEventListener('reset', () => setTimeout(actualizar));
    actualizar();
});

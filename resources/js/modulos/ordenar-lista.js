/*
 * Lista que se puede reordenar (form[data-ordenar]):
 * - arrastrando cada elemento con el ratón, o
 * - con los botones de subir y bajar (teclado y teléfono).
 *
 * El orden se guarda en los campos ocultos orden[] de cada elemento, en el
 * orden en que quedan en la página. "Guardar orden" se activa solo si el
 * orden cambió. Cada movimiento se anuncia a los lectores de pantalla.
 */
document.querySelectorAll('form[data-ordenar]').forEach((formulario) => {
    const lista = formulario.querySelector('[data-ordenar-lista]');
    const guardar = formulario.querySelector('[data-ordenar-guardar]');
    const anuncio = formulario.querySelector('[data-ordenar-anuncio]');
    const elementos = () => [...lista.querySelectorAll('[data-ordenar-item]')];
    const ordenDe = () => elementos().map((item) => item.querySelector('input').value).join(',');
    const inicial = ordenDe();
    let arrastrado = null;

    const actualizar = (movido) => {
        elementos().forEach((item, indice) => {
            item.querySelector('[data-ordenar-lugar]').textContent = indice + 1;
            item.querySelector('[data-ordenar-subir]').disabled = indice === 0;
            item.querySelector('[data-ordenar-bajar]').disabled = indice === elementos().length - 1;
            item.toggleAttribute('data-movida', item === movido);
        });

        guardar.disabled = ordenDe() === inicial;

        if (movido) {
            const nombre = movido.querySelector('[data-ordenar-nombre]').textContent.trim();
            anuncio.textContent = `${nombre}, lugar ${elementos().indexOf(movido) + 1} de ${elementos().length}.`;
        }
    };

    lista.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-ordenar-subir], [data-ordenar-bajar]');
        if (!boton) {
            return;
        }

        const item = boton.closest('[data-ordenar-item]');
        const subir = boton.hasAttribute('data-ordenar-subir');
        const vecino = subir ? item.previousElementSibling : item.nextElementSibling;
        if (!vecino) {
            return;
        }

        if (subir) {
            vecino.before(item);
        } else {
            vecino.after(item);
        }

        actualizar(item);

        // El foco se queda en el mismo botón (o en el otro si este ya no se puede usar)
        const mismo = item.querySelector(subir ? '[data-ordenar-subir]' : '[data-ordenar-bajar]');
        (mismo.disabled ? item.querySelector(subir ? '[data-ordenar-bajar]' : '[data-ordenar-subir]') : mismo).focus();
    });

    lista.addEventListener('dragstart', (evento) => {
        arrastrado = evento.target.closest('[data-ordenar-item]');
        arrastrado?.setAttribute('data-arrastrando', '');
        evento.dataTransfer.effectAllowed = 'move';
    });

    lista.addEventListener('dragover', (evento) => {
        const destino = evento.target.closest('[data-ordenar-item]');
        if (!arrastrado || !destino || destino === arrastrado) {
            return;
        }

        evento.preventDefault();
        const caja = destino.getBoundingClientRect();
        if (evento.clientY < caja.top + caja.height / 2) {
            destino.before(arrastrado);
        } else {
            destino.after(arrastrado);
        }
    });

    lista.addEventListener('dragend', () => {
        arrastrado?.removeAttribute('data-arrastrando');
        actualizar(arrastrado);
        arrastrado = null;
    });

    actualizar(null);
});

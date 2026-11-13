/*
 * Buscador de universidad ([data-buscador-universidad]): un cuadro de texto
 * que filtra la lista mientras se escribe (por nombre, zona o entidad, sin
 * importar mayúsculas ni acentos). Se elige con clic o con las flechas y
 * Enter; Esc cierra la lista. El identificador elegido va en un campo oculto.
 * Si lo escrito no corresponde a una universidad de la lista, el campo queda
 * inválido y el botón de guardar no se activa.
 */
const normalizar = (texto) => texto.normalize('NFD').replace(/[̀́̂̈]/g, '').normalize('NFC').toLowerCase().trim();

document.querySelectorAll('[data-buscador-universidad]').forEach((buscador) => {
    const texto = buscador.querySelector('[data-buscador-texto]');
    const valor = buscador.querySelector('[data-buscador-valor]');
    const detalle = buscador.querySelector('[data-buscador-detalle]');
    const lista = buscador.querySelector('[role="listbox"]');
    const vacio = buscador.querySelector('[data-buscador-vacio]');
    const opciones = [...buscador.querySelectorAll('[role="option"]')];
    let activa = -1;

    const visibles = () => opciones.filter((o) => !o.hidden);

    const validar = () => {
        texto.setCustomValidity(texto.required && !texto.disabled && valor.value === '' ? 'Elige una universidad de la lista.' : '');
    };

    const abrir = (abierta) => {
        lista.hidden = !abierta;
        texto.setAttribute('aria-expanded', String(abierta));
        if (!abierta) {
            texto.removeAttribute('aria-activedescendant');
            activa = -1;
        }
    };

    const filtrar = () => {
        const buscado = normalizar(texto.value);
        opciones.forEach((opcion) => {
            opcion.hidden = buscado !== '' && !normalizar(`${opcion.dataset.nombre} ${opcion.dataset.detalle}`).includes(buscado);
            opcion.setAttribute('aria-selected', 'false');
        });
        vacio.hidden = visibles().length > 0;
        activa = -1;
    };

    const marcar = (indice) => {
        const lista = visibles();
        if (lista.length === 0) {
            return;
        }
        activa = (indice + lista.length) % lista.length;
        lista.forEach((o, i) => o.setAttribute('aria-selected', String(i === activa)));
        texto.setAttribute('aria-activedescendant', lista[activa].id);
        lista[activa].scrollIntoView({ block: 'nearest' });
    };

    const elegir = (opcion) => {
        valor.value = opcion.dataset.id;
        texto.value = opcion.dataset.nombre;
        detalle.textContent = opcion.dataset.detalle;
        abrir(false);
        validar();
        texto.dispatchEvent(new Event('change', { bubbles: true }));
    };

    texto.addEventListener('input', () => {
        valor.value = '';
        detalle.textContent = '';
        filtrar();
        abrir(true);
        validar();
    });

    texto.addEventListener('focus', () => {
        filtrar();
        abrir(true);
    });

    texto.addEventListener('keydown', (evento) => {
        if (evento.key === 'ArrowDown' || evento.key === 'ArrowUp') {
            evento.preventDefault();
            abrir(true);
            marcar(activa + (evento.key === 'ArrowDown' ? 1 : -1));
        } else if (evento.key === 'Enter' && !lista.hidden && activa >= 0) {
            evento.preventDefault();
            elegir(visibles()[activa]);
        } else if (evento.key === 'Escape' && !lista.hidden) {
            evento.preventDefault();
            evento.stopPropagation();
            abrir(false);
        }
    });

    lista.addEventListener('mousedown', (evento) => evento.preventDefault()); // no perder el foco al dar clic
    lista.addEventListener('click', (evento) => {
        const opcion = evento.target.closest('[role="option"]');
        if (opcion) {
            elegir(opcion);
        }
    });

    texto.addEventListener('blur', () => abrir(false));

    // Al mostrarse u ocultarse (tipo de institución), se revisa otra vez
    new MutationObserver(validar).observe(texto, { attributes: true, attributeFilter: ['disabled'] });
    validar();
});

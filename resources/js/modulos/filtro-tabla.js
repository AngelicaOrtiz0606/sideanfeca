/*
 * Filtro rápido de una tabla ([data-filtro-tabla]), sin recargar:
 * - [data-filtro-texto]: busca en el texto de cada fila (sin acentos ni mayúsculas).
 * - [data-filtro-valor]: botones de grupo (por ejemplo, el nivel del cargo).
 * Las filas llevan data-filtro-fila, data-texto y data-grupo. Si ninguna
 * coincide, aparece [data-filtro-vacio].
 */
const normalizar = (texto) => texto.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase().trim();

document.querySelectorAll('[data-filtro-tabla]').forEach((contenedor) => {
    const campo = contenedor.querySelector('[data-filtro-texto]');
    const botones = [...contenedor.querySelectorAll('[data-filtro-valor]')];
    const filas = [...contenedor.querySelectorAll('[data-filtro-fila]')];
    const vacio = contenedor.querySelector('[data-filtro-vacio]');
    let grupo = '';

    const aplicar = () => {
        const buscado = normalizar(campo?.value ?? '');
        let visibles = 0;

        filas.forEach((fila) => {
            const coincide = (grupo === '' || fila.dataset.grupo === grupo)
                && (buscado === '' || normalizar(fila.dataset.texto).includes(buscado));
            fila.hidden = !coincide;
            visibles += coincide ? 1 : 0;
        });

        if (vacio) {
            vacio.hidden = visibles > 0;
        }
    };

    campo?.addEventListener('input', aplicar);

    botones.forEach((boton) => {
        boton.addEventListener('click', () => {
            grupo = boton.dataset.filtroValor;
            botones.forEach((otro) => otro.setAttribute('aria-pressed', String(otro === boton)));
            aplicar();
        });
    });
});

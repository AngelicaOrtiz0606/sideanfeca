/*
 * Contadores de la pantalla de inicio: el número sube de 0 a su valor al cargar.
 * Si la persona pidió menos movimiento en su sistema, se muestra directo.
 */
const contadores = document.querySelectorAll('[data-contar]');
const menosMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (!menosMovimiento) {
    contadores.forEach((elemento) => {
        const final = Number(elemento.dataset.contar);

        if (!Number.isFinite(final) || final <= 0) {
            return;
        }

        const duracion = 900;
        const inicio = performance.now();
        const formato = new Intl.NumberFormat('es-MX');

        const paso = (ahora) => {
            const avance = Math.min((ahora - inicio) / duracion, 1);
            // Curva que desacelera al final (salida exponencial)
            const suave = avance === 1 ? 1 : 1 - 2 ** (-10 * avance);
            elemento.textContent = formato.format(Math.round(final * suave));

            if (avance < 1) {
                requestAnimationFrame(paso);
            }
        };

        elemento.textContent = '0';
        requestAnimationFrame(paso);
    });
}

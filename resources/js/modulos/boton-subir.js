/*
 * Botón redondo para subir: aparece solo cuando la página ya bajó.
 */
const botonSubir = document.querySelector('[data-boton-subir]');

if (botonSubir) {
    // Aparece al bajar 320 px o, en páginas cortas, al bajar más de la mitad de lo que se puede bajar
    const revisarDesplazamiento = () => {
        const maximo = document.documentElement.scrollHeight - window.innerHeight;
        const umbral = Math.max(80, Math.min(320, maximo * 0.5));
        botonSubir.hidden = maximo <= 0 || window.scrollY < umbral;
    };

    window.addEventListener('scroll', revisarDesplazamiento, { passive: true });
    revisarDesplazamiento();
}

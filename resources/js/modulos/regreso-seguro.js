/*
 * Flecha de regreso después de cerrar sesión.
 *
 * Algunos navegadores guardan la página completa en memoria al salir de ella
 * y la vuelven a mostrar con la flecha de regreso, sin pedirla al servidor.
 * En las pantallas con sesión (body con data-con-sesion) se pide de nuevo:
 * si la sesión ya se cerró, el servidor manda al inicio de sesión.
 */
window.addEventListener('pageshow', (evento) => {
    if (evento.persisted && document.body.hasAttribute('data-con-sesion')) {
        window.location.reload();
    }
});

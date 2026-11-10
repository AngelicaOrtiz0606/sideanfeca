/*
 * Ventanas de confirmación con <dialog>.
 *
 *   <button data-abrir-dialogo="id">       abre la ventana con ese id
 *   <button data-cerrar-dialogo>            la cierra (también la tecla Esc)
 *   <dialog data-abrir-al-cargar>           se abre sola al cargar la página
 *                                           (por ejemplo, si faltó elegir el motivo)
 */
document.addEventListener('click', (evento) => {
    const abrir = evento.target.closest('[data-abrir-dialogo]');
    if (abrir) {
        document.getElementById(abrir.dataset.abrirDialogo)?.showModal();
        return;
    }

    const cerrar = evento.target.closest('[data-cerrar-dialogo]');
    if (cerrar) {
        cerrar.closest('dialog')?.close();
    }
});

document.querySelectorAll('dialog[data-abrir-al-cargar]').forEach((dialogo) => dialogo.showModal());

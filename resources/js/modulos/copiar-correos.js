/*
 * Copiar los correos de un directorio (Consultar directorios).
 *
 *   <textarea data-correos>              los correos, separados por "; "
 *   <button data-copiar-correos>         los copia al portapapeles
 *   <p data-correos-aviso role="status"> dice si se copiaron
 *
 * Si el navegador no deja usar el portapapeles, deja los correos
 * seleccionados para copiarlos con Ctrl + C.
 */
document.querySelectorAll('[data-copiar-correos]').forEach((boton) => {
    const dialogo = boton.closest('dialog') ?? document;
    const texto = dialogo.querySelector('[data-correos]');
    const aviso = dialogo.querySelector('[data-correos-aviso]');
    if (!texto) {
        return;
    }

    const avisar = (mensaje) => {
        if (aviso) {
            aviso.textContent = mensaje;
        }
    };

    boton.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(texto.value);
            avisar('Listo: se copiaron los correos. Pégalos en tu correo.');
        } catch {
            texto.focus();
            texto.select();
            avisar('Los correos quedaron seleccionados: cópialos con Ctrl + C.');
        }
    });

    // Al volver a abrir la ventana, el aviso anterior ya no aplica
    dialogo.addEventListener?.('close', () => avisar(''));
});

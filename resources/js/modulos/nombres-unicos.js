/*
 * Aviso inmediato de nombres repetidos en los catálogos.
 *
 * Un campo con data-existentes='[[id, "valor", "como se muestra"], ...]' y
 * data-mensaje-repetido="Ya existe el cargo «:existente»." se revisa mientras
 * se escribe: si el valor ya existe (sin importar mayúsculas, acentos ni
 * espacios, igual que App\Support\TextoCatalogo), la casilla se marca en rojo,
 * abajo aparece el mensaje y el botón de guardar no se activa.
 *
 * Al editar, el registro no se compara consigo mismo (campo oculto _id del
 * formulario). El servidor lo vuelve a revisar al guardar.
 */
const clave = (texto) => texto
    .replace(/\s+/g, ' ')
    .trim()
    .normalize('NFD')
    .replace(/[̀́̂̈]/g, '')
    .normalize('NFC')
    .toLowerCase();

function retroDe(campo) {
    let retro = campo.parentElement.querySelector('[data-retro-unico]');
    if (!retro) {
        retro = document.createElement('p');
        retro.className = 'campo__retro';
        retro.dataset.retroUnico = '';
        retro.dataset.tipo = 'error';
        retro.id = `${campo.id}-repetido`;
        retro.setAttribute('aria-live', 'polite');
        campo.insertAdjacentElement('afterend', retro);
        campo.setAttribute('aria-describedby', [campo.getAttribute('aria-describedby'), retro.id].filter(Boolean).join(' '));
    }
    return retro;
}

function revisar(campo) {
    const existentes = JSON.parse(campo.dataset.existentes);
    const propio = campo.form?.elements.namedItem('_id')?.value ?? '';
    const valor = clave(campo.value);
    const repetido = valor === ''
        ? null
        : existentes.find(([id, comparado]) => String(id) !== String(propio) && clave(String(comparado)) === valor);

    const retro = retroDe(campo);
    if (repetido) {
        const mensaje = campo.dataset.mensajeRepetido
            .replace(':existente', repetido[2] ?? repetido[1])
            .replace(':valor', campo.value.trim());
        campo.setCustomValidity(mensaje);
        campo.setAttribute('aria-invalid', 'true');
        retro.textContent = mensaje;
        retro.hidden = false;
    } else {
        campo.setCustomValidity('');
        retro.textContent = '';
        retro.hidden = true;
        // Sin error de nombre repetido, la validación discreta decide el resto
        if (campo.value.trim() !== '' && campo.checkValidity()) {
            campo.removeAttribute('aria-invalid');
        }
    }
}

document.querySelectorAll('[data-existentes]').forEach((campo) => {
    campo.addEventListener('input', () => revisar(campo));
});

// Al abrir una ventana ya llena (Editar), se revisa desde el principio
document.addEventListener('dialogo:preparado', (evento) => {
    evento.target.querySelectorAll('[data-existentes]').forEach(revisar);
});

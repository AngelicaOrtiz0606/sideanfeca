/*
 * Aviso inmediato en los textos de los catálogos, mientras se escribe.
 *
 * 1. Texto seguro (campos con data-texto-seguro): sin caracteres de control,
 *    sin < ni >, y sin empezar con = + - @ (igual que App\Rules\TextoSeguro).
 * 2. Nombres repetidos:
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

function problemaDeTexto(valor) {
    if (/[\u0000-\u001F\u007F]/.test(valor)) {
        return 'El texto tiene caracteres que no se permiten (por ejemplo, saltos de línea).';
    }
    if (/[<>]/.test(valor)) {
        return 'El texto no puede llevar los símbolos < ni >.';
    }
    if (/^\s*[=+\-@]/.test(valor)) {
        return 'El texto no puede empezar con =, +, - ni @.';
    }
    return null;
}

function repetidoDe(campo) {
    if (!campo.dataset.existentes) {
        return null;
    }

    const existentes = JSON.parse(campo.dataset.existentes);
    const propio = campo.form?.elements.namedItem('_id')?.value ?? '';
    const valor = clave(campo.value);
    const repetido = valor === ''
        ? null
        : existentes.find(([id, comparado]) => String(id) !== String(propio) && clave(String(comparado)) === valor);

    return repetido
        ? campo.dataset.mensajeRepetido.replace(':existente', repetido[2] ?? repetido[1]).replace(':valor', campo.value.trim())
        : null;
}

function revisar(campo) {
    const inseguro = campo.hasAttribute('data-texto-seguro') ? problemaDeTexto(campo.value) : null;
    const mensaje = inseguro ?? repetidoDe(campo);

    const retro = retroDe(campo);
    if (mensaje) {
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

const CAMPOS = '[data-existentes], [data-texto-seguro]';

document.querySelectorAll(CAMPOS).forEach((campo) => {
    campo.addEventListener('input', () => revisar(campo));
});

// Al abrir una ventana ya llena (Editar), se revisa desde el principio
document.addEventListener('dialogo:preparado', (evento) => {
    evento.target.querySelectorAll(CAMPOS).forEach(revisar);
});

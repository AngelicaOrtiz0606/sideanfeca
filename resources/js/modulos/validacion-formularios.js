/*
 * Formularios con data-validar: validación discreta y botón de enviar
 * inactivo hasta que todo esté bien llenado.
 *
 * - En cuanto la persona empieza a escribir en un campo, este se delinea en
 *   rojo mientras no sea válido y vuelve a la normalidad cuando lo es.
 * - data-regla="nueva": marca la lista de requisitos de la contraseña.
 * - data-igual-a="id": indica si la confirmación coincide.
 *
 * Es una ayuda para la persona; el servidor vuelve a validar todo
 * (FormRequest) y la base solo recibe consultas con parámetros.
 */
const REQUISITOS = {
    largo: (valor) => valor.length >= 8 && valor.length <= 72,
    mayuscula: (valor) => /\p{Lu}/u.test(valor),
    numero: (valor) => /\p{N}/u.test(valor),
    // Mismo criterio que Laravel (Password::symbols): signos, puntuación o espacios
    especial: (valor) => /[\p{S}\p{P}\p{Z}]/u.test(valor),
};

function estaAutocompletado(campo) {
    try {
        return campo.matches(':autofill');
    } catch {
        return false;
    }
}

function marcarRequisitos(campo) {
    const lista = document.querySelector(`[data-requisitos="${campo.id}"]`);
    let todos = true;

    Object.entries(REQUISITOS).forEach(([clave, cumple]) => {
        const ok = cumple(campo.value);
        todos = todos && ok;

        const item = lista?.querySelector(`[data-requisito="${clave}"]`);
        if (item) {
            item.dataset.cumple = String(ok);
            item.querySelector('[data-estado-requisito]').textContent = ok ? ': cumplido' : ': pendiente';
        }
    });

    return todos;
}

function campoEsValido(campo) {
    const valor = campo.value;

    // Un campo opcional vacío está bien; casillas y campos inactivos no se revisan
    if (campo.disabled || campo.type === 'checkbox' || (!campo.required && valor.trim() === '')) {
        return true;
    }

    if (valor === '' && estaAutocompletado(campo)) {
        return true;
    }

    if (campo.dataset.regla === 'nueva') {
        return marcarRequisitos(campo);
    }

    if (campo.dataset.igualA) {
        const otro = document.getElementById(campo.dataset.igualA);
        const coincide = valor !== '' && otro !== null && otro.value === valor;
        const retro = document.getElementById(`${campo.id}-retro`);

        if (retro) {
            retro.textContent = valor === '' ? '' : coincide ? 'Las contraseñas coinciden.' : 'Las contraseñas todavía no coinciden.';
            retro.dataset.tipo = coincide ? 'exito' : 'error';
        }

        return coincide;
    }

    return valor.trim() !== '' && campo.checkValidity();
}

document.querySelectorAll('form[data-validar]').forEach((formulario) => {
    const boton = formulario.querySelector('[type="submit"]');
    const campos = [...formulario.querySelectorAll('input:not([type="hidden"]), select, textarea')];
    const escritos = new Set();

    const actualizar = () => {
        const resultados = campos.map((campo) => {
            const valido = campoEsValido(campo);

            // Solo se delinea en rojo un campo en el que la persona ya empezó a escribir
            if (escritos.has(campo)) {
                campo.setAttribute('aria-invalid', String(!valido));
            }

            return valido;
        });

        boton.disabled = !resultados.every(Boolean);
    };

    campos.forEach((campo) => {
        campo.addEventListener('input', () => {
            escritos.add(campo);

            // Al corregir, el mensaje que mandó el servidor para este campo ya no aplica
            document.getElementById(`${campo.id}-error`)?.setAttribute('hidden', '');
        });
    });

    formulario.addEventListener('input', actualizar);
    formulario.addEventListener('change', actualizar);
    window.addEventListener('load', () => setTimeout(actualizar, 300));
    actualizar();
});

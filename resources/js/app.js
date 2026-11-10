/*
 * Menú lateral en teléfono y tableta (menos de 1024 px de ancho).
 *
 * - El botón del encabezado abre el panel y actualiza aria-expanded.
 * - Se cierra con el botón "Cerrar menú", con la tecla Esc o tocando fuera.
 * - Al abrir, el foco va al primer enlace; al cerrar, regresa al botón.
 * En pantallas grandes el menú siempre está visible y esto no interviene.
 */
const botonAbrir = document.querySelector('[data-menu-abrir]');
const menu = document.getElementById('menu-lateral');
const botonCerrar = document.querySelector('[data-menu-cerrar]');
const velo = document.querySelector('[data-menu-velo]');
const pantallaGrande = window.matchMedia('(min-width: 64rem)');

function estaAbierto() {
    return menu?.dataset.abierto === 'true';
}

function cambiarMenu(abrir, { regresarFoco = true } = {}) {
    if (!menu || !botonAbrir) {
        return;
    }

    menu.dataset.abierto = String(abrir);
    botonAbrir.setAttribute('aria-expanded', String(abrir));
    document.body.classList.toggle('menu-abierto', abrir);

    if (velo) {
        velo.hidden = !abrir;
    }

    if (abrir) {
        menu.querySelector('a, [data-menu-cerrar]')?.focus();
    } else if (regresarFoco) {
        botonAbrir.focus();
    }
}

if (menu && botonAbrir) {
    botonAbrir.addEventListener('click', () => cambiarMenu(!estaAbierto()));
    botonCerrar?.addEventListener('click', () => cambiarMenu(false));
    velo?.addEventListener('click', () => cambiarMenu(false));

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && estaAbierto()) {
            cambiarMenu(false);
        }
    });

    // Si la ventana crece a pantalla grande con el panel abierto, se cierra sin mover el foco
    pantallaGrande.addEventListener('change', (evento) => {
        if (evento.matches && estaAbierto()) {
            cambiarMenu(false, { regresarFoco: false });
        }
    });
}

/*
 * Campo de contraseña:
 * - El botón "Mostrar / Ocultar" deja ver lo que se escribió.
 * - Si la tecla Bloq Mayús está activada, aparece un aviso debajo del campo.
 */
document.querySelectorAll('[data-mostrar-contrasenia]').forEach((boton) => {
    const campo = document.getElementById(boton.getAttribute('aria-controls'));
    const texto = boton.querySelector('[data-texto-boton]');
    const iconoMostrar = boton.querySelector('[data-icono-mostrar]');
    const iconoOcultar = boton.querySelector('[data-icono-ocultar]');

    if (!campo) {
        return;
    }

    boton.addEventListener('click', () => {
        const mostrar = campo.type === 'password';

        campo.type = mostrar ? 'text' : 'password';
        boton.setAttribute('aria-pressed', String(mostrar));
        texto.textContent = mostrar ? 'Ocultar' : 'Mostrar';
        iconoMostrar.toggleAttribute('hidden', mostrar);
        iconoOcultar.toggleAttribute('hidden', !mostrar);
    });

    // Antes de enviar, el campo vuelve a ocultarse para que el navegador no lo guarde como texto
    campo.form?.addEventListener('submit', () => {
        campo.type = 'password';
    });
});

document.querySelectorAll('[data-contrasenia]').forEach((campo) => {
    const aviso = document.getElementById(campo.dataset.aviso);

    if (!aviso) {
        return;
    }

    const revisar = (evento) => {
        const activada = evento.getModifierState?.('CapsLock') ?? false;
        aviso.textContent = activada ? 'Tienes activadas las mayúsculas (Bloq Mayús).' : '';
    };

    campo.addEventListener('keydown', revisar);
    campo.addEventListener('keyup', revisar);
    campo.addEventListener('blur', () => {
        aviso.textContent = '';
    });
});

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
    const campos = [...formulario.querySelectorAll('input:not([type="hidden"])')];
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

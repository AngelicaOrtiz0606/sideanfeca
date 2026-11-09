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
    const aviso = document.querySelector('[data-aviso-mayusculas]');

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

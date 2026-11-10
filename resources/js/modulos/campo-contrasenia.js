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

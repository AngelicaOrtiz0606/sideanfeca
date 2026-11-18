/*
 * Pestañas ([data-pestanas]): cada pestaña es un enlace (?pestana=…), así
 * funcionan sin JavaScript. Aquí se cambian sin recargar, se actualiza la
 * dirección (para volver a la misma pestaña) y se navega con las flechas.
 */
document.querySelectorAll('[data-pestanas]').forEach((grupo) => {
    const pestanas = [...grupo.querySelectorAll('[data-pestana]')];

    const elegir = (pestana, enfocar) => {
        pestanas.forEach((otra) => {
            const elegida = otra === pestana;
            otra.setAttribute('aria-selected', elegida ? 'true' : 'false');
            otra.tabIndex = elegida ? 0 : -1;
            document.getElementById(otra.getAttribute('aria-controls')).hidden = !elegida;
        });
        history.replaceState(null, '', pestana.href);
        if (enfocar) {
            pestana.focus();
        }
    };

    pestanas.forEach((pestana, indice) => {
        pestana.addEventListener('click', (evento) => {
            evento.preventDefault();
            elegir(pestana, false);
        });

        pestana.addEventListener('keydown', (evento) => {
            const destino = {
                ArrowRight: pestanas[(indice + 1) % pestanas.length],
                ArrowLeft: pestanas[(indice - 1 + pestanas.length) % pestanas.length],
                Home: pestanas[0],
                End: pestanas[pestanas.length - 1],
            }[evento.key];

            if (destino) {
                evento.preventDefault();
                elegir(destino, true);
            }
        });
    });
});

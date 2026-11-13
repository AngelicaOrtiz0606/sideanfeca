/*
 * Domicilio con el catálogo de SEPOMEX ([data-domicilio]).
 *
 * Al escribir los 5 números del código postal se piden al servidor sus
 * colonias, municipio, entidad y zona: se llenan la entidad y el municipio,
 * aparece la lista de colonias y se sugiere la zona (si la persona no la
 * cambió a mano). Todo se escribe como texto, nunca como HTML.
 */
document.querySelectorAll('[data-domicilio]').forEach((bloque) => {
    const cp = bloque.querySelector('[data-codigo-postal]');
    const colonias = bloque.querySelector('[data-colonias]');
    const entidad = bloque.querySelector('[data-entidad]');
    const municipio = bloque.querySelector('[data-municipio]');
    const zona = bloque.querySelector('[data-zona]');
    const retro = bloque.querySelector('[data-retro-cp]');
    const url = bloque.dataset.urlCodigoPostal;
    let zonaManual = false;
    let ultimo = '';

    zona?.addEventListener('change', () => { zonaManual = true; });

    const opcion = (valor, texto, elegida = false) => {
        const elemento = document.createElement('option');
        elemento.value = valor;
        elemento.textContent = texto;
        elemento.selected = elegida;
        return elemento;
    };

    const avisar = (texto) => {
        retro.textContent = texto;
        retro.hidden = texto === '';
    };

    const limpiar = (texto) => {
        colonias.replaceChildren(opcion('', texto));
        entidad.textContent = '—';
        municipio.textContent = '—';
    };

    const buscar = async (codigo, conservar) => {
        ultimo = codigo;
        avisar('');
        colonias.replaceChildren(opcion('', 'Buscando…'));

        try {
            const respuesta = await fetch(url.replace('00000', codigo), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!respuesta.ok) {
                throw new Error(String(respuesta.status));
            }
            const grupos = await respuesta.json();
            if (codigo !== ultimo) {
                return; // llegó la respuesta de un código que ya se cambió
            }

            if (grupos.length === 0) {
                limpiar('Primero escribe el código postal');
                avisar(`No encontramos el código postal ${codigo}. Revisa que esté bien escrito.`);
                return;
            }

            entidad.textContent = [...new Set(grupos.map((g) => g.entidad))].join(' / ');
            municipio.textContent = grupos.map((g) => g.municipio).join(' / ');

            // Si el código postal abarca más de un municipio, sus colonias se agrupan por municipio
            const lista = [opcion('', 'Elige la colonia')];
            grupos.forEach((grupo) => {
                const opciones = grupo.colonias.map((colonia) => opcion(colonia.id, colonia.nombre, String(colonia.id) === conservar));
                if (grupos.length > 1) {
                    const conjunto = document.createElement('optgroup');
                    conjunto.label = grupo.municipio;
                    conjunto.append(...opciones);
                    lista.push(conjunto);
                } else {
                    lista.push(...opciones);
                }
            });
            colonias.replaceChildren(...lista);

            // Una sola colonia: se elige sola
            if (!conservar && grupos.length === 1 && grupos[0].colonias.length === 1) {
                colonias.value = String(grupos[0].colonias[0].id);
            }

            if (zona && !zonaManual) {
                zona.value = String(grupos[0].zona_id);
            }
        } catch {
            limpiar('Primero escribe el código postal');
            avisar('No se pudo consultar el código postal. Inténtalo de nuevo.');
        }

        colonias.dispatchEvent(new Event('change', { bubbles: true }));
        zona?.dispatchEvent(new Event('input', { bubbles: true }));
    };

    cp.addEventListener('input', () => {
        cp.value = cp.value.replace(/\D/g, '').slice(0, 5);
        if (cp.value.length === 5 && cp.value !== ultimo) {
            buscar(cp.value, '');
        } else if (cp.value.length < 5) {
            ultimo = '';
            limpiar('Primero escribe el código postal');
            avisar('');
        }
    });

    // Al editar: se carga la lista completa de colonias, conservando la elegida
    if (/^\d{5}$/.test(cp.value)) {
        zonaManual = true; // la zona guardada se respeta
        buscar(cp.value, colonias.value);
    }
});

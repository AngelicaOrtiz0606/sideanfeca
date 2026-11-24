/*
 * Formulario de una persona ([data-persona]):
 * - Pertenencia: según la opción (institución, organización o ninguna) se
 *   muestra el buscador o la lista que aplica; lo oculto se desactiva para
 *   que no se envíe ni se valide.
 * - CURP: se escribe en mayúsculas; si su letra de sexo (H o M, posición 11)
 *   no coincide con el género, avisa en amarillo sin bloquear.
 * - Grados: la abreviatura cambia con el género (Mtro. / Mtra.).
 * - Contactos: el correo y el teléfono obligatorios son campos fijos; los
 *   otros contactos son filas que se agregan (desde la plantilla) y se quitan;
 *   según el tipo aparecen la lada y la extensión, y el campo pide número o
 *   correo. En los números solo quedan dígitos ("5622 8300" → "56228300").
 * - Las designaciones nuevas las maneja el componente Vue FilasDesignacion
 *   (resources/js/components). Al editar, en las guardadas, la papelera la
 *   marca para borrar: muestra el motivo, desactiva sus fechas y se deshace
 *   con el mismo botón.
 * - Grados: se agregan desde una lista (el grado elegido sale de la lista) y
 *   cada uno se quita con su ×.
 * - ¿Ya existe? (al registrar): al dejar de escribir el nombre o la CURP,
 *   pregunta al servidor quién se parece y lo muestra con un enlace (a su
 *   ficha, o a registrarle sus designaciones si solo tenía cuenta).
 */
const mostrar = (bloque, visible) => {
    if (!bloque) {
        return;
    }
    bloque.hidden = !visible;
    bloque.querySelectorAll('input, select').forEach((campo) => { campo.disabled = !visible; });
};

const avisarCambio = (formulario) => formulario.dispatchEvent(new Event('change', { bubbles: true }));

document.querySelectorAll('form[data-persona]').forEach((formulario) => {
    // ---- Pertenencia ----
    const pertenencia = () => {
        const tipo = formulario.querySelector('[data-pertenencia-tipo]:checked')?.value ?? '';
        formulario.querySelectorAll('[data-solo-pertenencia]').forEach((bloque) => mostrar(bloque, bloque.dataset.soloPertenencia === tipo));
        avisarCambio(formulario);
    };
    formulario.querySelectorAll('[data-pertenencia-tipo]').forEach((radio) => radio.addEventListener('change', pertenencia));
    pertenencia();

    // ---- CURP y género ----
    const curp = formulario.querySelector('[data-curp]');
    const aviso = formulario.querySelector('[data-curp-aviso]');
    const genero = () => formulario.querySelector('[data-genero]:checked')?.value ?? '';

    const revisar = () => {
        const sexo = /^[A-Z]{4}[0-9]{6}[HM]/.test(curp.value) ? curp.value[10] : '';
        const esperado = { F: 'M', M: 'H' }[genero()];
        const distinto = sexo !== '' && esperado !== undefined && sexo !== esperado;
        aviso.textContent = distinto
            ? `La CURP indica ${sexo === 'H' ? 'hombre (H)' : 'mujer (M)'}, pero el género elegido es ${genero() === 'F' ? 'femenino' : 'masculino'}. Revisa que sea correcto.`
            : '';
        aviso.hidden = !distinto;

        formulario.querySelectorAll('[data-abreviatura]').forEach((abreviatura) => {
            abreviatura.textContent = genero() === 'F' ? abreviatura.dataset.f : abreviatura.dataset.m;
        });
    };

    curp.addEventListener('input', () => {
        const posicion = curp.selectionStart;
        curp.value = curp.value.toUpperCase().replace(/\s/g, '');
        curp.setSelectionRange(posicion, posicion);
        revisar();
    });
    formulario.querySelectorAll('[data-genero]').forEach((radio) => radio.addEventListener('change', revisar));
    revisar();

    // ---- Grados ----
    const agregarGrado = formulario.querySelector('[data-grado-agregar]');
    if (agregarGrado) {
        const listaGrados = formulario.querySelector('[data-grados-lista]');
        const sinGrados = formulario.querySelector('[data-grados-vacio]');
        const plantillaGrado = formulario.querySelector('[data-grado-plantilla]');
        const contar = () => { sinGrados.hidden = listaGrados.children.length > 0; avisarCambio(formulario); };
        const opcionDe = (id) => agregarGrado.querySelector(`option[value="${id}"]`);

        listaGrados.addEventListener('click', (evento) => {
            const quitar = evento.target.closest('[data-grado-quitar]');
            if (!quitar) {
                return;
            }
            const elemento = quitar.closest('[data-grado]');
            opcionDe(elemento.dataset.grado).disabled = false;
            elemento.remove();
            contar();
            agregarGrado.focus();
        });

        agregarGrado.addEventListener('change', () => {
            const opcion = agregarGrado.selectedOptions[0];
            if (!opcion?.value) {
                return;
            }
            // Se arma con el DOM (textos y atributos), nunca con HTML: así nada del catálogo se interpreta
            const nuevo = plantillaGrado.content.firstElementChild.cloneNode(true);
            nuevo.dataset.grado = opcion.value;
            nuevo.querySelector('input').value = opcion.value;
            nuevo.querySelector('.grado-elegido__nombre').textContent = opcion.dataset.nombre;
            const abreviatura = nuevo.querySelector('[data-abreviatura]');
            abreviatura.dataset.m = opcion.dataset.m;
            abreviatura.dataset.f = opcion.dataset.f;
            nuevo.querySelector('.solo-lectores').textContent = `Quitar ${opcion.dataset.nombre}`;
            listaGrados.append(nuevo);
            opcion.disabled = true;
            agregarGrado.value = '';
            revisar();
            contar();
        });
    }

    // ---- Designaciones guardadas (al editar): la papelera la marca para borrar (pide el motivo) y se puede deshacer ----
    formulario.querySelectorAll('[data-designacion-actual]').forEach((fila) => {
        const borrar = fila.querySelector('[data-designacion-borrar]');
        const papelera = fila.querySelector('[data-designacion-papelera]');
        const texto = papelera.querySelector('[data-designacion-papelera-texto]');
        const nombre = texto.querySelector('.solo-lectores').outerHTML;
        const ajustar = () => {
            const marcada = borrar.value === '1';
            mostrar(fila.querySelector('[data-designacion-motivo]'), marcada);
            fila.querySelectorAll('[data-designacion-fecha]').forEach((fecha) => { fecha.disabled = marcada; });
            fila.classList.toggle('designacion-actual--borrar', marcada);
            papelera.setAttribute('aria-pressed', String(marcada));
            papelera.querySelector('[data-si-no-borrar]').hidden = marcada;
            papelera.querySelector('[data-si-borrar]').hidden = !marcada;
            texto.innerHTML = `${marcada ? 'Deshacer: no borrar' : 'Borrar'} ${nombre}`;
            avisarCambio(formulario);
        };
        papelera.addEventListener('click', () => {
            borrar.value = borrar.value === '1' ? '0' : '1';
            ajustar();
            (borrar.value === '1' ? fila.querySelector('[data-designacion-motivo] select') : papelera).focus();
        });
        ajustar();
    });

    // ---- ¿Ya existe? (al registrar) ----
    const coincidencias = formulario.querySelector('[data-coincidencias]');
    if (coincidencias) {
        const lista = coincidencias.querySelector('[data-coincidencias-lista]');
        const campos = ['nombre', 'primer_apellido', 'segundo_apellido'].map((nombre) => formulario.querySelector(`[name="${nombre}"]`));
        let espera;
        let ultima = '';

        const buscar = async () => {
            const nombre = campos.map((campo) => campo.value.trim()).filter(Boolean).join(' ');
            const consulta = new URLSearchParams({ nombre, curp: curp.value });
            if (consulta.toString() === ultima) {
                return;
            }
            ultima = consulta.toString();
            try {
                const respuesta = await fetch(`${coincidencias.dataset.coincidencias}?${consulta}`, { headers: { Accept: 'application/json' } });
                const personas = respuesta.ok ? await respuesta.json() : [];
                lista.replaceChildren(...personas.map((persona) => {
                    const fila = document.createElement('li');
                    fila.className = 'coincidencias__persona';
                    const nombreTexto = document.createElement('strong');
                    nombreTexto.textContent = persona.nombre;
                    const detalle = document.createElement('span');
                    detalle.className = 'coincidencias__detalle';
                    detalle.textContent = [
                        persona.misma_curp ? 'Misma CURP' : null,
                        persona.en_directorio ? 'Ya está en Personas' : 'Solo tiene cuenta de usuario',
                    ].filter(Boolean).join(' · ');
                    const enlace = document.createElement('a');
                    enlace.href = persona.enlace;
                    enlace.className = 'boton boton--secundario';
                    enlace.textContent = persona.en_directorio ? 'Ver su ficha' : 'Usar sus datos';
                    fila.append(nombreTexto, detalle, enlace);

                    return fila;
                }));
                coincidencias.hidden = personas.length === 0;
            } catch {
                coincidencias.hidden = true;
            }
        };
        const programar = () => {
            clearTimeout(espera);
            espera = setTimeout(buscar, 400);
        };
        [...campos, curp].forEach((campo) => campo.addEventListener('input', programar));
    }

    // ---- Contactos ----
    formulario.querySelectorAll('[data-solo-digitos]').forEach((campo) => campo.addEventListener('input', () => {
        campo.value = campo.value.replace(/\D/g, '').slice(0, campo.maxLength > 0 ? campo.maxLength : undefined);
    }));

    const contenedor = formulario.querySelector('[data-contactos]');
    const lista = contenedor.querySelector('[data-contactos-lista]');
    const plantilla = contenedor.querySelector('[data-contacto-plantilla]');
    const agregar = contenedor.querySelector('[data-contacto-agregar]');
    const maximo = Number(contenedor.dataset.maximo);
    let siguiente = lista.querySelectorAll('[data-contacto]').length;

    const prepararFila = (fila) => {
        const tipo = fila.querySelector('[data-contacto-tipo]');
        const valor = fila.querySelector('[data-contacto-valor]');
        const etiqueta = fila.querySelector('[data-contacto-etiqueta]');

        const esCorreo = () => tipo.selectedOptions[0]?.dataset.correo === 'si';

        const ajustar = () => {
            const opcion = tipo.selectedOptions[0];
            mostrar(fila.querySelector('[data-solo-lada]'), opcion?.dataset.lada === 'si');
            mostrar(fila.querySelector('[data-solo-extension]'), opcion?.dataset.extension === 'si');
            fila.classList.toggle('fila-contacto--correo', esCorreo());
            etiqueta.textContent = esCorreo() ? 'Correo' : 'Número';
            valor.type = esCorreo() ? 'email' : 'text';
            valor.inputMode = esCorreo() ? 'email' : 'numeric';
            if (esCorreo()) {
                valor.removeAttribute('pattern');
                valor.maxLength = 120;
                valor.dataset.mensajeFormato = 'Escribe un correo válido, por ejemplo nombre@dominio.mx.';
            } else {
                valor.pattern = '[0-9]{7,10}';
                valor.maxLength = 10;
                valor.dataset.mensajeFormato = 'El número lleva de 7 a 10 dígitos (sin la lada).';
            }
            avisarCambio(formulario);
        };

        const soloDigitos = (campo, siempre) => campo?.addEventListener('input', () => {
            if (siempre || !esCorreo()) {
                campo.value = campo.value.replace(/\D/g, '').slice(0, campo.maxLength > 0 ? campo.maxLength : undefined);
            }
        });
        soloDigitos(valor, false);
        soloDigitos(fila.querySelector('[name$="[lada]"]'), true);
        soloDigitos(fila.querySelector('[name$="[extension]"]'), true);

        tipo.addEventListener('change', ajustar);
        fila.querySelector('[data-contacto-quitar]').addEventListener('click', () => {
            // El foco pasa a la fila siguiente (o a "Agregar contacto"), para no perderse
            const siguienteFila = fila.nextElementSibling ?? fila.previousElementSibling;
            fila.remove();
            actualizarLista();
            (siguienteFila?.querySelector('[data-contacto-tipo]') ?? agregar).focus();
        });
        ajustar();
    };

    const actualizarLista = () => {
        const filas = [...lista.querySelectorAll('[data-contacto]')];
        const cuantos = filas.length;
        // Cada fila se nombra por su lugar ("Contacto 2"), para los lectores de pantalla
        filas.forEach((fila, indice) => {
            fila.setAttribute('aria-label', `Contacto ${indice + 1}`);
            fila.querySelector('[data-contacto-quitar] .accion__texto').textContent = `Quitar contacto ${indice + 1}`;
        });
        agregar.disabled = cuantos >= maximo;
        avisarCambio(formulario);
    };

    agregar.addEventListener('click', () => {
        const fila = plantilla.content.firstElementChild.cloneNode(true);
        // Cada fila nueva lleva su propio número en los nombres y los id
        fila.querySelectorAll('[name], [id], [for]').forEach((elemento) => {
            ['name', 'id', 'for'].forEach((atributo) => {
                if (elemento.hasAttribute(atributo)) {
                    elemento.setAttribute(atributo, elemento.getAttribute(atributo).replaceAll('__i__', String(siguiente)));
                }
            });
        });
        siguiente += 1;
        lista.append(fila);
        prepararFila(fila);
        actualizarLista();
        fila.querySelector('[data-contacto-tipo]').focus();
    });

    lista.querySelectorAll('[data-contacto]').forEach(prepararFila);
    actualizarLista();
});

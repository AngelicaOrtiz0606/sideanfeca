/*
 * Formulario de una cuenta de usuario ([data-usuario]):
 * - CURP: se escribe en mayúsculas y sin espacios; si su letra de sexo (H o
 *   M, posición 11) no coincide con el género, avisa en amarillo sin bloquear.
 * - ¿Ya está registrada? (al crear): al dejar de escribir el nombre o la CURP,
 *   pregunta al servidor quién se parece y lo muestra con su enlace (a su
 *   cuenta, o a crearle una con sus datos). El texto lo arma el servidor.
 */
document.querySelectorAll('form[data-usuario]').forEach((formulario) => {
    const curp = formulario.querySelector('[data-curp]');
    const aviso = formulario.querySelector('[data-curp-aviso]');
    const genero = () => formulario.querySelector('[data-genero]:checked')?.value ?? '';

    const revisarCurp = () => {
        const sexo = /^[A-Z]{4}[0-9]{6}[HM]/.test(curp.value) ? curp.value[10] : '';
        const esperado = { F: 'M', M: 'H' }[genero()];
        const distinto = sexo !== '' && esperado !== undefined && sexo !== esperado;
        aviso.hidden = !distinto;
        aviso.textContent = distinto
            ? `La CURP indica ${sexo === 'H' ? 'hombre (H)' : 'mujer (M)'}, pero el género elegido es ${genero() === 'F' ? 'femenino' : 'masculino'}. Revisa que sea correcto.`
            : '';
    };

    curp.addEventListener('input', () => {
        const posicion = curp.selectionStart;
        curp.value = curp.value.toUpperCase().replace(/\s/g, '');
        curp.setSelectionRange(posicion, posicion);
        revisarCurp();
    });
    formulario.querySelectorAll('[data-genero]').forEach((radio) => radio.addEventListener('change', revisarCurp));
    revisarCurp();

    const coincidencias = formulario.querySelector('[data-coincidencias]');
    if (!coincidencias) {
        return;
    }

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
                detalle.textContent = persona.detalle;
                const enlace = document.createElement('a');
                enlace.href = persona.enlace;
                enlace.className = 'boton boton--secundario';
                enlace.textContent = persona.accion;
                fila.append(nombreTexto, detalle, enlace);

                return fila;
            }));
            coincidencias.hidden = personas.length === 0;
        } catch {
            coincidencias.hidden = true;
        }
    };

    [...campos, curp].forEach((campo) => campo.addEventListener('input', () => {
        clearTimeout(espera);
        espera = setTimeout(buscar, 400);
    }));
});

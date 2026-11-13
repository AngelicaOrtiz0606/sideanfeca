/*
 * Formulario de institución ([data-institucion]): muestra solo lo que aplica.
 * - Al registrar se avanza paso a paso: tipo → participación → lo demás.
 * - Universidad padre: solo para facultad o campus.
 * - Matriz: solo para universidad (si no, se desactiva esa opción).
 * - Número de afiliación: solo para afiliada.
 * - Domicilio y zona: obligatorios, salvo para una matriz.
 * Los campos ocultos se desactivan, así no se envían ni se validan.
 * Lo usan el registro y la ventana "Identificación" de la consulta.
 */
const formulario = document.querySelector('form[data-institucion]');

if (formulario) {
    const tipos = [...formulario.querySelectorAll('input[data-tipo]')];
    const participaciones = [...formulario.querySelectorAll('input[data-participacion]')];
    const fija = formulario.querySelector('[data-participacion-fija]');
    const domicilio = formulario.querySelector('[data-domicilio]');

    const mostrar = (selector, visible) => {
        formulario.querySelectorAll(selector).forEach((bloque) => {
            bloque.hidden = !visible;
            bloque.querySelectorAll('input, select').forEach((campo) => { campo.disabled = !visible; });
        });
    };

    const actualizar = () => {
        const tipo = tipos.find((r) => r.checked)?.value ?? '';
        const matrizPosible = tipo === '' || tipo === 'Universidad';

        const matriz = participaciones.find((r) => r.value === 'Matriz');
        if (matriz) {
            matriz.disabled = !matrizPosible;
            if (!matrizPosible && matriz.checked) {
                matriz.checked = false;
            }
            const aviso = formulario.querySelector('[data-aviso-matriz]');
            if (aviso) {
                aviso.hidden = matrizPosible;
            }
        }

        const participacion = fija?.value ?? participaciones.find((r) => r.checked)?.value ?? '';

        // Paso a paso (solo al registrar; al editar la participación ya está fija)
        if (!fija) {
            const conParticipacion = tipo !== '' && participacion !== '';
            formulario.querySelectorAll('[data-paso-participacion]').forEach((bloque) => { bloque.hidden = tipo === ''; });
            formulario.querySelectorAll('[data-paso-resto]').forEach((bloque) => { bloque.hidden = !conParticipacion; });
            const guia = formulario.querySelector('[data-guia]');
            if (guia) {
                guia.hidden = conParticipacion;
                guia.textContent = tipo === '' ? 'Elige el tipo de institución para continuar.' : 'Ahora elige su participación para continuar.';
            }
        }

        mostrar('[data-solo-con-universidad]', tipo === 'Facultad' || tipo === 'Campus');
        mostrar('[data-solo-afiliada]', participacion === 'Afiliada');

        if (domicilio) {
            const obligatorio = participacion !== 'Matriz';
            domicilio.querySelectorAll('[name="codigo_postal"], [name="colonia"], [name="calle"], [name="num_ext"], [name="zona"]').forEach((campo) => {
                campo.required = obligatorio;
                campo.closest('.campo')?.querySelectorAll('.campo__obligatorio, .campo__obligatorio + .solo-lectores').forEach((marca) => { marca.hidden = !obligatorio; });
            });
            domicilio.querySelector('[data-domicilio-opcional]').hidden = obligatorio;
        }

        formulario.dispatchEvent(new Event('change', { bubbles: true }));
    };

    [...tipos, ...participaciones].forEach((radio) => radio.addEventListener('change', actualizar));
    // En una ventana que vuelve a sus datos guardados (reset), se ajusta de nuevo
    formulario.addEventListener('reset', () => setTimeout(actualizar));
    actualizar();
}

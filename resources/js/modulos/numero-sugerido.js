/*
 * Número de afiliación de una afiliada nueva ([data-numero-afiliacion],
 * componente x-numero-afiliacion).
 *
 * El número se forma con el año en que empieza a participar (2 dígitos), la
 * zona regional (2 dígitos) y el consecutivo de ese año y zona (3 dígitos):
 *   2607003 = año 2026 · zona 07 · consecutivo 003
 * Cada año y zona lleva su propia cuenta: el siguiente al más alto que ya
 * existe (de data-existentes). Aquí solo se muestra la vista previa; el
 * servidor lo asigna al guardar, con candado, para que nunca se repita.
 *
 * "Escribir el número" cambia a captura manual (numero_modo=manual): se
 * avisa en amarillo si no empieza con el año y la zona, pero se permite.
 *
 *   data-fecha="#desde"      campo de la fecha
 *   data-zona="#zona"        lista de zonas (cada opción con data-numero)
 *   data-zona-numero="7"     o la zona fija (al pasar a afiliada)
 */
document.querySelectorAll('[data-numero-afiliacion]').forEach((bloque) => {
    const formulario = bloque.closest('form');
    const fecha = formulario.querySelector(bloque.dataset.fecha);
    const zona = bloque.dataset.zona ? formulario.querySelector(bloque.dataset.zona) : null;
    const modo = bloque.querySelector('[data-numero-modo]');
    const automatico = bloque.querySelector('[data-numero-automatico]');
    const manual = bloque.querySelector('[data-numero-manual]');
    const campo = manual.querySelector('input[name="numero"]');
    const vista = bloque.querySelector('[data-numero-vista]');
    const ayuda = bloque.querySelector('[data-numero-ayuda]');
    const aviso = bloque.querySelector('[data-numero-aviso]');
    const existentes = JSON.parse(campo.dataset.existentes ?? '[]').map(([, numero]) => String(numero));

    const prefijo = () => {
        const anio = /^\d{4}-/.test(fecha?.value ?? '') ? fecha.value.slice(2, 4) : null;
        const numeroZona = bloque.dataset.zonaNumero
            ? Number(bloque.dataset.zonaNumero)
            : Number(zona?.selectedOptions[0]?.dataset.numero ?? NaN);

        return anio === null || Number.isNaN(numeroZona) || numeroZona > 99
            ? null
            : anio + String(numeroZona).padStart(2, '0');
    };

    const actualizar = () => {
        const esManual = modo.value === 'manual';
        const oculto = bloque.hidden || bloque.closest('[hidden]') !== null;

        automatico.hidden = esManual;
        manual.hidden = !esManual;
        campo.disabled = !esManual || oculto;
        bloque.querySelectorAll('[data-solo-manual]').forEach((marca) => { marca.hidden = !esManual; });

        const inicio = prefijo();

        // Vista previa del número automático
        if (inicio === null) {
            vista.textContent = '—';
            ayuda.textContent = 'Se asigna al guardar, con el año y la zona regional.';
        } else {
            const usados = existentes.filter((n) => n.startsWith(inicio)).map((n) => Number(n.slice(4)));
            const siguiente = (usados.length ? Math.max(...usados) : 0) + 1;

            if (siguiente > 999) {
                vista.textContent = '—';
                ayuda.textContent = `Ya no hay consecutivos para el año ${inicio.slice(0, 2)} y la zona ${inicio.slice(2)}. Escribe el número.`;
            } else {
                const numero = inicio + String(siguiente).padStart(3, '0');
                vista.textContent = numero;
                ayuda.textContent = `Se asigna al guardar: ${inicio.slice(0, 2)} (año) · ${inicio.slice(2)} (zona) · ${numero.slice(4)} (consecutivo).`;
            }
        }

        // Aviso (amarillo) si el número escrito no sigue el año y la zona
        const escrito = campo.value.trim();
        const fuera = esManual && inicio !== null && /^\d{7}$/.test(escrito) && !escrito.startsWith(inicio);
        aviso.textContent = fuera ? `No empieza con ${inicio} (año y zona). Se guardará así, revisa que sea correcto.` : '';
        aviso.hidden = !fuera;
    };

    const cambiarModo = (nuevo, enfocar = true) => {
        modo.value = nuevo;
        if (nuevo === 'automatico') {
            campo.value = '';
        }
        actualizar();
        formulario.dispatchEvent(new Event('change', { bubbles: true }));
        if (enfocar) {
            (nuevo === 'manual' ? campo : bloque.querySelector('[data-numero-escribir]'))?.focus();
        }
    };

    bloque.querySelector('[data-numero-escribir]').addEventListener('click', () => cambiarModo('manual'));
    bloque.querySelector('[data-numero-usar-automatico]').addEventListener('click', () => cambiarModo('automatico'));

    ['input', 'change'].forEach((tipo) => {
        fecha?.addEventListener(tipo, actualizar);
        zona?.addEventListener(tipo, actualizar); // la zona también cambia sola con el código postal
    });
    campo.addEventListener('input', actualizar);
    // Al mostrarse u ocultarse el bloque (por ejemplo, al elegir afiliada) se ajusta
    formulario.addEventListener('change', (evento) => { if (evento.target === formulario) actualizar(); });
    formulario.addEventListener('reset', () => setTimeout(() => cambiarModo('automatico', false)));
    actualizar();
});

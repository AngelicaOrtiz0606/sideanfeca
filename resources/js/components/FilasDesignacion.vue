<!--
    Filas de designación (Nueva persona y "Agregar designación" de la ficha).
    Cada fila: primero el nivel del cargo (la lista de cargos muestra solo los
    de ese nivel), luego el cargo, desde cuándo, hasta cuándo (opcional, para
    registrar las que ya terminaron) y, según el nivel, la zona y la
    coordinación. Se agregan y se quitan; quedan al menos "minimo" filas
    (1 al registrar a una persona; 0 al editarla, donde son opcionales).

    Los campos se envían como designaciones[0][cargo], designaciones[0][zona]…
    con el formulario normal: el servidor valida todo (ValidaDesignaciones).
    Lo que no aplica al nivel no se dibuja, así no se envía ni se valida.

    Un cargo de la institución se puede marcar como titular de la institución.
    Al marcarlo se pregunta al servidor quién es el titular hoy: si hay alguien,
    se dice qué pasa con él y se pide confirmar el cambio. La institución es la
    de la persona: fija (prop "institucion") o la que se elige en el formulario.
-->
<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    cargos: { type: Array, required: true }, // [{ id, nombre, nivel }]
    zonas: { type: Array, required: true }, // [{ id, texto }]
    coordinaciones: { type: Array, required: true }, // [{ id, nombre }]
    iniciales: { type: Array, default: () => [] }, // filas escritas antes (si hubo errores)
    errores: { type: Object, default: () => ({}) }, // { 'designaciones.0.cargo': true }
    maximo: { type: Number, default: 10 },
    hoy: { type: String, required: true },
    notaInstitucional: { type: String, default: 'Es un cargo de la institución a la que pertenece.' },
    minimo: { type: Number, default: 1 },
    institucion: { type: Number, default: null }, // la de la persona, si ya está fija
    urlTitular: { type: String, default: '' }, // dirección con __id__ en lugar del número de institución
});

const NIVELES = [
    { valor: 'Nacional', texto: 'Nacional' },
    { valor: 'Regional', texto: 'Regional' },
    { valor: 'Institucional', texto: 'De una institución' },
];

let siguienteClave = 0;
const nuevaFila = (datos = {}) => ({
    clave: siguienteClave++,
    nivel: datos.nivel ?? props.cargos.find((c) => String(c.id) === String(datos.cargo))?.nivel ?? '',
    cargo: datos.cargo ? String(datos.cargo) : '',
    zona: datos.zona ? String(datos.zona) : '',
    coordinacion: datos.coordinacion ? String(datos.coordinacion) : '',
    fecha_inicio: datos.fecha_inicio ?? props.hoy,
    fecha_fin: datos.fecha_fin ?? '',
    titular: datos.titular === '1' || datos.titular === true,
    reemplazar: datos.reemplazar === '1' || datos.reemplazar === true,
});

const filas = ref(props.iniciales.length > 0 ? props.iniciales.map(nuevaFila) : Array.from({ length: props.minimo }, () => nuevaFila()));
const raiz = ref(null);

const cargosDe = (nivel) => props.cargos.filter((c) => c.nivel === nivel);
const conError = (i, campo) => props.errores[`designaciones.${i}.${campo}`] === true;
const puedeAgregar = computed(() => filas.value.length < props.maximo);

// El formulario vuelve a revisar sus campos (validacion-formularios.js) cuando cambian las filas
const avisarCambio = () => nextTick(() => raiz.value?.closest('form')?.dispatchEvent(new Event('change', { bubbles: true })));

const cambiarNivel = (fila) => {
    // Un cargo de otro nivel no se queda elegido
    if (!cargosDe(fila.nivel).some((c) => String(c.id) === fila.cargo)) {
        fila.cargo = '';
    }
    avisarCambio();
};

const agregar = async () => {
    filas.value.push(nuevaFila());
    avisarCambio();
    await nextTick();
    raiz.value.querySelector('[data-fila]:last-of-type input[type="radio"]')?.focus();
};

// ---- Titular de la institución ----
const institucionActual = ref(props.institucion);
const titulares = reactive({}); // número de institución → { cargo_id, cargo, ocupante, desde } | null | 'cargando'
const titularDe = computed(() => titulares[institucionActual.value]);

onMounted(() => {
    if (props.institucion !== null) {
        return;
    }
    // Sin institución fija: la que esté elegida en "Pertenece a" (si pertenece a una institución)
    const formulario = raiz.value?.closest('form');
    const leer = () => {
        const tipo = formulario?.querySelector('input[name="pertenencia"]:checked')?.value;
        const campo = formulario?.querySelector('input[name="institucion"]');
        institucionActual.value = tipo === 'institucion' && campo && !campo.disabled && campo.value ? Number(campo.value) : null;
    };
    formulario?.addEventListener('change', leer);
    leer();
});

const pedirTitular = async (institucion) => {
    if (!props.urlTitular || institucion in titulares) {
        return;
    }
    titulares[institucion] = 'cargando';
    try {
        const respuesta = await fetch(props.urlTitular.replace('__id__', institucion), { headers: { Accept: 'application/json' } });
        titulares[institucion] = respuesta.ok ? await respuesta.json() : null;
    } catch {
        delete titulares[institucion];
    }
};

watch([institucionActual, () => filas.value.some((f) => f.nivel === 'Institucional' && f.titular)], ([institucion, alguno]) => {
    if (institucion && alguno) {
        pedirTitular(institucion);
    }
}, { immediate: true });

const quitar = async (indice) => {
    filas.value.splice(indice, 1);
    avisarCambio();
    await nextTick();
    // El foco pasa a la fila que quedó en su lugar (o a la anterior)
    const restantes = raiz.value.querySelectorAll('[data-fila]');
    restantes[Math.min(indice, restantes.length - 1)]?.querySelector('input[type="radio"]')?.focus();
};
</script>

<template>
    <div ref="raiz" class="filas-designacion">
        <div v-for="(fila, i) in filas" :key="fila.clave" class="fila-designacion" data-fila role="group" :aria-label="`Designación ${i + 1}`">
            <div class="fila-designacion__cabecera">
                <span class="fila-designacion__titulo">Designación {{ i + 1 }}</span>
                <button v-if="filas.length > minimo" type="button" class="accion accion--borrar" @click="quitar(i)">
                    <svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M3 6h18" /><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" /><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                    </svg>
                    <span class="accion__texto">Quitar designación {{ i + 1 }}</span>
                </button>
            </div>

            <fieldset class="campo">
                <legend class="campo__etiqueta">Nivel del cargo <span class="campo__obligatorio" aria-hidden="true">*</span></legend>
                <div class="segmentado">
                    <label v-for="nivel in NIVELES" :key="nivel.valor" class="segmentado__opcion">
                        <input v-model="fila.nivel" type="radio" :name="`designaciones[${i}][nivel]`" :value="nivel.valor" required @change="cambiarNivel(fila)">
                        {{ nivel.texto }}
                    </label>
                </div>
            </fieldset>

            <div class="formulario__rejilla formulario__rejilla--cargo">
                <div class="campo campo--mitad">
                    <label :for="`designacion-${fila.clave}-cargo`" class="campo__etiqueta">Cargo <span class="campo__obligatorio" aria-hidden="true">*</span></label>
                    <select :id="`designacion-${fila.clave}-cargo`" v-model="fila.cargo" :name="`designaciones[${i}][cargo]`" class="campo__entrada" required
                            :disabled="fila.nivel === ''" :aria-invalid="conError(i, 'cargo') ? 'true' : null">
                        <option value="">{{ fila.nivel === '' ? 'Primero elige el nivel' : 'Elige el cargo' }}</option>
                        <option v-for="cargo in cargosDe(fila.nivel)" :key="cargo.id" :value="String(cargo.id)">{{ cargo.nombre }}</option>
                    </select>
                </div>
                <div class="campo campo--cuarto">
                    <label :for="`designacion-${fila.clave}-desde`" class="campo__etiqueta">Desde <span class="campo__obligatorio" aria-hidden="true">*</span></label>
                    <input :id="`designacion-${fila.clave}-desde`" v-model="fila.fecha_inicio" :name="`designaciones[${i}][fecha_inicio]`" type="date" required
                           class="campo__entrada" min="1950-01-01" :max="hoy" :aria-invalid="conError(i, 'fecha_inicio') ? 'true' : null">
                </div>
                <div class="campo campo--cuarto">
                    <label :for="`designacion-${fila.clave}-hasta`" class="campo__etiqueta">Hasta <span class="campo__opcional">(opcional)</span></label>
                    <input :id="`designacion-${fila.clave}-hasta`" v-model="fila.fecha_fin" :name="`designaciones[${i}][fecha_fin]`" type="date"
                           class="campo__entrada" :min="fila.fecha_inicio || '1950-01-01'" :aria-describedby="`designacion-${fila.clave}-hasta-ayuda`"
                           :aria-invalid="conError(i, 'fecha_fin') ? 'true' : null">
                    <p :id="`designacion-${fila.clave}-hasta-ayuda`" class="campo__ayuda">Su último día. Vacío si sigue en el puesto.</p>
                </div>

                <div v-if="fila.nivel === 'Regional'" class="campo campo--mitad">
                    <label :for="`designacion-${fila.clave}-zona`" class="campo__etiqueta">Zona regional <span class="campo__obligatorio" aria-hidden="true">*</span></label>
                    <select :id="`designacion-${fila.clave}-zona`" v-model="fila.zona" :name="`designaciones[${i}][zona]`" class="campo__entrada" required
                            :aria-invalid="conError(i, 'zona') ? 'true' : null">
                        <option value="">Elige la zona</option>
                        <option v-for="zona in zonas" :key="zona.id" :value="String(zona.id)">{{ zona.texto }}</option>
                    </select>
                </div>
                <div v-if="fila.nivel === 'Nacional' || fila.nivel === 'Regional'" class="campo campo--mitad">
                    <label :for="`designacion-${fila.clave}-coordinacion`" class="campo__etiqueta">Coordinación nacional <span class="campo__opcional">(opcional)</span></label>
                    <select :id="`designacion-${fila.clave}-coordinacion`" v-model="fila.coordinacion" :name="`designaciones[${i}][coordinacion]`" class="campo__entrada">
                        <option value="">Ninguna</option>
                        <option v-for="coordinacion in coordinaciones" :key="coordinacion.id" :value="String(coordinacion.id)">{{ coordinacion.nombre }}</option>
                    </select>
                </div>
                <template v-if="fila.nivel === 'Institucional'">
                    <p class="campo--completo campo__ayuda">{{ notaInstitucional }}</p>
                    <div class="campo--completo">
                        <label class="opcion-en-linea opcion-en-linea--casilla">
                            <input v-model="fila.titular" type="checkbox" :name="`designaciones[${i}][titular]`" value="1" @change="avisarCambio">
                            Es el titular de la institución <span class="campo__opcional">(aparece en el directorio de Instituciones)</span>
                        </label>
                        <div v-if="fila.titular" class="aviso aviso--atencion aviso-titular" aria-live="polite">
                            <p v-if="!institucionActual">Primero elige la institución a la que pertenece.</p>
                            <p v-else-if="titularDe === 'cargando'">Revisando quién es el titular…</p>
                            <p v-else-if="!titularDe || !titularDe.ocupante">
                                {{ titularDe ? `El puesto titular (${titularDe.cargo}) está vacante hoy.` : 'La institución todavía no tiene titular.' }}
                                Esta persona será su titular.
                            </p>
                            <template v-else>
                                <p>Hoy el titular es <strong>{{ titularDe.ocupante }}</strong> ({{ titularDe.cargo }}, desde el {{ titularDe.desde }}).</p>
                                <p v-if="String(titularDe.cargo_id) === fila.cargo">Es el mismo puesto: su designación termina el día anterior a la fecha de inicio de esta.</p>
                                <p v-else>Sigue en su cargo, pero ya no como titular: el titular pasa a ser este cargo.</p>
                                <label class="opcion-en-linea opcion-en-linea--casilla">
                                    <input v-model="fila.reemplazar" type="checkbox" :name="`designaciones[${i}][reemplazar]`" value="1" required
                                           :aria-invalid="conError(i, 'reemplazar') ? 'true' : null">
                                    Sí, cambiar el titular
                                </label>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <button type="button" class="boton boton--secundario tabla-contactos__agregar" :disabled="!puedeAgregar" @click="agregar">
            <svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <path d="M5 12h14" /><path d="M12 5v14" />
            </svg>
            {{ filas.length === 0 ? 'Agregar designación' : 'Agregar otra designación' }}
        </button>
    </div>
</template>

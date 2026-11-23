<!--
    Orden de los puestos de un directorio (pantalla Directorios).
    Se reordena arrastrando cada puesto, o con las flechas (teclado y teléfono);
    cada movimiento se anuncia a los lectores de pantalla. "Guardar orden" se
    activa solo si el orden cambió y envía la lista completa (orden[]) y el
    último puesto movido (movido), como pide reordenar_puestos_directorio.
    La papelera de cada puesto envía el formulario "quitar-<id>" que la página
    dibuja aparte (no se anidan formularios).
-->
<script setup>
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    puestos: { type: Array, required: true }, // [{ id, nombre, ocupante, activo }]
    accion: { type: String, required: true },
    token: { type: String, required: true },
});

const lista = ref(props.puestos.map((p) => ({ ...p })));
const inicial = props.puestos.map((p) => p.id).join(',');
const cambio = computed(() => lista.value.map((p) => p.id).join(',') !== inicial);
const movido = ref(null);
const anuncio = ref('');
const arrastrado = ref(null);
const raiz = ref(null);

const mover = async (indice, destino, boton) => {
    if (destino < 0 || destino >= lista.value.length) {
        return;
    }
    const [puesto] = lista.value.splice(indice, 1);
    lista.value.splice(destino, 0, puesto);
    movido.value = puesto.id;
    anuncio.value = `${puesto.nombre}, lugar ${destino + 1} de ${lista.value.length}.`;
    // El foco se queda en el mismo botón del puesto movido (o en el otro, si este ya no se puede usar)
    await nextTick();
    const fila = raiz.value.querySelector(`[data-puesto="${puesto.id}"]`);
    const mismo = fila.querySelector(boton === 'subir' ? '[data-subir]' : '[data-bajar]');
    (mismo.disabled ? fila.querySelector(boton === 'subir' ? '[data-bajar]' : '[data-subir]') : mismo).focus();
};

const soltar = (destino) => {
    if (arrastrado.value === null || arrastrado.value === destino) {
        arrastrado.value = null;
        return;
    }
    const [puesto] = lista.value.splice(arrastrado.value, 1);
    lista.value.splice(destino, 0, puesto);
    movido.value = puesto.id;
    anuncio.value = `${puesto.nombre}, lugar ${destino + 1} de ${lista.value.length}.`;
    arrastrado.value = null;
};
</script>

<template>
    <form ref="raiz" method="POST" :action="accion" class="formulario">
        <input type="hidden" name="_token" :value="token">
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="movido" :value="movido ?? ''">

        <ol class="lista-orden">
            <li v-for="(puesto, i) in lista" :key="puesto.id" class="lista-orden__item" draggable="true" :data-puesto="puesto.id"
                :data-movida="puesto.id === movido ? '' : null" :data-arrastrando="arrastrado === i ? '' : null"
                @dragstart="arrastrado = i" @dragover.prevent @drop.prevent="soltar(i)" @dragend="arrastrado = null">
                <input type="hidden" name="orden[]" :value="puesto.id">
                <span class="lista-orden__asa" aria-hidden="true">
                    <svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <circle cx="9" cy="5" r="1" /><circle cx="9" cy="12" r="1" /><circle cx="9" cy="19" r="1" /><circle cx="15" cy="5" r="1" /><circle cx="15" cy="12" r="1" /><circle cx="15" cy="19" r="1" />
                    </svg>
                </span>
                <span class="lista-orden__lugar">{{ i + 1 }}</span>
                <span class="lista-orden__nombre">
                    <span>{{ puesto.nombre }}</span>
                    <span class="lista-orden__detalle">{{ puesto.ocupante ?? 'Vacante' }}</span>
                    <span v-if="!puesto.activo" class="estado">Desactivado</span>
                </span>
                <span class="lista-orden__botones">
                    <button type="button" class="boton-icono boton-icono--pequeno" data-subir :disabled="i === 0" @click="mover(i, i - 1, 'subir')">
                        <svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m18 15-6-6-6 6" /></svg>
                        <span class="solo-lectores">Subir {{ puesto.nombre }}</span>
                    </button>
                    <button type="button" class="boton-icono boton-icono--pequeno" data-bajar :disabled="i === lista.length - 1" @click="mover(i, i + 1, 'bajar')">
                        <svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6" /></svg>
                        <span class="solo-lectores">Bajar {{ puesto.nombre }}</span>
                    </button>
                    <button type="submit" class="accion accion--borrar accion--a-la-derecha" :form="`quitar-${puesto.id}`">
                        <svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 6h18" /><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" /><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" /></svg>
                        <span class="accion__texto">Quitar del directorio <span class="solo-lectores">{{ puesto.nombre }}</span></span>
                    </button>
                </span>
            </li>
        </ol>

        <p class="solo-lectores" aria-live="polite">{{ anuncio }}</p>

        <div class="orden-directorio__acciones">
            <button type="submit" class="boton boton--primario" :disabled="!cambio">
                <svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10" /><path d="m9 12 2 2 4-4" /></svg>
                Guardar orden
            </button>
        </div>
    </form>
</template>

/*
 * Partes interactivas hechas con Vue. Cada componente se monta solo donde la
 * página tiene su lugar (data-vue="…"); sus datos llegan en data-props (JSON),
 * así no hace falta JavaScript dentro de la página (respeta la CSP).
 *
 *   data-vue="designaciones"   filas de designación (Nueva persona y Agregar designación)
 */
import { createApp } from 'vue';
import FilasDesignacion from './components/FilasDesignacion.vue';

const componentes = {
    designaciones: FilasDesignacion,
};

document.querySelectorAll('[data-vue]').forEach((lugar) => {
    const componente = componentes[lugar.dataset.vue];
    if (componente) {
        createApp(componente, JSON.parse(lugar.dataset.props ?? '{}')).mount(lugar);
    }
});

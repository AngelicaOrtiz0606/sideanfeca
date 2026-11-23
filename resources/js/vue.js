/*
 * Partes interactivas hechas con Vue. Cada componente se monta solo donde la
 * página tiene su lugar (data-vue="…"); sus datos llegan en data-props (JSON),
 * así no hace falta JavaScript dentro de la página (respeta la CSP).
 *
 *   data-vue="designaciones"   filas de designación (Nueva persona, Editar y Agregar designación)
 *   data-vue="orden-puestos"   orden de los puestos de un directorio (pantalla Directorios)
 */
import { createApp } from 'vue';
import FilasDesignacion from './components/FilasDesignacion.vue';
import OrdenPuestos from './components/OrdenPuestos.vue';

const componentes = {
    designaciones: FilasDesignacion,
    'orden-puestos': OrdenPuestos,
};

document.querySelectorAll('[data-vue]').forEach((lugar) => {
    const componente = componentes[lugar.dataset.vue];
    if (componente) {
        createApp(componente, JSON.parse(lugar.dataset.props ?? '{}')).mount(lugar);
    }
});

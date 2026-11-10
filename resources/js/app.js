/*
 * SIDEANFECA: JavaScript principal.
 *
 * Solo reúne los módulos. Cada uno se activa por sí mismo cuando encuentra en
 * la página los elementos que le corresponden (atributos data-*), así que
 * cargarlos todos en todas las pantallas no causa problemas.
 *
 *   menu-movil.js               menú lateral en teléfono y tableta
 *   campo-contrasenia.js        botón Mostrar / Ocultar y aviso de mayúsculas
 *   validacion-formularios.js   validación discreta y botón inactivo
 *   boton-subir.js              botón flotante para subir
 *   contadores.js               números que se cuentan en la pantalla de inicio
 */
import './modulos/menu-movil.js';
import './modulos/campo-contrasenia.js';
import './modulos/validacion-formularios.js';
import './modulos/boton-subir.js';
import './modulos/contadores.js';

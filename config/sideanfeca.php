<?php

/*
 * Configuración propia de SIDEANFECA.
 */
return [

    /*
     * Enlaces legales del footer. El texto de cada enlace es la leyenda
     * oficial y no cambia; aquí solo se configura su dirección.
     * Las direcciones oficiales de la FCA vienen por defecto; se pueden
     * cambiar en .env sin tocar el código. Si una queda vacía, el texto se
     * muestra sin enlace.
     */
    'enlaces_legales' => [
        'aviso_privacidad' => env('SIDEANFECA_URL_AVISO_PRIVACIDAD', 'https://www.fca.unam.mx/docs/aviso_privacidad.pdf'),
        'documento_seguridad' => env('SIDEANFECA_URL_DOCUMENTO_SEGURIDAD', 'https://www.fca.unam.mx/docs/permanentes/seguridad.pdf'),
        'instrumento_juridico' => env('SIDEANFECA_URL_INSTRUMENTO_JURIDICO', 'https://www.fca.unam.mx/docs/permanentes/aws.pdf'),
        'aviso_privacidad_simplificado' => env('SIDEANFECA_URL_AVISO_PRIVACIDAD_SIMPLIFICADO', 'https://www.fca.unam.mx/docs/permanentes/aviso_simplificado.pdf'),
    ],

];

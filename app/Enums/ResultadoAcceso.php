<?php

namespace App\Enums;

/**
 * Resultados que acepta LOG_ACCESO.LOGA_RESULTADO (restricción CKC_LOGA_RESULTADO).
 * El valor de cada caso es el texto exacto que guarda la base.
 */
enum ResultadoAcceso: string
{
    case Exitoso = 'Exitoso';
    case Fallido = 'Fallido';
    case CuentaInactiva = 'Cuenta inactiva';
    case CierreSesion = 'Cierre de sesión';
}

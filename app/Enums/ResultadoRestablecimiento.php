<?php

namespace App\Enums;

/**
 * Resultado de revisar un enlace de recuperación de contraseña.
 */
enum ResultadoRestablecimiento
{
    /** El enlace sirve: se puede mostrar o guardar la contraseña nueva. */
    case Valido;

    /** El enlace venció, ya se usó, no existe o el token no coincide. */
    case NoValido;

    /** Se llegó al máximo de intentos con un token equivocado. */
    case Bloqueado;
}

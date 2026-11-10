<?php

namespace App\Services;

use App\Enums\ResultadoAcceso;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

/**
 * Evalúa un intento de inicio de sesión y lo registra en LOG_ACCESO.
 *
 * - Correo inexistente: Fallido, sin cuenta.
 * - Contraseña incorrecta (activa o inactiva): Fallido, con cuenta.
 * - Contraseña correcta, cuenta inactiva: Cuenta inactiva. No entra.
 * - Contraseña correcta, cuenta activa con persona: Exitoso.
 *
 * Iniciar la sesión (Auth::login) le toca al controlador.
 */
class AutenticacionService
{
    /**
     * Hash bcrypt (costo 12) de un texto que nadie conoce. Se revisa cuando
     * el correo no existe, para que esa respuesta tarde lo mismo que una
     * contraseña incorrecta y no revele qué correos están registrados.
     */
    private const HASH_FALSO = '$2y$12$BBnNuPj4AJ1C9Hs5FNfj.u7v4QKHflDDvo2xP8IntEo7SpAfmSE9S';

    public function __construct(private readonly AccesoService $accesos) {}

    public function intentar(string $correo, string $contrasenia, string $ip): IntentoInicioSesion
    {
        $cuenta = Usuario::porCorreo($correo)->first();

        if ($cuenta === null) {
            Hash::check($contrasenia, self::HASH_FALSO);

            return $this->resultado(null, ResultadoAcceso::Fallido, $ip);
        }

        if (! $this->contraseniaEsCorrecta($contrasenia, $cuenta)) {
            return $this->resultado($cuenta, ResultadoAcceso::Fallido, $ip);
        }

        if (! $cuenta->estaActiva() || $cuenta->usua_id_persona === null) {
            return $this->resultado($cuenta, ResultadoAcceso::CuentaInactiva, $ip);
        }

        $this->accesos->registrar($cuenta->usua_id_usuario, ResultadoAcceso::Exitoso, $ip);

        return new IntentoInicioSesion(ResultadoAcceso::Exitoso, $cuenta);
    }

    /**
     * Las cuentas anonimizadas por ARCO guardan un texto que no es un hash
     * ('!ANONIMIZADA'). Laravel lanzaría un error al revisarlo, así que se
     * tratan como contraseña incorrecta.
     */
    private function contraseniaEsCorrecta(string $contrasenia, Usuario $cuenta): bool
    {
        $hash = (string) $cuenta->getAuthPassword();

        if (Hash::info($hash)['algoName'] === 'unknown') {
            Hash::check($contrasenia, self::HASH_FALSO);

            return false;
        }

        return Hash::check($contrasenia, $hash);
    }

    private function resultado(?Usuario $cuenta, ResultadoAcceso $resultado, string $ip): IntentoInicioSesion
    {
        $this->accesos->registrar($cuenta?->usua_id_usuario, $resultado, $ip);

        return new IntentoInicioSesion($resultado);
    }
}

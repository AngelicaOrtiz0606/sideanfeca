<?php

namespace App\Services;

use App\Enums\ResultadoRestablecimiento;
use App\Mail\EnlaceRecuperacion;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Recuperación de contraseña con un enlace enviado por correo
 * (tabla RECUPERACION_CONTRASENIA).
 *
 * El enlace lleva el número de la solicitud y un token aleatorio de 64 caracteres.
 * - Solo se guarda el hash del token (HMAC-SHA-256, 64 caracteres), nunca el token.
 * - Vence a los 15 minutos, medidos con la hora de la base de datos.
 * - Cada intento con un token equivocado cuenta; al quinto, la solicitud se bloquea.
 * - Un solo uso. Al pedir un enlace nuevo, los anteriores dejan de servir.
 * - Solo las cuentas activas con persona reciben correo.
 * - No se registra en la bitácora (decisión del proyecto).
 */
class RecuperacionService
{
    public const MINUTOS_VIGENCIA = 15;

    public const INTENTOS_MAXIMOS = 5;

    private const LONGITUD_TOKEN = 64;

    /**
     * Envía un enlace si el correo pertenece a una cuenta activa.
     * Si no, no hace nada: quien lo pide siempre ve el mismo mensaje.
     */
    public function solicitar(string $correo): void
    {
        $cuenta = $this->cuentaActiva($correo);

        if ($cuenta === null) {
            return;
        }

        $token = Str::random(self::LONGITUD_TOKEN);

        $idSolicitud = DB::transaction(function () use ($cuenta, $token): int {
            // Los enlaces anteriores sin usar dejan de servir
            DB::table('recuperacion_contrasenia')
                ->where('reco_id_usuario', $cuenta->usua_id_usuario)
                ->whereNull('reco_fecha_uso')
                ->delete();

            return (int) DB::table('recuperacion_contrasenia')->insertGetId([
                'reco_id_usuario' => $cuenta->usua_id_usuario,
                'reco_token_hash' => $this->hash($token),
                'reco_fecha_expira' => DB::raw("now() + interval '".self::MINUTOS_VIGENCIA." minutes'"),
            ], 'reco_id_recuperacion');
        });

        Mail::to($cuenta->usua_correo)->send(new EnlaceRecuperacion(
            $this->enlace($idSolicitud, $token),
            self::MINUTOS_VIGENCIA,
        ));
    }

    /**
     * Arma el enlace del correo SIEMPRE con APP_URL, nunca con la dirección que
     * dice el navegador. Así nadie puede pedir un enlace para otra persona
     * haciendo que apunte a un sitio falso (envenenamiento del enlace).
     */
    private function enlace(int $idSolicitud, string $token): string
    {
        $ruta = route('contrasenia.restablecer', ['solicitud' => $idSolicitud, 'token' => $token], absolute: false);

        return rtrim((string) config('app.url'), '/').$ruta;
    }

    /**
     * Revisa el enlace al abrirlo. Un token equivocado cuenta como intento.
     */
    public function verificar(int $idSolicitud, string $token): ResultadoRestablecimiento
    {
        return DB::transaction(fn (): ResultadoRestablecimiento => $this->revisar($idSolicitud, $token)['resultado']);
    }

    /**
     * Revisa el enlace otra vez y, si sigue sirviendo, guarda la contraseña nueva
     * y marca la solicitud como usada, todo en la misma transacción.
     */
    public function restablecer(int $idSolicitud, string $token, string $contraseniaNueva): ResultadoRestablecimiento
    {
        return DB::transaction(function () use ($idSolicitud, $token, $contraseniaNueva): ResultadoRestablecimiento {
            ['resultado' => $resultado, 'solicitud' => $solicitud] = $this->revisar($idSolicitud, $token);

            if ($resultado !== ResultadoRestablecimiento::Valido) {
                return $resultado;
            }

            DB::table('usuario')
                ->where('usua_id_usuario', $solicitud->reco_id_usuario)
                ->update(['usua_contrasenia' => Hash::make($contraseniaNueva)]);

            DB::table('recuperacion_contrasenia')
                ->where('reco_id_recuperacion', $idSolicitud)
                ->update(['reco_fecha_uso' => DB::raw('now()')]);

            return ResultadoRestablecimiento::Valido;
        });
    }

    /**
     * Borra las solicitudes vencidas o ya usadas con la función SQL
     * limpiar_recuperaciones. Devuelve cuántas borró.
     */
    public function limpiar(): int
    {
        return (int) DB::selectOne('select limpiar_recuperaciones() as borradas')->borradas;
    }

    /**
     * Debe llamarse dentro de una transacción: bloquea la fila mientras la revisa.
     *
     * @return array{resultado: ResultadoRestablecimiento, solicitud: ?object}
     */
    private function revisar(int $idSolicitud, string $token): array
    {
        $solicitud = DB::table('recuperacion_contrasenia')
            ->select('*', DB::raw('(reco_fecha_expira > now()) as vigente'))
            ->where('reco_id_recuperacion', $idSolicitud)
            ->lockForUpdate()
            ->first();

        if ($solicitud === null || $solicitud->reco_fecha_uso !== null || ! $solicitud->vigente) {
            return ['resultado' => ResultadoRestablecimiento::NoValido, 'solicitud' => null];
        }

        if ($solicitud->reco_intentos >= self::INTENTOS_MAXIMOS) {
            return ['resultado' => ResultadoRestablecimiento::Bloqueado, 'solicitud' => null];
        }

        if (! hash_equals($solicitud->reco_token_hash, $this->hash($token))) {
            $intentos = $solicitud->reco_intentos + 1;

            DB::table('recuperacion_contrasenia')
                ->where('reco_id_recuperacion', $idSolicitud)
                ->update(['reco_intentos' => $intentos]);

            return [
                'resultado' => $intentos >= self::INTENTOS_MAXIMOS
                    ? ResultadoRestablecimiento::Bloqueado
                    : ResultadoRestablecimiento::NoValido,
                'solicitud' => null,
            ];
        }

        $cuentaSigueActiva = Usuario::query()
            ->whereKey($solicitud->reco_id_usuario)
            ->where('usua_activo', true)
            ->whereNotNull('usua_id_persona')
            ->exists();

        if (! $cuentaSigueActiva) {
            return ['resultado' => ResultadoRestablecimiento::NoValido, 'solicitud' => null];
        }

        return ['resultado' => ResultadoRestablecimiento::Valido, 'solicitud' => $solicitud];
    }

    private function cuentaActiva(string $correo): ?Usuario
    {
        return Usuario::porCorreo($correo)
            ->where('usua_activo', true)
            ->whereNotNull('usua_id_persona')
            ->first();
    }

    /**
     * HMAC-SHA-256 del token con la llave de la aplicación.
     */
    private function hash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}

<?php

namespace App\Services;

use App\Models\Persona;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Crea la primera cuenta del sistema: un superadministrador.
 *
 * Solo funciona si todavía no existe ninguna cuenta. Las demás se crean
 * desde la gestión de usuarios, con otro superadmin como actor.
 *
 * Todo ocurre en una transacción: la persona, la cuenta y su bitácora.
 * La cuenta nueva es el actor de su propia bitácora, porque no hay otro.
 */
class CuentaInicialService
{
    public function yaExistenCuentas(): bool
    {
        return Usuario::query()->exists();
    }

    public function existenRoles(): bool
    {
        return Rol::query()->where('rol_nombre', Rol::SUPERADMINISTRADOR)->exists();
    }

    /**
     * @param  array{nombre: string, primer_apellido: string, segundo_apellido: ?string, genero: string, correo: string, contrasenia: string}  $datos
     */
    public function crearSuperadmin(array $datos): Usuario
    {
        return DB::transaction(function () use ($datos): Usuario {
            if ($this->yaExistenCuentas()) {
                throw new RuntimeException('Ya existen cuentas. Crea las demás desde la gestión de usuarios.');
            }

            $idRol = Rol::query()
                ->where('rol_nombre', Rol::SUPERADMINISTRADOR)
                ->value('rol_id_rol');

            if ($idRol === null) {
                throw new RuntimeException('No existe el rol Superadministrador. Corre primero: php artisan db:seed');
            }

            $persona = new Persona;
            $persona->forceFill([
                'pers_nombre' => $datos['nombre'],
                'pers_primer_apellido' => $datos['primer_apellido'],
                'pers_segundo_apellido' => $datos['segundo_apellido'],
                'pers_genero' => $datos['genero'],
            ])->save();

            $cuenta = new Usuario;
            $cuenta->forceFill([
                'usua_id_persona' => $persona->pers_id_persona,
                'usua_id_rol' => $idRol,
                'usua_correo' => $datos['correo'],
                // El cast "hashed" del modelo la guarda cifrada
                'usua_contrasenia' => $datos['contrasenia'],
                'usua_activo' => true,
            ])->save();

            $this->registrarEnBitacora($cuenta->usua_id_usuario, 'PERSONA', $persona->pers_id_persona);
            $this->registrarEnBitacora($cuenta->usua_id_usuario, 'USUARIO', $cuenta->usua_id_usuario);

            return $cuenta;
        });
    }

    private function registrarEnBitacora(int $actor, string $tabla, int $referencia): void
    {
        DB::select('select bitacora_registrar(?, ?, ?, ?, ?)', [$actor, 'Registro', $tabla, $referencia, null]);
    }
}

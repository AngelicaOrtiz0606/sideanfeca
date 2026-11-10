<?php

namespace Tests\Support;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\SeguridadSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Ayuda para que las pruebas creen una persona con su cuenta.
 *
 * Solo se usa en pruebas que corren con DatabaseTransactions: todo lo que
 * se crea aquí se deshace al terminar cada prueba, así que la regla
 * diferida "toda persona tiene designación o cuenta" no llega a revisarse
 * y la bitácora real nunca se toca.
 */
trait CreaCuentas
{
    /**
     * Contraseña en texto de todas las cuentas de prueba.
     */
    protected string $contraseniaDePrueba = 'Contrasenia-de-prueba-1';

    /**
     * Crea una persona y su cuenta con el rol indicado.
     *
     * @param  array<string, mixed>  $datosUsuario  columnas de USUARIO a sobrescribir
     * @param  array<string, mixed>  $datosPersona  columnas de PERSONA a sobrescribir
     */
    protected function crearCuenta(
        string $rol = Rol::ADMINISTRADOR,
        array $datosUsuario = [],
        array $datosPersona = [],
    ): Usuario {
        $this->asegurarRoles();

        $idPersona = DB::table('persona')->insertGetId(array_merge([
            'pers_nombre' => 'Ana',
            'pers_primer_apellido' => 'Prueba',
            'pers_segundo_apellido' => null,
            'pers_genero' => 'F',
        ], $datosPersona), 'pers_id_persona');

        $idUsuario = DB::table('usuario')->insertGetId(array_merge([
            'usua_id_persona' => $idPersona,
            'usua_id_rol' => Rol::where('rol_nombre', $rol)->value('rol_id_rol'),
            'usua_correo' => "cuenta{$idPersona}@prueba.test",
            'usua_contrasenia' => Hash::make($this->contraseniaDePrueba),
            'usua_activo' => true,
        ], $datosUsuario), 'usua_id_usuario');

        return Usuario::findOrFail($idUsuario);
    }

    /**
     * Carga roles y privilegios si la base de pruebas todavía no los tiene.
     */
    protected function asegurarRoles(): void
    {
        if (! Rol::where('rol_nombre', Rol::SUPERADMINISTRADOR)->exists()) {
            $this->seed(SeguridadSeeder::class);
        }
    }
}

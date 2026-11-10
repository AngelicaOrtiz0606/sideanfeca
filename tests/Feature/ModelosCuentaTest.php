<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Modelos de cuentas: Usuario, Persona, Rol y Privilegio, sobre las tablas
 * reales del esquema en la base de pruebas.
 */
class ModelosCuentaTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    public function test_la_cuenta_obtiene_su_persona_y_su_rol(): void
    {
        $cuenta = $this->crearCuenta(Rol::SUPERADMINISTRADOR, [], [
            'pers_nombre' => 'María',
            'pers_primer_apellido' => 'López',
            'pers_segundo_apellido' => 'Díaz',
        ]);

        $this->assertSame('María López Díaz', $cuenta->persona->nombre_completo);
        $this->assertSame(Rol::SUPERADMINISTRADOR, $cuenta->rol->rol_nombre);
        $this->assertTrue($cuenta->estaActiva());
    }

    public function test_el_nombre_completo_omite_el_segundo_apellido_vacio(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, [], [
            'pers_nombre' => 'Luis',
            'pers_primer_apellido' => 'Ramírez',
            'pers_segundo_apellido' => null,
        ]);

        $this->assertSame('Luis Ramírez', $cuenta->persona->nombre_completo);
    }

    public function test_el_correo_se_busca_sin_importar_mayusculas_ni_espacios(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, [
            'usua_correo' => 'Ana.Prueba@Ejemplo.test',
        ]);

        $encontrada = Usuario::porCorreo('  ana.prueba@EJEMPLO.test ')->first();

        $this->assertNotNull($encontrada);
        $this->assertSame($cuenta->usua_id_usuario, $encontrada->usua_id_usuario);
    }

    public function test_la_contrasenia_nunca_sale_al_convertir_la_cuenta(): void
    {
        $cuenta = $this->crearCuenta();

        $this->assertArrayNotHasKey('usua_contrasenia', $cuenta->toArray());
        $this->assertStringNotContainsString($cuenta->usua_contrasenia, $cuenta->toJson());
    }

    public function test_la_curp_nunca_sale_al_convertir_la_persona(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, [], [
            'pers_curp' => 'LODM800101MDFPZR09',
        ]);

        $persona = Persona::findOrFail($cuenta->usua_id_persona);

        $this->assertArrayNotHasKey('pers_curp', $persona->toArray());
    }

    public function test_la_cuenta_no_usa_recordarme(): void
    {
        $cuenta = $this->crearCuenta();

        $this->assertSame('', $cuenta->getRememberTokenName());
        $this->assertNull($cuenta->getRememberToken());
    }

    public function test_cada_rol_tiene_sus_privilegios(): void
    {
        $this->asegurarRoles();

        $superadmin = Rol::where('rol_nombre', Rol::SUPERADMINISTRADOR)->firstOrFail();
        $admin = Rol::where('rol_nombre', Rol::ADMINISTRADOR)->firstOrFail();
        $privilegiosAdmin = $admin->privilegios->pluck('priv_nombre');

        $this->assertCount(13, $superadmin->privilegios);
        $this->assertCount(11, $privilegiosAdmin);
        $this->assertNotContains('ver_bitacora', $privilegiosAdmin);
        $this->assertNotContains('gestionar_usuarios', $privilegiosAdmin);
    }

    public function test_un_privilegio_desactivado_no_cuenta(): void
    {
        $this->asegurarRoles();

        $admin = Rol::where('rol_nombre', Rol::ADMINISTRADOR)->firstOrFail();
        $idPrivilegio = DB::table('privilegio')
            ->where('priv_nombre', 'gestionar_zonas')
            ->value('priv_id_privilegio');

        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $admin->rol_id_rol)
            ->where('ropr_id_privilegio', $idPrivilegio)
            ->update(['ropr_activo' => false]);

        $privilegios = $admin->privilegios()->pluck('priv_nombre');

        $this->assertCount(10, $privilegios);
        $this->assertNotContains('gestionar_zonas', $privilegios);
    }
}

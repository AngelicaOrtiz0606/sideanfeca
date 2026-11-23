<?php

namespace Tests\Feature\Usuarios;

use App\Models\Rol;
use App\Models\Usuario;
use App\Services\BitacoraService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Usuarios (solo superadministrador) y Cambiar contraseña (cualquier cuenta):
 * la CURP es obligatoria para las cuentas, una persona ya registrada recibe
 * su cuenta sin duplicarse, las cuentas se desactivan (nunca la propia) y
 * la bitácora no guarda valores personales.
 */
class UsuariosTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private const CLAVE = 'Temporal-2026';

    private Usuario $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = $this->crearCuenta(Rol::SUPERADMINISTRADOR, [], ['pers_nombre' => 'Marta', 'pers_primer_apellido' => 'Ruiz']);
    }

    public function test_crear_la_cuenta_de_una_persona_nueva(): void
    {
        $this->como($this->superadmin)->get('/usuarios/create')->assertOk()->assertSee('CURP');

        $this->como($this->superadmin)->post('/usuarios', $this->datos())
            ->assertRedirect('/usuarios')->assertSessionHasNoErrors()
            ->assertSessionHas('estado');

        $cuenta = Usuario::porCorreo('LAURA@anfeca.test')->firstOrFail();
        $this->assertTrue(Hash::check(self::CLAVE, $cuenta->usua_contrasenia));
        $this->assertSame(Rol::ADMINISTRADOR, $cuenta->rol->rol_nombre);
        $this->assertDatabaseHas('persona', ['pers_id_persona' => $cuenta->usua_id_persona, 'pers_nombre' => 'Laura', 'pers_curp' => 'GOML800101MDFRRR09']);

        $linea = app(BitacoraService::class)->recientes(1)->first();
        $this->assertSame(['creó', 'la cuenta de Laura Gómez López (Administrador)'], [$linea['verbo'], $linea['objeto']]);

        // Ya puede entrar
        auth()->logout();
        $this->flushSession();
        $this->post('/login', ['correo' => 'laura@anfeca.test', 'contrasenia' => self::CLAVE])->assertRedirect('/inicio');
    }

    public function test_una_persona_ya_registrada_recibe_su_cuenta_sin_duplicarse(): void
    {
        $persona = (int) DB::table('persona')->insertGetId(['pers_nombre' => 'Laura', 'pers_primer_apellido' => 'Gómez', 'pers_segundo_apellido' => 'López', 'pers_genero' => 'F'], 'pers_id_persona');

        // Al escribir, avisa que ya está registrada y ofrece crearle su cuenta
        $this->como($this->superadmin)->getJson('/usuarios/coincidencias?nombre=Laura+Gomez')->assertOk()
            ->assertJsonFragment(['nombre' => 'Laura Gómez López', 'accion' => 'Crearle su cuenta', 'enlace' => route('usuarios.create', ['persona' => $persona])]);
        $this->como($this->superadmin)->get("/usuarios/create?persona={$persona}")->assertOk()
            ->assertSee('ya está registrada')->assertSee('value="Laura"', false);

        $this->como($this->superadmin)->post('/usuarios', $this->datos() + ['persona' => $persona])->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('persona')->where('pers_nombre', 'Laura')->count());
        $this->assertDatabaseHas('usuario', ['usua_id_persona' => $persona]);
        $this->assertDatabaseHas('persona', ['pers_id_persona' => $persona, 'pers_curp' => 'GOML800101MDFRRR09']);

        // Ya tiene cuenta: ahora la coincidencia lleva a su cuenta
        $this->como($this->superadmin)->getJson('/usuarios/coincidencias?curp=GOML800101MDFRRR09')->assertOk()
            ->assertJsonFragment(['accion' => 'Ver su cuenta', 'detalle' => 'Misma CURP · Ya tiene cuenta']);
    }

    public function test_curp_obligatoria_y_sin_repetir_correo_ni_curp(): void
    {
        DB::table('persona')->insert(['pers_nombre' => 'Otra', 'pers_primer_apellido' => 'Persona', 'pers_genero' => 'F', 'pers_curp' => 'GOML800101MDFRRR09']);

        $this->como($this->superadmin)->post('/usuarios', ['curp' => ''] + $this->datos())
            ->assertSessionHasErrors(['curp' => 'Escribe la CURP: es obligatoria para las cuentas de usuario.']);
        $this->como($this->superadmin)->post('/usuarios', $this->datos())
            ->assertSessionHasErrors(['curp' => 'Esa CURP ya pertenece a otra persona. Búscala arriba para crearle su cuenta.']);
        $this->como($this->superadmin)->post('/usuarios', ['curp' => 'GOML800101MDFRRR01', 'correo' => strtoupper($this->superadmin->usua_correo)] + $this->datos())
            ->assertSessionHasErrors(['correo' => 'Ese correo ya es de otra cuenta.']);
        $this->como($this->superadmin)->post('/usuarios', ['curp' => 'GOML800101MDFRRR01', 'contrasenia' => 'corta', 'contrasenia_confirmation' => 'corta'] + $this->datos())
            ->assertSessionHasErrors('contrasenia');

        $this->assertNull(Usuario::porCorreo('laura@anfeca.test')->first());
    }

    public function test_editar_registra_cada_cambio_sin_valores_personales(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => 'laura@anfeca.test'], ['pers_nombre' => 'Laura', 'pers_primer_apellido' => 'Gómez', 'pers_curp' => 'GOML800101MDFRRR09']);
        $superadmin = (int) DB::table('rol')->where('rol_nombre', Rol::SUPERADMINISTRADOR)->value('rol_id_rol');

        $this->como($this->superadmin)->get("/usuarios/{$cuenta->usua_id_usuario}/edit")->assertOk()->assertSee('laura@anfeca.test');

        $this->como($this->superadmin)->put("/usuarios/{$cuenta->usua_id_usuario}", [
            'nombre' => 'Laurita', 'primer_apellido' => 'Gómez', 'segundo_apellido' => '', 'curp' => 'GOML800101MDFRRR09', 'genero' => 'F',
            'correo' => 'laura@anfeca.test', 'rol' => $superadmin, 'contrasenia' => '', 'contrasenia_confirmation' => '',
        ])->assertSessionHasNoErrors()->assertSessionHas('estado');

        $this->assertSame($superadmin, (int) $cuenta->fresh()->usua_id_rol);
        $this->assertTrue(Hash::check($this->contraseniaDePrueba, $cuenta->fresh()->usua_contrasenia));
        $detalles = app(BitacoraService::class)->recientes(1)->first()['detalles'];
        $this->assertContains('Rol: Administrador → Superadministrador', $detalles);
        $this->assertContains('Nombre', $detalles);
    }

    public function test_nadie_cambia_su_propio_rol_ni_desactiva_su_cuenta(): void
    {
        $admin = (int) DB::table('rol')->where('rol_nombre', Rol::ADMINISTRADOR)->value('rol_id_rol');
        $propia = $this->superadmin->usua_id_usuario;
        DB::table('persona')->where('pers_id_persona', $this->superadmin->usua_id_persona)->update(['pers_curp' => 'RUMM800101MDFRRR09']);

        $this->como($this->superadmin)->put("/usuarios/{$propia}", [
            'nombre' => 'Marta', 'primer_apellido' => 'Ruiz', 'curp' => 'RUMM800101MDFRRR09', 'genero' => 'F',
            'correo' => $this->superadmin->usua_correo, 'rol' => $admin,
        ])->assertSessionHasErrors(['rol' => 'No puedes cambiar tu propio rol. Pídeselo a otro superadministrador.']);

        $this->como($this->superadmin)->patch("/usuarios/{$propia}/estado", ['activa' => '0'])
            ->assertSessionHas('aviso', 'No puedes desactivar tu propia cuenta.');
        $this->assertTrue((bool) $this->superadmin->fresh()->usua_activo);
    }

    public function test_desactivar_y_activar_con_su_bitacora(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, ['usua_correo' => 'laura@anfeca.test'], ['pers_nombre' => 'Laura', 'pers_primer_apellido' => 'Gómez']);

        $this->como($this->superadmin)->patch("/usuarios/{$cuenta->usua_id_usuario}/estado", ['activa' => '0'])->assertSessionHas('estado', 'Cuenta desactivada.');
        $this->assertFalse((bool) $cuenta->fresh()->usua_activo);
        $this->como($this->superadmin)->get('/usuarios?estado=desactivadas')->assertOk()->assertSee('<span class="tabla__principal">Laura Gómez</span>', false)->assertDontSee('<span class="tabla__principal">Marta Ruiz</span>', false);

        // Desactivada no entra
        auth()->logout();
        $this->flushSession();
        $this->post('/login', ['correo' => 'laura@anfeca.test', 'contrasenia' => $this->contraseniaDePrueba])->assertRedirect();
        $this->assertGuest();

        $this->como($this->superadmin)->patch("/usuarios/{$cuenta->usua_id_usuario}/estado", ['activa' => '1'])->assertSessionHas('estado');
        $this->assertTrue((bool) $cuenta->fresh()->usua_activo);
        $this->assertSame('activó', app(BitacoraService::class)->recientes(1)->first()['verbo']);
    }

    public function test_solo_el_superadministrador_gestiona_usuarios(): void
    {
        $admin = $this->crearCuenta(Rol::ADMINISTRADOR);

        $this->como($admin)->get('/usuarios')->assertForbidden();
        $this->como($admin)->get('/usuarios/create')->assertForbidden();
        $this->como($admin)->post('/usuarios', $this->datos())->assertForbidden();
        $this->como($admin)->patch("/usuarios/{$this->superadmin->usua_id_usuario}/estado", ['activa' => '0'])->assertForbidden();
    }

    public function test_cambiar_la_propia_contrasenia(): void
    {
        $admin = $this->crearCuenta(Rol::ADMINISTRADOR);

        $this->como($admin)->get('/cuenta/contrasenia')->assertOk();
        $this->como($admin)->put('/cuenta/contrasenia', ['actual' => 'equivocada', 'contrasenia' => 'Nueva-Clave-1', 'contrasenia_confirmation' => 'Nueva-Clave-1'])
            ->assertSessionHasErrors(['actual' => 'Tu contraseña actual no es correcta.']);
        $this->como($admin)->put('/cuenta/contrasenia', ['actual' => $this->contraseniaDePrueba, 'contrasenia' => 'Nueva-Clave-1', 'contrasenia_confirmation' => 'Otra-Clave-1'])
            ->assertSessionHasErrors('contrasenia');

        $this->como($admin)->put('/cuenta/contrasenia', ['actual' => $this->contraseniaDePrueba, 'contrasenia' => 'Nueva-Clave-1', 'contrasenia_confirmation' => 'Nueva-Clave-1'])
            ->assertRedirect('/cuenta/contrasenia')->assertSessionHas('estado');

        $this->assertTrue(Hash::check('Nueva-Clave-1', $admin->fresh()->usua_contrasenia));
        // La sesión sigue abierta con la contraseña nueva
        $this->get('/inicio')->assertOk();
        $this->assertSame(['Contraseña'], app(BitacoraService::class)->recientes(1)->first()['detalles']);
    }

    // ---- Ayudas ----

    /** @return array<string, mixed> */
    private function datos(): array
    {
        return [
            'nombre' => 'Laura', 'primer_apellido' => 'Gómez', 'segundo_apellido' => 'López', 'curp' => 'goml800101mdfrrr09', 'genero' => 'F',
            'correo' => 'laura@anfeca.test', 'rol' => (int) DB::table('rol')->where('rol_nombre', Rol::ADMINISTRADOR)->value('rol_id_rol'),
            'contrasenia' => self::CLAVE, 'contrasenia_confirmation' => self::CLAVE,
        ];
    }

    /** Entra con esa cuenta (al cambiar de cuenta empieza otra sesión: auth.session). */
    private function como(Usuario $cuenta): static
    {
        if (auth()->id() !== $cuenta->getAuthIdentifier()) {
            $this->flushSession();
            auth()->forgetGuards();
        }

        return $this->actingAs($cuenta);
    }
}

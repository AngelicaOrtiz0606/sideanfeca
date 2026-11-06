<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\SeguridadSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Comando sideanfeca:crear-superadmin: crea la primera cuenta del sistema.
 */
class CrearSuperadminTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private const CORREO = 'responsable@anfeca.test';

    private const CONTRASENIA = 'clave2026';

    public function test_crea_la_persona_la_cuenta_y_su_bitacora(): void
    {
        $this->seed(SeguridadSeeder::class);

        $this->ejecutar()->assertExitCode(0);

        $cuenta = Usuario::porCorreo(self::CORREO)->firstOrFail();

        $this->assertTrue($cuenta->estaActiva());
        $this->assertSame(Rol::SUPERADMINISTRADOR, $cuenta->rol->rol_nombre);
        $this->assertSame('Marta Ruiz Soto', $cuenta->persona->nombre_completo);
        $this->assertSame('F', $cuenta->persona->pers_genero);

        // La contraseña se guarda cifrada, nunca en texto
        $this->assertNotSame(self::CONTRASENIA, $cuenta->usua_contrasenia);
        $this->assertTrue(Hash::check(self::CONTRASENIA, $cuenta->usua_contrasenia));

        $bitacora = DB::table('bitacora')
            ->where('bita_id_usuario', $cuenta->usua_id_usuario)
            ->orderBy('bita_id_bitacora')
            ->get();

        $this->assertCount(2, $bitacora);
        $this->assertSame(['Registro', 'Registro'], $bitacora->pluck('bita_accion')->all());
        $this->assertSame(['PERSONA', 'USUARIO'], $bitacora->pluck('bita_tabla')->all());
        $this->assertSame(
            [$cuenta->usua_id_persona, $cuenta->usua_id_usuario],
            $bitacora->pluck('bita_referencia')->all(),
        );

        // Sin valores personales en el detalle de la bitácora
        $this->assertSame(0, DB::table('bitacora_detalle')
            ->whereIn('bide_id_bitacora', $bitacora->pluck('bita_id_bitacora'))
            ->count());
    }

    public function test_con_la_cuenta_creada_se_puede_iniciar_sesion(): void
    {
        $this->seed(SeguridadSeeder::class);
        $this->ejecutar()->assertExitCode(0);

        $this->post('/login', ['correo' => self::CORREO, 'contrasenia' => self::CONTRASENIA])
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticated();
    }

    public function test_acepta_el_segundo_apellido_vacio(): void
    {
        $this->seed(SeguridadSeeder::class);

        $this->ejecutar(['segundo_apellido' => ''])->assertExitCode(0);

        $cuenta = Usuario::porCorreo(self::CORREO)->firstOrFail();
        $this->assertNull($cuenta->persona->pers_segundo_apellido);
    }

    public function test_se_niega_si_ya_existe_una_cuenta(): void
    {
        $this->crearCuenta();
        $antes = Usuario::query()->count();

        $this->artisan('sideanfeca:crear-superadmin')
            ->expectsOutputToContain('Ya existen cuentas')
            ->assertExitCode(1);

        $this->assertSame($antes, Usuario::query()->count());
    }

    public function test_se_niega_si_no_existen_los_roles(): void
    {
        $this->assertFalse(Rol::query()->exists(), 'La base de pruebas debería empezar sin roles.');

        $this->artisan('sideanfeca:crear-superadmin')
            ->expectsOutputToContain('php artisan db:seed')
            ->assertExitCode(1);
    }

    public function test_se_niega_con_un_correo_invalido(): void
    {
        $this->seed(SeguridadSeeder::class);

        $this->ejecutar(['correo' => 'no-es-correo'], confirmar: false)
            ->expectsOutputToContain('correo electrónico válido')
            ->assertExitCode(1);

        $this->assertFalse(Usuario::query()->exists());
    }

    public function test_se_niega_si_la_confirmacion_no_coincide(): void
    {
        $this->seed(SeguridadSeeder::class);

        $this->ejecutar(['confirmacion' => 'otra2026'], confirmar: false)
            ->expectsOutputToContain('no coincide')
            ->assertExitCode(1);

        $this->assertFalse(Usuario::query()->exists());
    }

    public function test_se_niega_con_una_contrasenia_sin_numeros(): void
    {
        $this->seed(SeguridadSeeder::class);

        $this->ejecutar(['contrasenia' => 'solotexto', 'confirmacion' => 'solotexto'], confirmar: false)
            ->expectsOutputToContain('al menos un número')
            ->assertExitCode(1);

        $this->assertFalse(Usuario::query()->exists());
    }

    public function test_se_niega_con_una_contrasenia_corta(): void
    {
        $this->seed(SeguridadSeeder::class);

        $this->ejecutar(['contrasenia' => 'ab12', 'confirmacion' => 'ab12'], confirmar: false)
            ->expectsOutputToContain('al menos 8 caracteres')
            ->assertExitCode(1);

        $this->assertFalse(Usuario::query()->exists());
    }

    public function test_si_responde_que_no_no_guarda_nada(): void
    {
        $this->seed(SeguridadSeeder::class);

        $this->ejecutar(respuestaFinal: 'no')
            ->expectsOutputToContain('No se guardó nada')
            ->assertExitCode(1);

        $this->assertFalse(Usuario::query()->exists());
        $this->assertSame(0, DB::table('persona')->count());
    }

    /**
     * Ejecuta el comando con respuestas válidas, salvo las que se cambien.
     *
     * @param  array<string, string>  $cambios
     */
    private function ejecutar(array $cambios = [], bool $confirmar = true, string $respuestaFinal = 'yes'): PendingCommand
    {
        $r = array_merge([
            'nombre' => 'Marta',
            'primer_apellido' => 'Ruiz',
            'segundo_apellido' => 'Soto',
            'genero' => 'F',
            'correo' => self::CORREO,
            'contrasenia' => self::CONTRASENIA,
            'confirmacion' => self::CONTRASENIA,
        ], $cambios);

        $comando = $this->artisan('sideanfeca:crear-superadmin')
            ->expectsQuestion('Nombre', $r['nombre'])
            ->expectsQuestion('Primer apellido', $r['primer_apellido'])
            ->expectsQuestion('Segundo apellido (opcional, Enter para omitir)', $r['segundo_apellido'])
            ->expectsChoice('Género', $r['genero'], ['F', 'M'])
            ->expectsQuestion('Correo electrónico', $r['correo'])
            ->expectsQuestion('Contraseña (al menos 8 caracteres, con letras y números)', $r['contrasenia'])
            ->expectsQuestion('Escribe la contraseña otra vez', $r['confirmacion']);

        if ($confirmar) {
            $comando->expectsConfirmation('¿Crear la cuenta?', $respuestaFinal);
        }

        return $comando;
    }
}

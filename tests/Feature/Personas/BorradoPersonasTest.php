<?php

namespace Tests\Feature\Personas;

use App\Models\Rol;
use App\Models\Usuario;
use App\Services\BitacoraService;
use Database\Seeders\CatalogosInicialesSeeder;
use Database\Seeders\GradosAcademicosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\Support\CreaInstituciones;
use Tests\TestCase;

/**
 * Borrado de personas (M8): sin cuenta se elimina por completo; con cuenta
 * se oculta (el superadministrador la ve en "Personas ocultas" y puede
 * mostrarla, eliminarla definitivamente o atender una cancelación ARCO).
 * No se borra a quien tiene una designación vigente, ni a uno mismo.
 */
class BorradoPersonasTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    private Usuario $admin;

    private Usuario $superadmin;

    private int $organizacion;

    private int $presidente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosInicialesSeeder::class);
        $this->seed(GradosAcademicosSeeder::class);
        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $this->superadmin = $this->crearCuenta(Rol::SUPERADMINISTRADOR);
        $this->organizacion = (int) DB::table('organizacion')->insertGetId([
            'orga_nombre' => 'CACECA de prueba',
            'orga_id_domicilio' => DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $this->crearInstitucion('Observadora'))->value('ined_id_domicilio'),
            'orga_activo' => true,
        ], 'orga_id_organizacion');
        $this->presidente = (int) DB::table('cargo')->insertGetId(['carg_nombre_m' => 'Presidente de prueba', 'carg_nombre_f' => 'Presidenta de prueba', 'carg_nivel' => 'Nacional', 'carg_activo' => true], 'carg_id_cargo');
    }

    public function test_sin_cuenta_y_sin_designacion_vigente_se_borra_por_completo(): void
    {
        $persona = $this->registrar('Rosa', '2024-01-01');

        // Con su designación vigente, no
        $this->como($this->admin)->delete("/personas/{$persona}", ['motivo' => 'Duplicado'])
            ->assertSessionHas('aviso', 'Tiene una designación vigente. Primero termínala (en su ficha, pestaña Designaciones).');
        $this->assertDatabaseHas('persona', ['pers_id_persona' => $persona]);

        $this->terminar($persona, '2024-06-01');
        $this->como($this->admin)->delete("/personas/{$persona}", ['motivo' => 'Porque sí'])->assertSessionHasErrors('motivo');
        $this->como($this->admin)->delete("/personas/{$persona}", ['motivo' => 'Duplicado'])
            ->assertRedirect('/personas')->assertSessionHas('estado', 'Se borró a Rosa Gómez por completo.');

        $this->assertDatabaseMissing('persona', ['pers_id_persona' => $persona]);
        $this->assertSame(0, DB::table('designacion')->where('desi_id_persona', $persona)->count());
        $linea = app(BitacoraService::class)->recientes(1)->first();
        $this->assertSame(['eliminó', "a una persona eliminada (#{$persona})", 'Duplicado'], [$linea['verbo'], $linea['objeto'], $linea['motivo']]);
    }

    public function test_con_cuenta_se_oculta_y_el_superadministrador_la_puede_mostrar(): void
    {
        $persona = $this->conCuentaEnDirectorio('Laura');

        $this->como($this->admin)->delete("/personas/{$persona}", ['motivo' => 'Decisión de la organización'])
            ->assertSessionHas('estado', 'Se ocultó a Laura Gómez: tiene cuenta de usuario, así que no se borró. Un superadministrador puede volver a mostrarla.');
        $this->assertDatabaseHas('persona', ['pers_id_persona' => $persona, 'pers_oculta_admin' => true]);
        $this->como($this->admin)->get("/personas/{$persona}")->assertNotFound();

        // El administrador no ve las ocultas; el superadministrador sí
        $this->como($this->admin)->get('/personas-ocultas')->assertForbidden();
        $this->como($this->superadmin)->get('/personas-ocultas')->assertOk()->assertSee('Laura Gómez');

        $this->como($this->superadmin)->patch("/personas/{$persona}/mostrar")
            ->assertSessionHas('estado', 'Laura Gómez vuelve a aparecer para los administradores.');
        $this->assertDatabaseHas('persona', ['pers_id_persona' => $persona, 'pers_oculta_admin' => false]);
        $this->como($this->admin)->get("/personas/{$persona}")->assertOk();
    }

    public function test_eliminar_definitivamente_conserva_nombre_y_curp_y_desactiva_la_cuenta(): void
    {
        $persona = $this->conCuentaEnDirectorio('Laura');
        $this->como($this->admin)->delete("/personas/{$persona}", ['motivo' => 'Duplicado']);

        $this->como($this->superadmin)->delete("/personas/{$persona}/definitiva", ['motivo' => 'Duplicado'])
            ->assertSessionHas('estado', 'Se borraron los datos de Laura Gómez. Se conservaron su nombre y su CURP, y su cuenta quedó desactivada.');

        $this->assertDatabaseHas('persona', ['pers_id_persona' => $persona, 'pers_nombre' => 'Laura']);
        $this->assertSame(0, DB::table('designacion')->where('desi_id_persona', $persona)->count());
        $this->assertDatabaseHas('usuario', ['usua_id_persona' => $persona, 'usua_activo' => false]);
    }

    public function test_la_cancelacion_arco_pide_el_nombre_y_borra_todo_lo_personal(): void
    {
        $persona = $this->conCuentaEnDirectorio('Laura');
        $cuenta = (int) DB::table('usuario')->where('usua_id_persona', $persona)->value('usua_id_usuario');

        // El administrador no puede; con el nombre equivocado, tampoco
        $this->como($this->admin)->delete("/personas/{$persona}/arco", ['confirmacion' => 'Laura Gómez'])->assertForbidden();
        $this->como($this->superadmin)->delete("/personas/{$persona}/arco", ['confirmacion' => 'Laura'])
            ->assertSessionHasErrors(['confirmacion' => 'Escribe el nombre completo tal como aparece para confirmar.']);

        $this->como($this->superadmin)->delete("/personas/{$persona}/arco", ['confirmacion' => '  laura   GÓMEZ '])
            ->assertSessionHas('estado', "Cancelación ARCO aplicada a la persona #{$persona}: se borraron todos sus datos personales.");

        $this->assertDatabaseMissing('persona', ['pers_id_persona' => $persona]);
        $this->assertDatabaseHas('usuario', ['usua_id_usuario' => $cuenta, 'usua_activo' => false, 'usua_id_persona' => null, 'usua_correo' => "anonimo-{$cuenta}@invalido.invalid"]);

        $lineas = app(BitacoraService::class)->recientes(2);
        $this->assertSame('Cancelación ARCO', $lineas->firstWhere('accion', 'Eliminación')['motivo'] ?? $lineas->last()['motivo']);
    }

    public function test_nadie_se_borra_a_si_mismo(): void
    {
        $propia = (int) $this->admin->usua_id_persona;
        $this->enDirectorio($propia, '2020-01-01', '2020-12-31');

        $this->como($this->admin)->delete("/personas/{$propia}", ['motivo' => 'Duplicado'])
            ->assertSessionHas('aviso', 'Eres tú: no puedes borrar tu propia persona.');
        $this->assertDatabaseHas('persona', ['pers_id_persona' => $propia, 'pers_oculta_admin' => false]);
    }

    // ---- Ayudas ----

    /**
     * Entra con esa cuenta. Al cambiar de cuenta se empieza otra sesión: la
     * sesión guarda la huella de la contraseña de quien entró (auth.session).
     */
    private function como(Usuario $cuenta): static
    {
        if (auth()->id() !== $cuenta->getAuthIdentifier()) {
            $this->flushSession();
            auth()->forgetGuards();
        }

        return $this->actingAs($cuenta);
    }

    /** Registra una persona sin cuenta con una designación desde $desde (como la pantalla). */
    private function registrar(string $nombre, string $desde): int
    {
        $this->como($this->admin)->post('/personas', [
            'nombre' => $nombre, 'primer_apellido' => 'Gómez', 'segundo_apellido' => '', 'curp' => '', 'genero' => 'F',
            'pertenencia' => 'organizacion', 'organizacion' => $this->organizacion, 'grados' => [],
            'correo' => mb_strtolower($nombre).'@caceca.org', 'telefono' => ['lada' => '55', 'valor' => '56228300', 'extension' => ''], 'contactos' => [],
            'designaciones' => [['cargo' => $this->presidente, 'fecha_inicio' => $desde]],
        ])->assertSessionHasNoErrors();

        return (int) DB::table('persona')->where('pers_nombre', $nombre)->where('pers_primer_apellido', 'Gómez')->value('pers_id_persona');
    }

    private function terminar(int $persona, string $fecha): void
    {
        $designacion = (int) DB::table('designacion')->where('desi_id_persona', $persona)->value('desi_id_designacion');
        $this->como($this->admin)->patch("/personas/{$persona}/designaciones/{$designacion}/terminar", ['fecha' => $fecha])->assertSessionHas('estado');
    }

    /** Una persona con cuenta que está en los directorios por una designación ya terminada. */
    private function conCuentaEnDirectorio(string $nombre): int
    {
        $persona = (int) $this->crearCuenta(Rol::ADMINISTRADOR, [], ['pers_nombre' => $nombre, 'pers_primer_apellido' => 'Gómez'])->usua_id_persona;
        $this->enDirectorio($persona, '2021-01-01', '2021-12-31');

        return $persona;
    }

    private function enDirectorio(int $persona, string $desde, string $hasta): void
    {
        DB::table('persona')->where('pers_id_persona', $persona)->update(['pers_id_organizacion' => $this->organizacion]);
        $puesto = (int) (DB::table('puesto')->where('pues_id_cargo', $this->presidente)->value('pues_id_puesto')
            ?? DB::table('puesto')->insertGetId(['pues_id_cargo' => $this->presidente], 'pues_id_puesto'));
        DB::table('designacion')->insert(['desi_id_puesto' => $puesto, 'desi_id_persona' => $persona, 'desi_fecha_inicio' => $desde, 'desi_fecha_fin' => $hasta]);
    }
}

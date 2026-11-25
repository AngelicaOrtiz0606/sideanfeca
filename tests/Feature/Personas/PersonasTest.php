<?php

namespace Tests\Feature\Personas;

use App\Enums\Privilegio;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogosInicialesSeeder;
use Database\Seeders\GradosAcademicosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreaCuentas;
use Tests\Support\CreaInstituciones;
use Tests\TestCase;

/**
 * Criterios de M5 (personas y contactos):
 * - CURP inválida o repetida: rechazada.
 * - Persona sin institución ni organización: se puede guardar, pero no recibir designación.
 * - Grados múltiples y contactos con lada o extensión según el tipo.
 * - La bitácora registra qué cambió, nunca el valor personal.
 */
class PersonasTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    private Usuario $admin;

    private int $persona;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosInicialesSeeder::class);
        $this->seed(GradosAcademicosSeeder::class);
        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        // La persona que se edita: tiene cuenta, así la base la acepta sin designación
        $this->persona = (int) $this->crearCuenta(Rol::ADMINISTRADOR, [], ['pers_nombre' => 'María José', 'pers_primer_apellido' => 'Núñez', 'pers_segundo_apellido' => 'Álvarez', 'pers_genero' => 'F'])->usua_id_persona;
        // Es de los directorios: tuvo un cargo (ya terminó) y pertenece a una organización
        $this->enDirectorio($this->persona);
        DB::table('persona')->where('pers_id_persona', $this->persona)->update(['pers_id_organizacion' => DB::table('organizacion')->value('orga_id_organizacion')]);
        // Con su correo y su teléfono obligatorios, como los deja el formulario
        DB::table('contacto')->insert([
            ['cont_id_persona' => $this->persona, 'cont_id_tipo_contacto' => $this->tipo('Correo'), 'cont_valor' => 'maria@unam.mx', 'cont_lada' => null, 'cont_extension' => null, 'cont_visible' => true],
            ['cont_id_persona' => $this->persona, 'cont_id_tipo_contacto' => $this->tipo('Teléfono'), 'cont_valor' => '56228300', 'cont_lada' => '55', 'cont_extension' => null, 'cont_visible' => true],
        ]);
    }

    // ---- Lista ----

    public function test_la_lista_busca_sin_acentos_y_no_muestra_personas_ocultas(): void
    {
        $this->assertContains('María José Núñez Álvarez', $this->filas('/personas?texto=maria%20jose'));
        $this->assertContains('María José Núñez Álvarez', $this->filas(route('personas.index', ['texto' => 'NUÑEZ'])));
        $this->assertNotContains('María José Núñez Álvarez', $this->filas(route('personas.index', ['texto' => 'nunez']))); // la ñ cuenta como letra distinta

        DB::table('persona')->where('pers_id_persona', $this->persona)->update(['pers_oculta_admin' => true]);
        $this->assertNotContains('María José Núñez Álvarez', $this->filas('/personas'));
        $this->actingAs($this->admin)->get("/personas/{$this->persona}")->assertNotFound();
    }

    public function test_la_lista_filtra_por_cargo_zona_y_estado_y_va_de_10_en_10(): void
    {
        $this->assertContains('María José Núñez Álvarez', $this->filas('/personas?situacion=sin-cargo'));
        $this->assertSame([], $this->filas('/personas?situacion=con-cargo'));

        // Le damos un cargo regional vigente: zona 3, en una coordinación
        $zona = (int) DB::table('zona_regional')->where('zore_numero', 3)->value('zore_id_zona_regional');
        $coordinacion = (int) DB::table('coordinacion_nacional')->value('cona_id_coordinacion_nacional');
        $consejero = (int) DB::table('cargo')->insertGetId(['carg_nombre_m' => 'Vocal de prueba', 'carg_nombre_f' => 'Vocal de prueba', 'carg_nivel' => 'Regional', 'carg_activo' => true], 'carg_id_cargo');
        $puesto = (int) DB::table('puesto')->insertGetId(['pues_id_cargo' => $consejero, 'pues_id_zona_regional' => $zona, 'pues_id_coordinacion_nacional' => $coordinacion], 'pues_id_puesto');
        $this->conPertenenciaTemporal($this->persona, fn () => DB::table('designacion')->insert(['desi_id_puesto' => $puesto, 'desi_id_persona' => $this->persona, 'desi_fecha_inicio' => '2025-01-01']));

        $mia = ['María José Núñez Álvarez'];
        $this->assertSame($mia, $this->filas('/personas?situacion=con-cargo'));
        $this->assertSame($mia, $this->filas("/personas?cargo={$consejero}"));
        $this->assertSame($mia, $this->filas("/personas?zona={$zona}"));
        $this->assertSame([], $this->filas('/personas?zona='.DB::table('zona_regional')->where('zore_numero', 4)->value('zore_id_zona_regional')));

        // El buscador también encuentra por correo y por cargo (sin acentos)
        $this->assertSame($mia, $this->filas(route('personas.index', ['texto' => 'maria@unam'])));
        $this->assertSame($mia, $this->filas(route('personas.index', ['texto' => 'vocal de PRUEBA'])));

        foreach (range(1, 11) as $n) {
            $this->enDirectorio($this->crearCuenta(Rol::ADMINISTRADOR, [], ['pers_nombre' => "Persona {$n}", 'pers_primer_apellido' => 'Zeta'])->usua_id_persona);
        }
        $this->actingAs($this->admin)->get('/personas')->assertSee('Mostrando 1–10 de 12');
    }

    public function test_quien_solo_tiene_cuenta_no_esta_en_personas(): void
    {
        // El administrador que entra solo tiene cuenta: se ve en Usuarios, no aquí
        $soloCuenta = (int) $this->admin->usua_id_persona;
        $this->assertNotContains($this->nombreDe($soloCuenta), $this->filas('/personas'));
        $this->actingAs($this->admin)->get("/personas/{$soloCuenta}")->assertNotFound();
        $this->actingAs($this->admin)->get("/personas/{$soloCuenta}/edit")->assertNotFound();
        $this->guardar([], $soloCuenta)->assertNotFound();

        // Con cuenta y con un cargo (aunque ya terminó): sí está
        $this->assertContains('María José Núñez Álvarez', $this->filas('/personas'));
    }

    // ---- Datos personales ----

    public function test_editar_los_datos_personales_registra_que_cambio_sin_guardar_el_valor(): void
    {
        $this->guardar(['curp' => ' nuaj800101mdfxlr09 '])
            ->assertRedirect("/personas/{$this->persona}")
            ->assertSessionHasNoErrors();

        // CURP en mayúsculas y sin espacios
        $this->assertSame('NUAJ800101MDFXLR09', DB::table('persona')->where('pers_id_persona', $this->persona)->value('pers_curp'));

        $bitacora = DB::table('bitacora')->orderByDesc('bita_id_bitacora')->first();
        $this->assertSame(['Modificacion', 'PERSONA'], [$bitacora->bita_accion, $bitacora->bita_tabla]);
        $detalles = DB::table('bitacora_detalle')->where('bide_id_bitacora', $bitacora->bita_id_bitacora)->get();
        $this->assertSame(['CURP'], $detalles->pluck('bide_campo')->all());
        $this->assertNull($detalles->first()->bide_anterior);
        $this->assertNull($detalles->first()->bide_nuevo);
    }

    public function test_una_curp_invalida_o_repetida_se_rechaza(): void
    {
        foreach (['NUAJ800101', 'NUAJ800101XDFXLR09', '1234567890ABCDEFGH'] as $mala) {
            $this->guardar(['curp' => $mala])
                ->assertSessionHasErrors('curp');
        }

        $otra = (int) $this->crearCuenta(Rol::ADMINISTRADOR, [], ['pers_curp' => 'PEAJ900215HDFRLN03'])->usua_id_persona;
        $this->guardar(['curp' => 'peaj900215hdfrln03'])
            ->assertSessionHasErrors(['curp' => 'Esa CURP ya pertenece a otra persona.']);

        // La propia CURP no cuenta como repetida
        $this->guardar(['curp' => 'PEAJ900215HDFRLN03', 'genero' => 'M'], $otra)->assertSessionHasNoErrors();
        $this->assertNull(DB::table('persona')->where('pers_id_persona', $this->persona)->value('pers_curp'));
    }

    public function test_la_misma_persona_con_cuenta_y_en_directorios_se_une_por_su_curp(): void
    {
        // Marvin: su cuenta (sin directorios) y, por error, otro registro en los directorios con otra CURP
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR, [], ['pers_nombre' => 'Marvin', 'pers_primer_apellido' => 'Betancourt', 'pers_curp' => 'BEAM000000HDFRLNA2']);
        $duplicada = (int) $cuenta->usua_id_persona;
        $directorio = (int) DB::table('persona')->insertGetId(['pers_nombre' => 'Marvin', 'pers_primer_apellido' => 'Betancourt', 'pers_genero' => 'M',
            'pers_curp' => 'BEAM000000HDFRLNA9', 'pers_id_organizacion' => DB::table('organizacion')->value('orga_id_organizacion')], 'pers_id_persona');
        $this->enDirectorio($directorio);
        $datos = $this->datos(['nombre' => 'Marvin', 'primer_apellido' => 'Betancourt', 'segundo_apellido' => '', 'genero' => 'M', 'curp' => 'BEAM000000HDFRLNA2', 'correo' => 'marvin@anfeca.mx']);

        // El administrador recibe la explicación; el superadministrador puede unirlos
        $this->actingAs($this->admin)->put("/personas/{$directorio}", $datos)
            ->assertSessionHasErrors(['curp' => 'Esa CURP es de la cuenta de usuario de Marvin Betancourt. Si es la misma persona, pide a un superadministrador que una los dos registros.']);
        $superadmin = $this->crearCuenta(Rol::SUPERADMINISTRADOR);
        $this->flushSession();
        $this->actingAs($superadmin)->put("/personas/{$directorio}", $datos)
            ->assertSessionHasErrors(['curp' => 'Esa CURP es de la cuenta de usuario de Marvin Betancourt. Si es la misma persona, marca «Unir con su cuenta».']);

        $this->actingAs($superadmin)->put("/personas/{$directorio}", $datos + ['unir_cuenta' => '1'])->assertSessionHasNoErrors();

        // La cuenta queda con el registro de los directorios y el duplicado se borró
        $this->assertSame($directorio, (int) DB::table('usuario')->where('usua_id_usuario', $cuenta->usua_id_usuario)->value('usua_id_persona'));
        $this->assertDatabaseMissing('persona', ['pers_id_persona' => $duplicada]);
        $this->assertSame('BEAM000000HDFRLNA2', DB::table('persona')->where('pers_id_persona', $directorio)->value('pers_curp'));
    }

    public function test_los_textos_peligrosos_se_rechazan(): void
    {
        $this->guardar(['nombre' => '<script>x</script>'])->assertSessionHasErrors('nombre');
        $this->guardar(['primer_apellido' => '=CMD()'])->assertSessionHasErrors('primer_apellido');
    }

    // ---- Pertenencia ----

    public function test_siempre_pertenece_a_una_institucion_u_organizacion(): void
    {
        // El formulario no ofrece "Ninguna": las personas de los directorios siempre pertenecen a algo
        $this->guardar(['pertenencia' => 'ninguna'])->assertSessionHasErrors(['pertenencia' => 'Elige a qué pertenece: una institución o una organización.']);
        $this->actingAs($this->admin)->get("/personas/{$this->persona}/edit")->assertDontSee('value="ninguna"', false);

        // Y la base no deja recibir una designación a quien no pertenece a nada
        DB::table('persona')->where('pers_id_persona', $this->persona)->update(['pers_id_organizacion' => null]);
        $puesto = $this->puestoNacional();
        $this->expectException(QueryException::class);
        DB::table('designacion')->insert(['desi_id_puesto' => $puesto, 'desi_id_persona' => $this->persona, 'desi_fecha_inicio' => '2024-01-01']);
    }

    public function test_la_pertenencia_se_cambia_a_institucion_u_organizacion(): void
    {
        $institucion = $this->institucion();
        $this->guardar(['pertenencia' => 'institucion', 'institucion' => $institucion])->assertRedirect("/personas/{$this->persona}")->assertSessionHasNoErrors();

        $this->assertDatabaseHas('persona', ['pers_id_persona' => $this->persona, 'pers_id_institucion_educativa' => $institucion, 'pers_nombre' => 'María José']);
        $this->assertSame(['Pertenencia'], DB::table('bitacora_detalle')->orderByDesc('bide_id_detalle')->limit(1)->pluck('bide_campo')->all());

        $organizacion = (int) DB::table('organizacion')->insertGetId(['orga_nombre' => 'CACECA', 'orga_id_domicilio' => DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $institucion)->value('ined_id_domicilio'), 'orga_activo' => true], 'orga_id_organizacion');
        $this->guardar(['pertenencia' => 'organizacion', 'organizacion' => $organizacion, 'institucion' => $institucion])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('persona', ['pers_id_persona' => $this->persona, 'pers_id_institucion_educativa' => null, 'pers_id_organizacion' => $organizacion]);
    }

    public function test_con_un_cargo_institucional_vigente_la_pertenencia_no_cambia(): void
    {
        $fca = $this->institucion();
        DB::table('persona')->where('pers_id_persona', $this->persona)->update(['pers_id_institucion_educativa' => $fca]);
        $cargo = (int) DB::table('cargo')->insertGetId(['carg_nombre_m' => 'Director', 'carg_nombre_f' => 'Directora', 'carg_nivel' => 'Institucional', 'carg_activo' => true], 'carg_id_cargo');
        $puesto = (int) DB::table('puesto')->insertGetId(['pues_id_cargo' => $cargo, 'pues_id_institucion_educativa' => $fca], 'pues_id_puesto');
        DB::table('designacion')->insert(['desi_id_puesto' => $puesto, 'desi_id_persona' => $this->persona, 'desi_fecha_inicio' => '2024-01-01']);

        $this->guardar(['pertenencia' => 'ninguna'])->assertSessionHasErrors('pertenencia');
        $this->guardar(['pertenencia' => 'institucion', 'institucion' => $this->institucion('1000002')])->assertSessionHasErrors('pertenencia');
        $this->assertDatabaseHas('persona', ['pers_id_persona' => $this->persona, 'pers_id_institucion_educativa' => $fca]);

        // La consulta muestra su cargo vigente
        $this->actingAs($this->admin)->get("/personas/{$this->persona}")->assertSee('Directora · Facultad 1000001')->assertSee('Con designación vigente');
    }

    // ---- Grados ----

    public function test_puede_tener_varios_grados_o_ninguno(): void
    {
        $ids = DB::table('nivel_academico')->whereIn('niac_nombre', ['Licenciatura', 'Maestría'])->pluck('niac_id_nivel_academico')->map(fn ($g) => (int) $g)->all();

        $this->guardar(['grados' => $ids])->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('persona_nivel_academico')->where('pena_id_persona', $this->persona)->count());

        // En el formulario se ven como elegidos (y salen de la lista para agregar)
        $this->actingAs($this->admin)->get("/personas/{$this->persona}/edit")
            ->assertSee('<input type="hidden" name="grados[]" value="'.$ids[0].'">', false)
            ->assertSee('Agregar un grado');
        $this->assertSame('Grado académico', DB::table('bitacora_detalle')->orderByDesc('bide_id_detalle')->value('bide_campo'));
        $this->actingAs($this->admin)->get("/personas/{$this->persona}")->assertSee('Mtra.');

        $this->guardar(['grados' => []])->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('persona_nivel_academico')->where('pena_id_persona', $this->persona)->count());
    }

    // ---- Contactos ----

    public function test_el_correo_y_el_telefono_son_obligatorios_y_siempre_visibles(): void
    {
        $this->guardar(['correo' => ''])->assertSessionHasErrors(['correo' => 'Escribe el correo.']);
        $this->guardar(['correo' => 'no-es-correo'])->assertSessionHasErrors('correo');
        $this->guardar(['telefono' => ['valor' => '']])->assertSessionHasErrors(['telefono.valor' => 'Escribe el teléfono.']);
        $this->guardar(['telefono' => ['valor' => '123']])->assertSessionHasErrors('telefono.valor');
        $this->guardar(['telefono' => ['lada' => '5', 'valor' => '56228300']])->assertSessionHasErrors('telefono.lada');

        // Aunque llegue "no visible", el correo y el teléfono se guardan visibles
        $this->guardar(['correo' => ' Maria.Nunez@UNAM.mx ', 'telefono' => ['lada' => '33', 'valor' => '3312-3456', 'extension' => '12', 'visible' => '0']])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contacto', ['cont_id_persona' => $this->persona, 'cont_id_tipo_contacto' => $this->tipo('Correo'), 'cont_valor' => 'maria.nunez@unam.mx', 'cont_visible' => true]);
        $this->assertDatabaseHas('contacto', ['cont_id_persona' => $this->persona, 'cont_id_tipo_contacto' => $this->tipo('Teléfono'), 'cont_valor' => '33123456', 'cont_lada' => '33', 'cont_extension' => '12', 'cont_visible' => true]);
        $this->assertSame(2, DB::table('contacto')->where('cont_id_persona', $this->persona)->count());

        // La bitácora dice qué tipo cambió, sin el valor
        $detalles = DB::table('bitacora_detalle')->where('bide_id_bitacora', DB::table('bitacora')->max('bita_id_bitacora'))->get();
        $this->assertSame(['Correo', 'Contacto'], $detalles->pluck('bide_campo')->all());
        $this->assertSame([null], $detalles->pluck('bide_nuevo')->unique()->values()->all());
    }

    public function test_los_otros_contactos_llevan_lada_o_extension_segun_su_tipo(): void
    {
        [$telefono, $celular, $correo] = [$this->tipo('Teléfono'), $this->tipo('Celular'), $this->tipo('Correo')];

        $this->guardar(['contactos' => [
            ['tipo' => $celular, 'lada' => '55', 'valor' => '5512-3456', 'visible' => '1'],
            ['tipo' => $correo, 'valor' => ' Maria@FCA.mx ', 'visible' => '0'],
            ['tipo' => $telefono, 'valor' => '', 'lada' => ''], // fila vacía: no cuenta
        ]])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contacto', ['cont_id_persona' => $this->persona, 'cont_id_tipo_contacto' => $celular, 'cont_valor' => '55123456', 'cont_lada' => '55', 'cont_visible' => true]);
        $this->assertDatabaseHas('contacto', ['cont_id_persona' => $this->persona, 'cont_valor' => 'maria@fca.mx', 'cont_visible' => false]);
        $this->assertSame(4, DB::table('contacto')->where('cont_id_persona', $this->persona)->count());

        // Un celular no lleva extensión; un correo no lleva lada; formatos
        $this->guardar(['contactos' => [['tipo' => $celular, 'lada' => '33', 'valor' => '12345678', 'extension' => '1']]])
            ->assertSessionHasErrors(['contactos.0.extension' => 'Contacto 1: un celular no lleva extensión.']);
        $this->guardar(['contactos' => [['tipo' => $correo, 'lada' => '55', 'valor' => 'a@b.mx']]])
            ->assertSessionHasErrors(['contactos.0.lada' => 'Contacto 1: un correo no lleva lada.']);
        $this->guardar(['contactos' => [['tipo' => $celular, 'valor' => '123']]])->assertSessionHasErrors('contactos.0.valor');
        $this->guardar(['contactos' => [['tipo' => $correo, 'valor' => 'no-es-correo']]])->assertSessionHasErrors('contactos.0.valor');
        $this->assertSame(4, DB::table('contacto')->where('cont_id_persona', $this->persona)->count());
    }

    public function test_quitar_un_contacto_y_su_visibilidad_se_ven_en_la_consulta(): void
    {
        $celular = $this->tipo('Celular');
        $this->guardar(['contactos' => [['tipo' => $celular, 'lada' => '33', 'valor' => '12345678', 'visible' => '1'], ['tipo' => $this->tipo('Correo'), 'valor' => 'a@b.mx', 'visible' => '1']]]);

        $this->guardar(['contactos' => [['tipo' => $celular, 'lada' => '33', 'valor' => '87654321', 'visible' => '0']]])->assertSessionHasNoErrors();

        $this->assertSame(['maria@unam.mx', '56228300', '87654321'], DB::table('contacto')->where('cont_id_persona', $this->persona)->orderBy('cont_id_contacto')->pluck('cont_valor')->all());
        $this->actingAs($this->admin)->get("/personas/{$this->persona}?pestana=contactos")->assertSee('(33) 87654321')->assertSee('Oculto')->assertDontSee('a@b.mx');

        // En el formulario, el correo y el teléfono van en sus campos; el celular, en "Otros contactos"
        $this->actingAs($this->admin)->get("/personas/{$this->persona}/edit")->assertOk()
            ->assertSee('name="correo"', false)->assertSee('value="maria@unam.mx"', false)
            ->assertSee('value="56228300"', false)->assertSee('value="87654321"', false);
    }

    public function test_sin_cambios_no_se_escribe_en_la_bitacora(): void
    {
        $antes = DB::table('bitacora')->count();
        $this->guardar([])->assertSessionHasNoErrors();
        $this->assertSame($antes, DB::table('bitacora')->count());
    }

    public function test_la_consulta_es_de_solo_lectura_y_editar_abre_el_formulario_lleno(): void
    {
        $this->actingAs($this->admin)->get("/personas/{$this->persona}")->assertOk()
            ->assertSee('href="'.route('personas.edit', $this->persona).'"', false)
            ->assertDontSee('name="nombre"', false);

        $this->actingAs($this->admin)->get("/personas/{$this->persona}/edit")->assertOk()
            ->assertSee('value="María José"', false)
            ->assertSee('Agregar contacto');
    }

    public function test_sin_el_privilegio_responde_403(): void
    {
        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $this->admin->usua_id_rol)
            ->where('ropr_id_privilegio', DB::table('privilegio')->where('priv_nombre', Privilegio::GestionarPersonas->value)->value('priv_id_privilegio'))
            ->update(['ropr_activo' => false]);

        $this->actingAs($this->admin)->get('/personas')->assertForbidden();
        $this->actingAs($this->admin)->get("/personas/{$this->persona}")->assertForbidden();
        $this->actingAs($this->admin)->get("/personas/{$this->persona}/edit")->assertForbidden();
        $this->guardar([])->assertForbidden();
    }

    // ---- Ayudas ----

    /**
     * El formulario completo, como lo envía la pantalla, con estos cambios.
     *
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return $cambios + ['nombre' => 'María José', 'primer_apellido' => 'Núñez', 'segundo_apellido' => 'Álvarez', 'curp' => '', 'genero' => 'F',
            'pertenencia' => 'organizacion', 'organizacion' => DB::table('organizacion')->value('orga_id_organizacion'), 'grados' => [],
            'correo' => 'maria@unam.mx', 'telefono' => ['lada' => '55', 'valor' => '56228300', 'extension' => ''], 'contactos' => []];
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function guardar(array $cambios, ?int $persona = null): TestResponse
    {
        return $this->actingAs($this->admin)->put('/personas/'.($persona ?? $this->persona), $this->datos($cambios));
    }

    /**
     * @return array<int, string>
     */
    private function filas(string $direccion): array
    {
        preg_match_all('/class="tabla__principal tabla__enlace">([^<]*)</', $this->actingAs($this->admin)->get($direccion)->assertOk()->getContent(), $coincidencias);

        return array_map(fn ($t) => html_entity_decode(trim($t)), $coincidencias[1]);
    }

    private function tipo(string $nombre): int
    {
        return (int) DB::table('tipo_contacto')->where('tico_nombre', $nombre)->value('tico_id_tipo_contacto');
    }

    private function institucion(string $numero = '1000001'): int
    {
        $id = $this->crearInstitucion('Afiliada');
        DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $id)->update(['ined_num_afiliacion' => $numero]);
        DB::table('institucion_nombre')->insert(['instn_id_institucion' => $id, 'instn_nombre' => "Facultad {$numero}", 'instn_fecha_inicio' => '2000-01-01']);

        return $id;
    }

    /**
     * La deja en los directorios con un cargo que ya terminó: para designarla
     * pertenece un momento a una organización y luego se le regresa la suya.
     */
    private function enDirectorio(int $persona): void
    {
        $antes = DB::table('persona')->where('pers_id_persona', $persona)->first(['pers_id_institucion_educativa', 'pers_id_organizacion']);
        $organizacion = DB::table('organizacion')->value('orga_id_organizacion') ?? DB::table('organizacion')->insertGetId([
            'orga_nombre' => 'Organización de prueba',
            'orga_id_domicilio' => DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $this->crearInstitucion('Observadora'))->value('ined_id_domicilio'),
            'orga_activo' => true,
        ], 'orga_id_organizacion');

        DB::table('persona')->where('pers_id_persona', $persona)->update(['pers_id_institucion_educativa' => null, 'pers_id_organizacion' => $organizacion]);
        // Un año distinto para cada una: un puesto no tiene dos titulares a la vez
        $puesto = $this->puestoNacional();
        $anio = 1980 + DB::table('designacion')->where('desi_id_puesto', $puesto)->count();
        DB::table('designacion')->insert(['desi_id_puesto' => $puesto, 'desi_id_persona' => $persona, 'desi_fecha_inicio' => "{$anio}-01-01", 'desi_fecha_fin' => "{$anio}-12-31"]);
        DB::table('persona')->where('pers_id_persona', $persona)->update(['pers_id_institucion_educativa' => $antes->pers_id_institucion_educativa, 'pers_id_organizacion' => $antes->pers_id_organizacion]);
    }

    /**
     * Para designarla, pertenece un momento a la organización de prueba (la base lo pide) y luego se le regresa la suya.
     */
    private function conPertenenciaTemporal(int $persona, \Closure $hacer): void
    {
        $antes = DB::table('persona')->where('pers_id_persona', $persona)->first(['pers_id_institucion_educativa', 'pers_id_organizacion']);
        DB::table('persona')->where('pers_id_persona', $persona)->update(['pers_id_institucion_educativa' => null, 'pers_id_organizacion' => DB::table('organizacion')->value('orga_id_organizacion')]);
        $hacer();
        DB::table('persona')->where('pers_id_persona', $persona)->update(['pers_id_institucion_educativa' => $antes->pers_id_institucion_educativa, 'pers_id_organizacion' => $antes->pers_id_organizacion]);
    }

    private function nombreDe(int $persona): string
    {
        $p = DB::table('persona')->where('pers_id_persona', $persona)->first();

        return trim("{$p->pers_nombre} {$p->pers_primer_apellido} ".($p->pers_segundo_apellido ?? ''));
    }

    private function puestoNacional(): int
    {
        $cargo = (int) (DB::table('cargo')->where('carg_nombre_m', 'Presidente')->value('carg_id_cargo')
            ?? DB::table('cargo')->insertGetId(['carg_nombre_m' => 'Presidente', 'carg_nombre_f' => 'Presidenta', 'carg_nivel' => 'Nacional', 'carg_activo' => true], 'carg_id_cargo'));

        return (int) (DB::table('puesto')->where('pues_id_cargo', $cargo)->whereNull('pues_id_institucion_educativa')->value('pues_id_puesto')
            ?? DB::table('puesto')->insertGetId(['pues_id_cargo' => $cargo], 'pues_id_puesto'));
    }
}

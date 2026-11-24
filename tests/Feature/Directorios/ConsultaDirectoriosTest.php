<?php

namespace Tests\Feature\Directorios;

use App\Enums\Privilegio;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogosInicialesSeeder;
use Database\Seeders\GradosAcademicosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\Support\CreaInstituciones;
use Tests\TestCase;

/**
 * Consultar directorios (M10): cada directorio como está hoy, con sus filtros,
 * los correos para copiar y el CSV. Una persona oculta aparece como
 * "Vacante" y solo se muestran los contactos visibles, en pantalla y en el CSV.
 */
class ConsultaDirectoriosTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    private Usuario $admin;

    /** @var array<string, int> */
    private array $directorios;

    private int $organizacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosInicialesSeeder::class);
        $this->seed(GradosAcademicosSeeder::class);
        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $this->directorios = DB::table('tipo_directorio')->pluck('tidi_id_tipo_directorio', 'tidi_nombre')->map(fn ($id) => (int) $id)->all();
        $this->organizacion = (int) DB::table('organizacion')->insertGetId([
            'orga_nombre' => 'CACECA de prueba',
            'orga_id_domicilio' => $this->domicilio(),
            'orga_activo' => true,
        ], 'orga_id_organizacion');
    }

    public function test_el_consejo_nacional_en_su_orden_con_grado_contactos_visibles_y_vacantes(): void
    {
        $presidente = $this->enDirectorio('Consejo Nacional Directivo', $this->puesto('Presidente', 'Presidenta', 'Nacional'));
        $this->enDirectorio('Consejo Nacional Directivo', $this->puesto('Vicepresidente', 'Vicepresidenta', 'Nacional'));
        $rosa = $this->persona('Rosa', 'F', 'Doctorado');
        $this->contacto($rosa, 'Correo', 'rosa@anfeca.org');
        $this->contacto($rosa, 'Teléfono', '56228300', '55', '123');
        $this->contacto($rosa, 'Correo', 'privado@anfeca.org', visible: false);
        $this->designar($presidente, $rosa, now()->subYear()->format('Y').'-03-01');

        $this->actingAs($this->admin)->get('/directorios')->assertOk()
            ->assertSeeInOrder(['Presidenta', 'Dra. Rosa Gómez', 'CACECA de prueba', '(55) 56228300 ext. 123', 'rosa@anfeca.org', 'Vicepresidente', 'Vacante'])
            ->assertDontSee('privado@anfeca.org')
            // Copiar correos: los de todo el directorio, separados por "; "
            ->assertSee('>rosa@anfeca.org</textarea>', false);
    }

    public function test_solo_muestra_a_quien_ocupa_el_puesto_hoy(): void
    {
        $presidente = $this->enDirectorio('Consejo Nacional Directivo', $this->puesto('Presidente', 'Presidenta', 'Nacional'));
        $vicepresidente = $this->enDirectorio('Consejo Nacional Directivo', $this->puesto('Vicepresidente', 'Vicepresidenta', 'Nacional'));
        $this->designar($presidente, $this->persona('Rosa', 'F'), '2020-01-01', now()->subDay()->toDateString());
        $this->designar($presidente, $this->persona('Luis', 'M'), now()->toDateString());
        // Una designación que empieza mañana todavía no cuenta
        $this->designar($vicepresidente, $this->persona('Eva', 'F'), now()->addDay()->toDateString());

        $this->actingAs($this->admin)->get('/directorios')->assertOk()
            ->assertSee('Luis Gómez')->assertDontSee('Rosa Gómez')->assertDontSee('Eva Gómez')
            ->assertDontSee('name="anio"', false);
    }

    public function test_una_persona_oculta_es_vacante_en_pantalla_y_en_el_csv(): void
    {
        $presidente = $this->enDirectorio('Consejo Nacional Directivo', $this->puesto('Presidente', 'Presidenta', 'Nacional'));
        $rosa = $this->persona('Rosa', 'F');
        $this->contacto($rosa, 'Correo', 'rosa@anfeca.org');
        $this->designar($presidente, $rosa, '2020-01-01');
        DB::table('persona')->where('pers_id_persona', $rosa)->update(['pers_oculta_admin' => true]);

        $this->actingAs($this->admin)->get('/directorios')->assertOk()->assertSee('Vacante')->assertDontSee('Rosa Gómez')->assertDontSee('rosa@anfeca.org');

        $csv = $this->actingAs($this->admin)->get('/directorios/csv')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}Puesto,Grado,Nombre,", $csv);
        $this->assertStringContainsString('Presidente,,Vacante,,,', $csv);
        $this->assertStringNotContainsString('Rosa', $csv);
    }

    public function test_el_csv_trae_todo_lo_filtrado_y_no_deja_formulas(): void
    {
        $zona1 = $this->zona(1);
        $zona2 = $this->zona(2);
        $director1 = $this->enDirectorio('Consejos Regionales', $this->puesto('Director Regional', 'Directora Regional', 'Regional', ['pues_id_zona_regional' => $zona1]));
        $this->enDirectorio('Consejos Regionales', $this->puesto('Director Regional', 'Directora Regional', 'Regional', ['pues_id_zona_regional' => $zona2]));
        $persona = $this->persona('=HYPERLINK', 'M');
        $this->designar($director1, $persona, '2020-01-01');

        // Filtro por zona en pantalla
        $regionales = $this->directorios['Consejos Regionales'];
        $this->actingAs($this->admin)->get("/directorios?directorio={$regionales}&zona={$zona1}")->assertOk()
            ->assertSee('Zona 1 · Noroeste')->assertSee('Mostrando 1–1 de 1');

        $csv = $this->actingAs($this->admin)->get("/directorios/csv?directorio={$regionales}&zona={$zona1}")->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=consejos-regionales-'.now()->format('Y-m-d').'.csv')
            ->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK Gómez", $csv);
        $this->assertStringNotContainsString('Zona 2', $csv);
    }

    public function test_coordinaciones_se_filtran_por_coordinacion(): void
    {
        $historia = (int) DB::table('coordinacion_nacional')->where('cona_nombre', 'Historia')->value('cona_id_coordinacion_nacional');
        $posgrado = (int) DB::table('coordinacion_nacional')->where('cona_nombre', 'Posgrado')->value('cona_id_coordinacion_nacional');
        $this->enDirectorio('Coordinaciones Nacionales', $this->puesto('Coordinador Nacional', 'Coordinadora Nacional', 'Nacional', ['pues_id_coordinacion_nacional' => $historia]));
        $this->enDirectorio('Coordinaciones Nacionales', $this->puesto('Coordinador Nacional', 'Coordinadora Nacional', 'Nacional', ['pues_id_coordinacion_nacional' => $posgrado]));

        $coordinaciones = $this->directorios['Coordinaciones Nacionales'];
        $this->actingAs($this->admin)->get("/directorios?directorio={$coordinaciones}&coordinacion={$historia}")->assertOk()
            ->assertSee('Coordinador Nacional · Historia')->assertDontSee('Coordinador Nacional · Posgrado');
    }

    public function test_instituciones_afiliadas_y_observadoras_de_hoy_con_su_titular(): void
    {
        $anio = (int) now()->format('Y');
        $afiliada = $this->institucion('Afiliada', 'Facultad de Contaduría', "{$anio}-01-01");
        $this->institucion('Observadora', 'Instituto Observador', "{$anio}-01-01");
        $this->institucion('Afiliada', 'Facultad que ya salió', '2010-01-01', '2015-12-31');
        $this->institucion('Matriz', 'Universidad Matriz', "{$anio}-01-01");

        $director = (int) DB::table('puesto')->insertGetId(['pues_id_cargo' => $this->cargo('Director', 'Directora', 'Institucional'), 'pues_id_institucion_educativa' => $afiliada, 'pues_titularidad' => true], 'pues_id_puesto');
        $ana = $this->persona('Ana', 'F', 'Maestría', institucion: $afiliada);
        $this->contacto($ana, 'Correo', 'ana@fca.mx');
        $this->designar($director, $ana, '2022-01-01');

        $instituciones = $this->directorios['Instituciones'];
        $this->actingAs($this->admin)->get("/directorios?directorio={$instituciones}")->assertOk()
            ->assertSeeInOrder(['Facultad de Contaduría', 'Mtra. Ana Gómez', 'Directora', 'ana@fca.mx', 'Instituto Observador', 'Vacante'])
            ->assertDontSee('Facultad que ya salió')->assertDontSee('Universidad Matriz');

        $this->actingAs($this->admin)->get("/directorios?directorio={$instituciones}&participacion=Observadora")->assertOk()
            ->assertSee('Instituto Observador')->assertDontSee('Facultad de Contaduría</span>', false);

        $csv = $this->actingAs($this->admin)->get("/directorios/csv?directorio={$instituciones}")->assertOk()->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}\"No. de afiliación\",Institución,", $csv);
        $this->assertStringContainsString('Facultad de Contaduría', $csv);
    }

    public function test_sin_el_privilegio_responde_403(): void
    {
        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $this->admin->usua_id_rol)
            ->where('ropr_id_privilegio', DB::table('privilegio')->where('priv_nombre', Privilegio::VerDirectorios->value)->value('priv_id_privilegio'))
            ->update(['ropr_activo' => false]);

        $this->actingAs($this->admin)->get('/directorios')->assertForbidden();
        $this->actingAs($this->admin)->get('/directorios/csv')->assertForbidden();
    }

    // ---- Ayudas ----

    /** @param  array<string, int>  $ambito */
    private function puesto(string $masculino, string $femenino, string $nivel, array $ambito = []): int
    {
        return (int) DB::table('puesto')->insertGetId(['pues_id_cargo' => $this->cargo($masculino, $femenino, $nivel)] + $ambito, 'pues_id_puesto');
    }

    private function cargo(string $masculino, string $femenino, string $nivel): int
    {
        return (int) (DB::table('cargo')->where('carg_nombre_m', $masculino)->value('carg_id_cargo')
            ?? DB::table('cargo')->insertGetId(['carg_nombre_m' => $masculino, 'carg_nombre_f' => $femenino, 'carg_nivel' => $nivel, 'carg_activo' => true], 'carg_id_cargo'));
    }

    private function enDirectorio(string $directorio, int $puesto): int
    {
        $tipo = $this->directorios[$directorio];
        $orden = (int) DB::table('puesto_directorio')->where('pudi_id_tipo_directorio', $tipo)->max('pudi_orden') + 1;
        DB::table('puesto_directorio')->insert(['pudi_id_puesto' => $puesto, 'pudi_id_tipo_directorio' => $tipo, 'pudi_orden' => $orden]);

        return $puesto;
    }

    private function persona(string $nombre, string $genero, ?string $grado = null, ?int $institucion = null): int
    {
        $id = (int) DB::table('persona')->insertGetId([
            'pers_nombre' => $nombre, 'pers_primer_apellido' => 'Gómez', 'pers_genero' => $genero,
            'pers_id_institucion_educativa' => $institucion, 'pers_id_organizacion' => $institucion ? null : $this->organizacion,
        ], 'pers_id_persona');
        if ($grado) {
            DB::table('persona_nivel_academico')->insert(['pena_id_persona' => $id, 'pena_id_nivel_academico' => DB::table('nivel_academico')->where('niac_nombre', $grado)->value('niac_id_nivel_academico')]);
        }

        return $id;
    }

    private function contacto(int $persona, string $tipo, string $valor, ?string $lada = null, ?string $extension = null, bool $visible = true): void
    {
        DB::table('contacto')->insert([
            'cont_id_persona' => $persona, 'cont_id_tipo_contacto' => DB::table('tipo_contacto')->where('tico_nombre', $tipo)->value('tico_id_tipo_contacto'),
            'cont_valor' => $valor, 'cont_lada' => $lada, 'cont_extension' => $extension, 'cont_visible' => $visible,
        ]);
    }

    private function designar(int $puesto, int $persona, string $desde, ?string $hasta = null): void
    {
        DB::table('designacion')->insert(['desi_id_puesto' => $puesto, 'desi_id_persona' => $persona, 'desi_fecha_inicio' => $desde, 'desi_fecha_fin' => $hasta]);
    }

    private function institucion(string $participacion, string $nombre, string $desde, ?string $hasta = null): int
    {
        $id = $this->crearInstitucion($participacion);
        DB::table('institucion_nombre')->insert(['instn_id_institucion' => $id, 'instn_nombre' => $nombre, 'instn_fecha_inicio' => '2000-01-01']);
        DB::table('institucion_participacion')->insert(['inpa_id_institucion' => $id, 'inpa_tipo' => $participacion, 'inpa_fecha_inicio' => $desde, 'inpa_fecha_fin' => $hasta]);

        return $id;
    }

    private function zona(int $numero): int
    {
        return (int) DB::table('zona_regional')->where('zore_numero', $numero)->value('zore_id_zona_regional');
    }

    /** Un domicilio de prueba (el de una institución sin historial, que no sale en ningún directorio). */
    private function domicilio(): int
    {
        return (int) DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $this->crearInstitucion('Observadora'))->value('ined_id_domicilio');
    }
}

<?php

namespace Tests\Feature\Puestos;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogosInicialesSeeder;
use Database\Seeders\GradosAcademicosSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\Support\CreaInstituciones;
use Tests\TestCase;

/**
 * Puesto de una organización (Director General de CACECA): un puesto
 * nacional que lleva organización (no junto con coordinación). Solo lo
 * ocupa alguien de esa organización; al designar desde la ficha se usa ese
 * puesto. Lo revisan la aplicación y la base.
 */
class PuestoDeOrganizacionTest extends TestCase
{
    use CreaCuentas;
    use CreaInstituciones;
    use DatabaseTransactions;

    private Usuario $admin;

    private int $caceca;

    private int $otra;

    private int $cargo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogosInicialesSeeder::class);
        $this->seed(GradosAcademicosSeeder::class);
        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $domicilio = (int) DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $this->crearInstitucion('Observadora'))->value('ined_id_domicilio');
        $this->caceca = (int) DB::table('organizacion')->insertGetId(['orga_nombre' => 'CACECA', 'orga_id_domicilio' => $domicilio, 'orga_activo' => true], 'orga_id_organizacion');
        $this->otra = (int) DB::table('organizacion')->insertGetId(['orga_nombre' => 'IMCP', 'orga_id_domicilio' => $domicilio, 'orga_activo' => true], 'orga_id_organizacion');
        $this->cargo = (int) DB::table('cargo')->insertGetId(['carg_nombre_m' => 'Director General CACECA', 'carg_nombre_f' => 'Directora General CACECA', 'carg_nivel' => 'Nacional', 'carg_activo' => true], 'carg_id_cargo');
    }

    public function test_registrar_el_puesto_de_una_organizacion(): void
    {
        $historia = (int) DB::table('coordinacion_nacional')->where('cona_nombre', 'Historia')->value('cona_id_coordinacion_nacional');

        // Coordinación y organización a la vez, no
        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargo, 'coordinacion' => $historia, 'organizacion' => $this->caceca])
            ->assertSessionHasErrors(['organizacion' => 'Un puesto nacional lleva coordinación u organización, no las dos.']);

        $this->actingAs($this->admin)->post('/puestos', ['cargo' => $this->cargo, 'organizacion' => $this->caceca])
            ->assertSessionHasNoErrors()
            // El cargo ya dice su organización: no se repite
            ->assertSessionHas('estado', 'Se registró el puesto Director General CACECA.');
        $this->assertDatabaseHas('puesto', ['pues_id_cargo' => $this->cargo, 'pues_id_organizacion' => $this->caceca]);

        $this->actingAs($this->admin)->get('/puestos')->assertOk()->assertSee('<span class="tabla__detalle">', false)->assertSee('CACECA');
    }

    public function test_solo_alguien_de_la_organizacion_lo_ocupa(): void
    {
        $puesto = (int) DB::table('puesto')->insertGetId(['pues_id_cargo' => $this->cargo, 'pues_id_organizacion' => $this->caceca], 'pues_id_puesto');

        // Alguien de otra organización: la aplicación lo explica
        $this->actingAs($this->admin)->post('/personas', $this->persona('Luis', $this->otra))
            ->assertSessionHasErrors(['designaciones.0.cargo' => 'Designación 1: ese cargo es de CACECA; solo lo ocupa alguien que pertenezca a esa organización.']);

        // Alguien de CACECA: ocupa ese mismo puesto (no se crea otro)
        $this->actingAs($this->admin)->post('/personas', $this->persona('Rosa', $this->caceca))->assertSessionHasNoErrors();
        $rosa = (int) DB::table('persona')->where('pers_nombre', 'Rosa')->value('pers_id_persona');
        $this->assertSame($puesto, (int) DB::table('designacion')->where('desi_id_persona', $rosa)->value('desi_id_puesto'));
        $this->assertSame(1, DB::table('puesto')->where('pues_id_cargo', $this->cargo)->count());

        // La base tampoco lo permite
        $luis = (int) DB::table('persona')->insertGetId(['pers_nombre' => 'Luis', 'pers_primer_apellido' => 'Gómez', 'pers_genero' => 'M', 'pers_id_organizacion' => $this->otra], 'pers_id_persona');
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('La persona debe pertenecer a la organización del puesto.');
        DB::table('designacion')->insert(['desi_id_puesto' => $puesto, 'desi_id_persona' => $luis, 'desi_fecha_inicio' => '2010-01-01', 'desi_fecha_fin' => '2010-12-31']);
    }

    /** @return array<string, mixed> */
    private function persona(string $nombre, int $organizacion): array
    {
        return [
            'nombre' => $nombre, 'primer_apellido' => 'Gómez', 'segundo_apellido' => '', 'curp' => '', 'genero' => 'F',
            'pertenencia' => 'organizacion', 'organizacion' => $organizacion, 'grados' => [],
            'correo' => mb_strtolower($nombre).'@caceca.org', 'telefono' => ['lada' => '55', 'valor' => '56228300', 'extension' => ''], 'contactos' => [],
            'designaciones' => [['cargo' => $this->cargo, 'fecha_inicio' => '2024-01-01']],
        ];
    }
}

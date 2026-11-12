<?php

namespace Tests\Feature\Catalogos;

use App\Enums\Privilegio;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\GradosAcademicosSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Catálogo de grados académicos (M3; tabla NIVEL_ACADEMICO): registrar, editar, desactivar y borrar desde la lista.
 */
class GradosAcademicosTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
    }

    public function test_la_lista_muestra_nombre_abreviaturas_y_personas(): void
    {
        $this->grado('Doctorado', 'Dr.', 'Dra.');

        $this->actingAs($this->admin)->get('/grados-academicos')
            ->assertOk()
            ->assertSeeText('Doctorado')
            ->assertSeeText('Dra.')
            ->assertSee('data-etiqueta="Personas">0<', false);
    }

    public function test_sin_el_privilegio_responde_403(): void
    {
        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $this->admin->usua_id_rol)
            ->where('ropr_id_privilegio', DB::table('privilegio')->where('priv_nombre', Privilegio::GestionarGrados->value)->value('priv_id_privilegio'))
            ->update(['ropr_activo' => false]);

        $this->actingAs($this->admin)->get('/grados-academicos')->assertForbidden();
    }

    public function test_registrar_y_editar_con_su_bitacora(): void
    {
        $this->actingAs($this->admin)->from('/grados-academicos')
            ->post('/grados-academicos', ['nombre' => 'Maestría', 'masculina' => 'Mtro.', 'femenina' => 'Mtra.'])
            ->assertRedirect('/grados-academicos')
            ->assertSessionHas('estado', 'Se registró el grado Maestría.');

        $id = (int) DB::table('nivel_academico')->where('niac_nombre', 'Maestría')->value('niac_id_nivel_academico');
        $this->assertSame('Registro', $this->ultimaBitacora()->bita_accion);

        $this->actingAs($this->admin)->from('/grados-academicos')
            ->put("/grados-academicos/{$id}", ['nombre' => 'Maestría', 'masculina' => 'M.', 'femenina' => 'Mtra.'])
            ->assertSessionHasNoErrors();

        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Modificacion', 'NIVEL_ACADEMICO'], [$bitacora->bita_accion, $bitacora->bita_tabla]);
        $this->assertSame(
            [['Abreviatura masculina', 'Mtro.', 'M.']],
            DB::table('bitacora_detalle')->where('bide_id_bitacora', $bitacora->bita_id_bitacora)->get()
                ->map(fn (object $d) => [$d->bide_campo, $d->bide_anterior, $d->bide_nuevo])->all(),
        );
    }

    public function test_el_nombre_no_se_repite_y_todo_es_obligatorio(): void
    {
        $this->grado('Doctorado', 'Dr.', 'Dra.');

        $this->actingAs($this->admin)->post('/grados-academicos', ['nombre' => 'DOCTORADO', 'masculina' => 'D', 'femenina' => 'D'])
            ->assertSessionHasErrors(['nombre' => 'Ya existe el grado «Doctorado».']);
        $this->actingAs($this->admin)->post('/grados-academicos', ['nombre' => '', 'masculina' => '', 'femenina' => str_repeat('x', 11)])
            ->assertSessionHasErrors(['nombre', 'masculina', 'femenina']);
    }

    public function test_con_personas_no_se_borra_y_sin_personas_si(): void
    {
        $usado = $this->grado('Doctorado', 'Dr.', 'Dra.');
        $libre = $this->grado('Ingeniería', 'Ing.', 'Ing.');
        DB::table('persona_nivel_academico')->insert(['pena_id_persona' => $this->admin->usua_id_persona, 'pena_id_nivel_academico' => $usado]);

        $this->actingAs($this->admin)->get('/grados-academicos')
            ->assertSee('No se puede borrar porque 1 persona tiene este grado.');

        $this->actingAs($this->admin)->delete("/grados-academicos/{$usado}", ['motivo' => 'Duplicado'])->assertSessionHas('aviso');
        $this->assertDatabaseHas('nivel_academico', ['niac_id_nivel_academico' => $usado]);

        $this->actingAs($this->admin)->delete("/grados-academicos/{$libre}", ['motivo' => 'Error de captura'])
            ->assertSessionHas('estado', 'Se borró el grado Ingeniería.');
        $this->assertDatabaseMissing('nivel_academico', ['niac_id_nivel_academico' => $libre]);
        $this->assertSame(['Eliminacion', 'Error de captura'], [$this->ultimaBitacora()->bita_accion, $this->ultimaBitacora()->bita_motivo]);
    }

    public function test_desactivar_conserva_el_grado_de_las_personas(): void
    {
        $id = $this->grado('Doctorado', 'Dr.', 'Dra.');
        DB::table('persona_nivel_academico')->insert(['pena_id_persona' => $this->admin->usua_id_persona, 'pena_id_nivel_academico' => $id]);

        $this->actingAs($this->admin)->from('/grados-academicos')->patch("/grados-academicos/{$id}/estado", ['activa' => '0'])
            ->assertSessionHas('estado', 'Se desactivó el grado Doctorado. Las personas que ya lo tienen lo conservan.');

        $this->assertDatabaseHas('nivel_academico', ['niac_id_nivel_academico' => $id, 'niac_activo' => false]);
        $this->assertDatabaseHas('persona_nivel_academico', ['pena_id_nivel_academico' => $id]);
    }

    public function test_el_inicio_nombra_el_grado_en_la_bitacora(): void
    {
        $superadmin = $this->crearCuenta(Rol::SUPERADMINISTRADOR, [], ['pers_nombre' => 'Marta', 'pers_primer_apellido' => 'Ruiz']);

        $this->actingAs($superadmin)->post('/grados-academicos', ['nombre' => 'Doctorado', 'masculina' => 'Dr.', 'femenina' => 'Dra.']);

        $this->actingAs($superadmin)->get('/inicio')->assertSeeText('Marta Ruiz registró el grado académico Doctorado.');
    }

    public function test_el_seeder_carga_los_tres_grados_basicos_sin_duplicar(): void
    {
        $this->grado('MAESTRÍA', 'M.', 'M.'); // ya existía, con otras mayúsculas

        $this->seed(GradosAcademicosSeeder::class);
        $this->seed(GradosAcademicosSeeder::class); // repetible

        $this->assertSame(['Doctorado', 'Licenciatura', 'MAESTRÍA'], DB::table('nivel_academico')->orderBy('niac_nombre')->pluck('niac_nombre')->all());
        $this->assertDatabaseHas('nivel_academico', ['niac_nombre' => 'Doctorado', 'niac_abr_m' => 'Dr.', 'niac_abr_f' => 'Dra.', 'niac_activo' => true]);

        // Se pueden borrar como cualquier otro
        $id = (int) DB::table('nivel_academico')->where('niac_nombre', 'Licenciatura')->value('niac_id_nivel_academico');
        $this->actingAs($this->admin)->delete("/grados-academicos/{$id}", ['motivo' => 'Decisión de la organización'])->assertSessionHas('estado');
        $this->assertDatabaseMissing('nivel_academico', ['niac_id_nivel_academico' => $id]);
    }

    private function grado(string $nombre, string $masculina, string $femenina): int
    {
        return (int) DB::table('nivel_academico')->insertGetId(
            ['niac_nombre' => $nombre, 'niac_abr_m' => $masculina, 'niac_abr_f' => $femenina],
            'niac_id_nivel_academico',
        );
    }

    private function ultimaBitacora(): object
    {
        return DB::table('bitacora')->orderByDesc('bita_id_bitacora')->first();
    }
}

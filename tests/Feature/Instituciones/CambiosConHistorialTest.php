<?php

namespace Tests\Feature\Instituciones;

use App\Enums\Privilegio;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogosInicialesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Criterios de M4 (instituciones), lote 2: consulta y cambios con historial.
 * - El cambio de razón social cierra el periodo anterior sin traslape.
 * - La participación actual y su historial se actualizan juntas.
 */
class CambiosConHistorialTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private Usuario $admin;

    private int $colonia;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $this->seed(CatalogosInicialesSeeder::class);

        $cdmx = (int) DB::table('entidad_federativa')->where('enfe_nombre', 'Ciudad de México')->value('enfe_id_entidad_federativa');
        $municipio = (int) DB::table('alcaldia_municipio')->insertGetId(['almu_id_entidad_federativa' => $cdmx, 'almu_clave' => '003', 'almu_nombre' => 'Coyoacán'], 'almu_id_alcaldia_municipio');
        $this->colonia = (int) DB::table('colonia')->insertGetId(['colo_id_alcaldia_municipio' => $municipio, 'colo_codigo_postal' => '04510', 'colo_id_asenta_cpcons' => '0001', 'colo_nombre' => 'Ciudad Universitaria'], 'colo_id_colonia');
    }

    // ---- Consulta ----

    public function test_la_consulta_muestra_sus_datos_e_historiales_por_pestanas(): void
    {
        $unam = $this->registrarMatriz();
        $fca = $this->registrarAfiliada($unam);

        $this->actingAs($this->admin)->get("/instituciones/{$unam}")->assertOk()
            ->assertSee('Universidad Nacional Autónoma de México')
            ->assertSee('https://www.unam.mx')
            ->assertSeeInOrder(['General', 'Participación', 'Razón social', 'Sedes'])
            ->assertSee('Facultad de Contaduría y Administración'); // en la pestaña Sedes

        // Una facultad no tiene pestaña de sedes, pero sí su universidad y su domicilio
        $this->actingAs($this->admin)->get("/instituciones/{$fca}?pestana=participacion")->assertOk()
            ->assertDontSee('id="pestana-sedes"', false)
            ->assertSee('Av. Universidad 3000')
            ->assertSee('1 de marzo de 1996 – hoy');
    }

    public function test_la_consulta_de_una_institucion_que_no_existe_da_404(): void
    {
        $this->actingAs($this->admin)->get('/instituciones/999999')->assertNotFound();
    }

    public function test_sin_privilegio_no_se_puede_consultar_ni_cambiar(): void
    {
        $unam = $this->registrarMatriz();
        // Se le quita al rol el privilegio de gestionar instituciones
        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $this->admin->usua_id_rol)
            ->where('ropr_id_privilegio', DB::table('privilegio')->where('priv_nombre', Privilegio::GestionarInstituciones->value)->value('priv_id_privilegio'))
            ->update(['ropr_activo' => false]);

        $this->actingAs($this->admin)->get("/instituciones/{$unam}")->assertForbidden();
        $this->actingAs($this->admin)->patch("/instituciones/{$unam}/razon-social", ['razon_social' => 'Otra', 'desde' => '2020-01-01'])->assertForbidden();
        $this->actingAs($this->admin)->patch("/instituciones/{$unam}/participacion", ['participacion' => 'Observadora', 'desde' => '2020-01-01'])->assertForbidden();
        $this->assertSame(1, DB::table('institucion_nombre')->where('instn_id_institucion', $unam)->count());
    }

    // ---- Cambio de razón social ----

    public function test_el_cambio_de_razon_social_cierra_el_periodo_anterior_sin_traslape(): void
    {
        $unam = $this->registrarMatriz();

        $this->actingAs($this->admin)->patch("/instituciones/{$unam}/razon-social", ['razon_social' => '  Universidad   Nacional de México ', 'desde' => '2001-05-10'])
            ->assertRedirect("/instituciones/{$unam}?pestana=razon-social")
            ->assertSessionHasNoErrors();

        $periodos = DB::table('institucion_nombre')->where('instn_id_institucion', $unam)->orderBy('instn_fecha_inicio')
            ->get()->map(fn ($n) => [$n->instn_nombre, $n->instn_fecha_inicio, $n->instn_fecha_fin])->all();

        // La anterior termina el día previo; la nueva queda vigente (escrita sin espacios de sobra)
        $this->assertSame([
            ['Universidad Nacional Autónoma de México', '1959-01-01', '2001-05-09'],
            ['Universidad Nacional de México', '2001-05-10', null],
        ], $periodos);

        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Modificacion', 'INSTITUCION_EDUCATIVA', $unam], [$bitacora->bita_accion, $bitacora->bita_tabla, (int) $bitacora->bita_referencia]);
        $this->assertSame([
            ['Razón social', 'Universidad Nacional Autónoma de México', 'Universidad Nacional de México'],
            ['Razón social vigente desde', '1959-01-01', '2001-05-10'],
        ], $this->detalles((int) $bitacora->bita_id_bitacora));

        // La lista ya muestra el nombre nuevo
        $this->assertSame(['Universidad Nacional de México'], $this->filas('/instituciones'));
    }

    public function test_dos_cambios_seguidos_dejan_tres_periodos_sin_traslape(): void
    {
        $unam = $this->registrarMatriz();
        $this->cambiarNombre($unam, 'Nombre Dos', '2000-01-01');
        $this->cambiarNombre($unam, 'Nombre Tres', '2010-06-15');

        $fechas = DB::table('institucion_nombre')->where('instn_id_institucion', $unam)->orderBy('instn_fecha_inicio')
            ->get(['instn_fecha_inicio', 'instn_fecha_fin'])->map(fn ($n) => [$n->instn_fecha_inicio, $n->instn_fecha_fin])->all();

        $this->assertSame([['1959-01-01', '1999-12-31'], ['2000-01-01', '2010-06-14'], ['2010-06-15', null]], $fechas);
    }

    public function test_la_fecha_del_cambio_de_razon_social_no_puede_ser_anterior_a_la_vigente_ni_futura(): void
    {
        $unam = $this->registrarMatriz();

        $this->actingAs($this->admin)->patch("/instituciones/{$unam}/razon-social", ['razon_social' => 'Otro nombre', 'desde' => '1950-01-01'])
            ->assertSessionHasErrors(['desde' => 'La fecha no puede ser anterior a cuando empezó la razón social vigente (01/01/1959).']);

        $this->actingAs($this->admin)->patch("/instituciones/{$unam}/razon-social", ['razon_social' => 'Otro nombre', 'desde' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors(['desde' => 'La fecha no puede ser futura.']);

        $this->assertSame(1, DB::table('institucion_nombre')->where('instn_id_institucion', $unam)->count());
    }

    public function test_la_misma_razon_social_o_un_texto_peligroso_se_rechazan(): void
    {
        $unam = $this->registrarMatriz();

        $this->actingAs($this->admin)->patch("/instituciones/{$unam}/razon-social", ['razon_social' => 'UNIVERSIDAD NACIONAL AUTONOMA DE MEXICO', 'desde' => '2001-01-01'])
            ->assertSessionHasErrors(['razon_social' => 'Es la misma razón social que la vigente. Para corregir cómo está escrita, usa Editar.']);

        $this->actingAs($this->admin)->patch("/instituciones/{$unam}/razon-social", ['razon_social' => '<script>alert(1)</script>', 'desde' => '2001-01-01'])
            ->assertSessionHasErrors('razon_social');

        $this->assertSame(1, DB::table('institucion_nombre')->where('instn_id_institucion', $unam)->count());
    }

    public function test_un_cambio_el_mismo_dia_en_que_empezo_solo_corrige_la_vigente(): void
    {
        $unam = $this->registrarMatriz();

        $this->cambiarNombre($unam, 'UNAM corregida', '1959-01-01');

        $this->assertSame([['UNAM corregida', '1959-01-01', null]], DB::table('institucion_nombre')->where('instn_id_institucion', $unam)
            ->get()->map(fn ($n) => [$n->instn_nombre, $n->instn_fecha_inicio, $n->instn_fecha_fin])->all());
    }

    // ---- Cambio de participación: solo de observadora a afiliada ----

    public function test_la_participacion_actual_y_su_historial_se_actualizan_juntas(): void
    {
        $udg = $this->registrarObservadora();

        $this->actingAs($this->admin)->patch("/instituciones/{$udg}/participacion", ['participacion' => 'Afiliada', 'numero' => '1400001', 'desde' => '2020-02-01'])
            ->assertRedirect("/instituciones/{$udg}?pestana=participacion")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('estado', 'Ahora es afiliada desde el 01/02/2020, con el número 1400001.');

        // La actual cambia con su número…
        $this->assertDatabaseHas('institucion_educativa', ['ined_id_institucion_educativa' => $udg, 'ined_participacion' => 'Afiliada', 'ined_num_afiliacion' => '1400001']);
        // …y en la misma operación el periodo anterior se cierra el día previo y se abre el nuevo
        $this->assertSame([
            ['Observadora', '2010-08-15', '2020-01-31'],
            ['Afiliada', '2020-02-01', null],
        ], $this->periodos($udg));

        $bitacora = $this->ultimaBitacora();
        $this->assertSame([
            ['Participación', 'Observadora', 'Afiliada'],
            ['Participa como tal desde', '2010-08-15', '2020-02-01'],
            ['Número de afiliación', null, '1400001'],
        ], $this->detalles((int) $bitacora->bita_id_bitacora));

        // El filtro por año ve ambos periodos
        $this->assertSame(['Universidad de Guadalajara'], $this->filas('/instituciones?participacion=Observadora&anio=2019'));
        $this->assertSame([], $this->filas('/instituciones?participacion=Observadora&anio=2021'));
        $this->assertSame(['Universidad de Guadalajara'], $this->filas('/instituciones?participacion=Afiliada&anio=2021'));
    }

    public function test_una_afiliada_jamas_vuelve_a_ser_observadora_y_una_matriz_no_cambia(): void
    {
        $unam = $this->registrarMatriz();
        $fca = $this->registrarAfiliada($unam);

        foreach ([[$fca, 'Observadora'], [$fca, 'Matriz'], [$unam, 'Afiliada'], [$unam, 'Observadora']] as [$id, $participacion]) {
            $this->actingAs($this->admin)->patch("/instituciones/{$id}/participacion", ['participacion' => $participacion, 'numero' => '1400001', 'desde' => '2020-01-01'])
                ->assertSessionHasErrors('participacion');
        }

        $this->assertSame([['Afiliada', '1996-03-01', null]], $this->periodos($fca));
        $this->assertSame([['Matriz', '1959-01-01', null]], $this->periodos($unam));
        $this->assertSame('2607002', DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $fca)->value('ined_num_afiliacion'));

        // Ni siquiera se ofrece el botón
        $this->actingAs($this->admin)->get("/instituciones/{$fca}?pestana=participacion")->assertDontSee('Pasar a afiliada');
    }

    public function test_una_observadora_no_puede_volverse_matriz(): void
    {
        $udg = $this->registrarObservadora();

        $this->actingAs($this->admin)->patch("/instituciones/{$udg}/participacion", ['participacion' => 'Matriz', 'desde' => '2020-01-01'])
            ->assertSessionHasErrors(['participacion' => 'Solo una observadora puede pasar a afiliada.']);
        $this->assertSame([['Observadora', '2010-08-15', null]], $this->periodos($udg));
    }

    public function test_al_volverse_afiliada_necesita_numero_de_7_digitos_sin_repetir(): void
    {
        $unam = $this->registrarMatriz();
        $this->registrarAfiliada($unam);
        $udg = $this->registrarObservadora();

        $this->actingAs($this->admin)->patch("/instituciones/{$udg}/participacion", ['participacion' => 'Afiliada', 'numero_modo' => 'manual', 'desde' => '2020-01-01'])
            ->assertSessionHasErrors(['numero' => 'Una afiliada necesita su número de afiliación de 7 dígitos.']);
        $this->actingAs($this->admin)->patch("/instituciones/{$udg}/participacion", ['participacion' => 'Afiliada', 'numero' => '123', 'desde' => '2020-01-01'])
            ->assertSessionHasErrors(['numero' => 'El número de afiliación tiene exactamente 7 dígitos.']);
        $this->actingAs($this->admin)->patch("/instituciones/{$udg}/participacion", ['participacion' => 'Afiliada', 'numero' => '2607002', 'desde' => '2020-01-01'])
            ->assertSessionHasErrors(['numero' => 'El número 2607002 ya pertenece a otra institución.']);

        $this->assertSame([['Observadora', '2010-08-15', null]], $this->periodos($udg));
    }

    public function test_la_fecha_no_puede_ser_anterior_al_periodo_actual(): void
    {
        $udg = $this->registrarObservadora();

        $this->actingAs($this->admin)->patch("/instituciones/{$udg}/participacion", ['participacion' => 'Afiliada', 'numero' => '1400001', 'desde' => '2009-01-01'])
            ->assertSessionHasErrors(['desde' => 'La fecha no puede ser anterior a cuando empezó como observadora (15/08/2010).']);
    }

    public function test_una_observadora_desactivada_no_pasa_a_afiliada(): void
    {
        $udg = $this->registrarObservadora();
        $this->actingAs($this->admin)->patch("/instituciones/{$udg}/estado", ['activa' => '0', 'fecha' => '2015-01-01'])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->patch("/instituciones/{$udg}/participacion", ['participacion' => 'Afiliada', 'numero' => '1400001', 'desde' => '2020-01-01'])
            ->assertSessionHasErrors(['participacion' => 'Está desactivada. Actívala primero; después podrás hacerla afiliada.']);

        // La consulta lo explica con el botón gris
        $this->actingAs($this->admin)->get("/instituciones/{$udg}?pestana=participacion")
            ->assertSee('boton--bloqueado', false)
            ->assertSee('Está desactivada. Actívala primero; después podrás hacerla afiliada.');
    }

    public function test_pasar_a_afiliada_el_mismo_dia_en_que_empezo_corrige_ese_periodo(): void
    {
        $udg = $this->registrarObservadora();

        $this->actingAs($this->admin)->patch("/instituciones/{$udg}/participacion", ['participacion' => 'Afiliada', 'numero' => '1400001', 'desde' => '2010-08-15'])
            ->assertSessionHasNoErrors();

        $this->assertSame([['Afiliada', '2010-08-15', null]], $this->periodos($udg));
    }

    public function test_al_pasar_a_afiliada_el_sistema_asigna_el_numero_con_su_ano_y_zona(): void
    {
        $udg = $this->registrarObservadora(); // zona 4

        $this->actingAs($this->admin)->patch("/instituciones/{$udg}/participacion", ['participacion' => 'Afiliada', 'numero_modo' => 'automatico', 'desde' => '2022-03-01'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('estado', 'Ahora es afiliada desde el 01/03/2022, con el número 2204001.');
    }

    // ---- Edición por secciones desde la consulta ----

    public function test_ya_no_hay_pantalla_de_edicion(): void
    {
        $udg = $this->registrarObservadora();

        $this->actingAs($this->admin)->get("/instituciones/{$udg}/edit")->assertNotFound();
    }

    public function test_cada_seccion_solo_cambia_sus_propios_datos(): void
    {
        $udg = $this->registrarObservadora();

        // Sitio web: aunque se envíen otros campos, solo cambia el sitio web
        $this->actingAs($this->admin)->put("/instituciones/{$udg}", ['_seccion' => 'web', 'sitio_web' => 'https://www.udg.mx', 'razon_social' => 'Otro nombre', 'sector' => 'Privada', 'calle' => 'Otra'])
            ->assertRedirect("/instituciones/{$udg}")
            ->assertSessionHasNoErrors();

        $institucion = DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $udg)->first();
        $this->assertSame(['https://www.udg.mx', 'Publica'], [$institucion->ined_sitio_web, $institucion->ined_sector]);
        $this->assertSame('Av. Juárez', DB::table('domicilio')->where('domi_id_domicilio', $institucion->ined_id_domicilio)->value('domi_calle'));
        $this->assertSame([['Sitio web', null, 'https://www.udg.mx']], $this->detalles((int) $this->ultimaBitacora()->bita_id_bitacora));

        // Ubicación: solo domicilio y zona
        $this->actingAs($this->admin)->put("/instituciones/{$udg}", ['_seccion' => 'ubicacion', 'colonia' => $this->colonia, 'calle' => 'Av. Juárez', 'num_ext' => '975', 'zona' => $this->zona(7), 'sitio_web' => ''])
            ->assertSessionHasNoErrors();
        $institucion = DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $udg)->first();
        $this->assertSame(['https://www.udg.mx', $this->zona(7)], [$institucion->ined_sitio_web, (int) $institucion->ined_id_zona_regional]);
        $this->assertSame('975', DB::table('domicilio')->where('domi_id_domicilio', $institucion->ined_id_domicilio)->value('domi_num_ext'));

        // Sitio web vacío: se quita
        $this->actingAs($this->admin)->put("/instituciones/{$udg}", ['_seccion' => 'web', 'sitio_web' => ''])->assertSessionHasNoErrors();
        $this->assertNull(DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $udg)->value('ined_sitio_web'));
    }

    public function test_corregir_la_razon_social_regresa_a_su_pestana_sin_historial(): void
    {
        $udg = $this->registrarObservadora();

        $this->actingAs($this->admin)->put("/instituciones/{$udg}", ['_seccion' => 'razon', 'razon_social' => 'Universidad de Guadalajara (UdeG)'])
            ->assertRedirect("/instituciones/{$udg}?pestana=razon-social")
            ->assertSessionHas('estado', 'Se corrigió la razón social.');

        $this->assertSame([['Universidad de Guadalajara (UdeG)', '2010-08-15', null]], DB::table('institucion_nombre')->where('instn_id_institucion', $udg)
            ->get()->map(fn ($n) => [$n->instn_nombre, $n->instn_fecha_inicio, $n->instn_fecha_fin])->all());
    }

    public function test_una_universidad_con_sedes_o_matriz_no_cambia_de_tipo(): void
    {
        $unam = $this->registrarMatriz();
        $this->registrarAfiliada($unam);
        $udg = $this->registrarObservadora();
        $this->registrar(['tipo' => 'Campus', 'universidad' => $udg, 'participacion' => 'Observadora', 'desde' => '2015-01-01', 'razon_social' => 'Campus Norte', 'sector' => 'Publica',
            'colonia' => $this->colonia, 'calle' => 'Calle 1', 'num_ext' => '1', 'zona' => $this->zona(4)]);

        $this->actingAs($this->admin)->put("/instituciones/{$udg}", ['_seccion' => 'identificacion', 'tipo' => 'Campus', 'universidad' => $unam, 'sector' => 'Publica'])
            ->assertSessionHasErrors(['tipo' => 'Tiene facultades o campus, así que sigue siendo universidad.']);
        $this->actingAs($this->admin)->put("/instituciones/{$unam}", ['_seccion' => 'identificacion', 'tipo' => 'Facultad', 'universidad' => $udg, 'sector' => 'Publica'])
            ->assertSessionHasErrors('tipo');

        $this->assertSame('Universidad', DB::table('institucion_educativa as i')->join('tipo_institucion_educativa as t', 't.tiie_id_tipo_institucion', '=', 'i.ined_id_tipo_institucion')
            ->where('i.ined_id_institucion_educativa', $udg)->value('t.tiie_nombre'));
    }

    // ---- Ayudas ----

    private function registrarMatriz(): int
    {
        return $this->registrar(['tipo' => 'Universidad', 'participacion' => 'Matriz', 'desde' => '1959-01-01', 'razon_social' => 'Universidad Nacional Autónoma de México', 'sector' => 'Publica', 'sitio_web' => 'https://www.unam.mx']);
    }

    private function registrarAfiliada(int $universidad): int
    {
        return $this->registrar(['tipo' => 'Facultad', 'universidad' => $universidad, 'participacion' => 'Afiliada', 'numero' => '2607002', 'desde' => '1996-03-01',
            'razon_social' => 'Facultad de Contaduría y Administración', 'sector' => 'Publica', 'colonia' => $this->colonia, 'calle' => 'Av. Universidad', 'num_ext' => '3000', 'zona' => $this->zona(7)]);
    }

    private function registrarObservadora(): int
    {
        return $this->registrar(['tipo' => 'Universidad', 'participacion' => 'Observadora', 'desde' => '2010-08-15', 'razon_social' => 'Universidad de Guadalajara', 'sector' => 'Publica',
            'colonia' => $this->colonia, 'calle' => 'Av. Juárez', 'num_ext' => '976', 'zona' => $this->zona(4)]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function registrar(array $datos): int
    {
        $this->actingAs($this->admin)->post('/instituciones', $datos)->assertSessionHasNoErrors();

        return (int) DB::table('institucion_nombre')->where('instn_nombre', $datos['razon_social'])->whereNull('instn_fecha_fin')->value('instn_id_institucion');
    }

    private function cambiarNombre(int $id, string $nombre, string $desde): void
    {
        $this->actingAs($this->admin)->patch("/instituciones/{$id}/razon-social", ['razon_social' => $nombre, 'desde' => $desde])->assertSessionHasNoErrors();
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: ?string}>
     */
    private function periodos(int $id): array
    {
        return DB::table('institucion_participacion')->where('inpa_id_institucion', $id)->orderBy('inpa_fecha_inicio')
            ->get()->map(fn ($p) => [$p->inpa_tipo, $p->inpa_fecha_inicio, $p->inpa_fecha_fin])->all();
    }

    /**
     * Nombres de las instituciones que aparecen en la lista (solo las filas de la tabla).
     *
     * @return array<int, string>
     */
    private function filas(string $direccion): array
    {
        preg_match_all('/class="tabla__principal[^"]*">([^<]*)</', $this->actingAs($this->admin)->get($direccion)->assertOk()->getContent(), $coincidencias);

        return array_map('trim', $coincidencias[1]);
    }

    private function zona(int $numero): int
    {
        return (int) DB::table('zona_regional')->where('zore_numero', $numero)->value('zore_id_zona_regional');
    }

    private function ultimaBitacora(): object
    {
        return DB::table('bitacora')->orderByDesc('bita_id_bitacora')->first();
    }

    /**
     * @return array<int, array{0: string, 1: ?string, 2: ?string}>
     */
    private function detalles(int $idBitacora): array
    {
        return DB::table('bitacora_detalle')->where('bide_id_bitacora', $idBitacora)->orderBy('bide_id_detalle')->get()
            ->map(fn (object $d) => [$d->bide_campo, $d->bide_anterior, $d->bide_nuevo])->all();
    }
}

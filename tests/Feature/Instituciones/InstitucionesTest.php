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
 * Criterios de M4 (instituciones), lote 1: registro, edición y desactivación.
 * - Afiliada sin número de 7 dígitos o con número repetido: rechazada con mensaje claro.
 * - Matriz que no es universidad: rechazada.
 * - La zona se sugiere desde la entidad del domicilio y se puede cambiar.
 * - Participación actual y su historial se guardan juntos.
 * - Desactivar conserva el número de afiliación.
 */
class InstitucionesTest extends TestCase
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

    public function test_registrar_una_matriz_sin_domicilio_guarda_su_razon_social_y_participacion(): void
    {
        $this->actingAs($this->admin)->post('/instituciones', $this->matriz())
            ->assertRedirect('/instituciones')
            ->assertSessionHas('estado', 'Se registró Universidad Nacional Autónoma de México.');

        $id = $this->idDe('Universidad Nacional Autónoma de México');
        $this->assertDatabaseHas('institucion_educativa', ['ined_id_institucion_educativa' => $id, 'ined_participacion' => 'Matriz', 'ined_num_afiliacion' => null, 'ined_id_domicilio' => null, 'ined_sector' => 'Publica']);
        // La participación actual y su historial, juntos
        $this->assertDatabaseHas('institucion_participacion', ['inpa_id_institucion' => $id, 'inpa_tipo' => 'Matriz', 'inpa_fecha_inicio' => '1959-01-01', 'inpa_fecha_fin' => null]);
        $this->assertDatabaseHas('institucion_nombre', ['instn_id_institucion' => $id, 'instn_fecha_inicio' => '1959-01-01', 'instn_fecha_fin' => null]);
        $this->assertSame(['Registro', 'INSTITUCION_EDUCATIVA', $id], $this->resumen($this->ultimaBitacora()));
    }

    public function test_registrar_una_facultad_afiliada_con_su_domicilio_y_zona(): void
    {
        $unam = $this->registrar($this->matriz());
        $zona7 = $this->zona(7);

        $this->actingAs($this->admin)->post('/instituciones', $this->afiliada($unam, '2607002', $zona7))
            ->assertSessionHasNoErrors();

        $id = $this->idDe('Facultad de Contaduría y Administración');
        $institucion = DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $id)->first();
        $this->assertSame(['2607002', $unam, $zona7], [$institucion->ined_num_afiliacion, (int) $institucion->ined_id_institucion_padre, (int) $institucion->ined_id_zona_regional]);
        $this->assertDatabaseHas('domicilio', ['domi_id_domicilio' => $institucion->ined_id_domicilio, 'domi_id_colonia' => $this->colonia, 'domi_calle' => 'Av. Universidad', 'domi_num_ext' => '3000']);
    }

    public function test_la_zona_se_puede_cambiar_aunque_la_entidad_sugiera_otra(): void
    {
        $unam = $this->registrar($this->matriz());

        // La colonia es de la Ciudad de México (zona 7), pero se elige la zona 3
        $this->actingAs($this->admin)->post('/instituciones', $this->afiliada($unam, '2607002', $this->zona(3)))->assertSessionHasNoErrors();

        $this->assertSame($this->zona(3), (int) DB::table('institucion_educativa')->where('ined_num_afiliacion', '2607002')->value('ined_id_zona_regional'));
    }

    public function test_la_consulta_por_codigo_postal_sugiere_la_zona_de_la_entidad(): void
    {
        $this->actingAs($this->admin)->getJson('/codigos-postales/04510')->assertJsonPath('0.zona_id', $this->zona(7));
    }

    public function test_una_afiliada_sin_numero_de_7_digitos_se_rechaza_con_mensaje_claro(): void
    {
        $unam = $this->registrar($this->matriz());

        $this->actingAs($this->admin)->post('/instituciones', ['numero' => ''] + $this->afiliada($unam, '', $this->zona(7)))
            ->assertSessionHasErrors(['numero' => 'Una afiliada necesita su número de afiliación de 7 dígitos.']);

        foreach (['260700', '26070021', '26O7002', ' 2607002x'] as $malo) {
            $this->actingAs($this->admin)->post('/instituciones', $this->afiliada($unam, $malo, $this->zona(7)))
                ->assertSessionHasErrors(['numero' => 'El número de afiliación tiene exactamente 7 dígitos.']);
        }

        $this->assertSame(0, DB::table('institucion_educativa')->where('ined_participacion', 'Afiliada')->count());
    }

    public function test_un_numero_de_afiliacion_repetido_se_rechaza_con_mensaje_claro(): void
    {
        $unam = $this->registrar($this->matriz());
        $this->registrar($this->afiliada($unam, '2607002', $this->zona(7)));

        $this->actingAs($this->admin)->post('/instituciones', ['razon_social' => 'Otra Facultad'] + $this->afiliada($unam, '2607002', $this->zona(7)))
            ->assertSessionHasErrors(['numero' => 'El número 2607002 ya pertenece a otra institución.']);
    }

    public function test_una_matriz_que_no_es_universidad_se_rechaza(): void
    {
        $unam = $this->registrar($this->matriz());

        $this->actingAs($this->admin)->post('/instituciones', ['tipo' => 'Facultad', 'universidad' => $unam, 'razon_social' => 'Facultad Matriz'] + $this->matriz())
            ->assertSessionHasErrors(['tipo' => 'Solo una universidad puede ser matriz.']);

        // La base también lo impide, aunque alguien se saltara la pantalla
        $this->expectExceptionMessage('Solo una universidad puede ser matriz.');
        DB::table('institucion_educativa')->insert([
            'ined_id_tipo_institucion' => DB::table('tipo_institucion_educativa')->where('tiie_nombre', 'Campus')->value('tiie_id_tipo_institucion'),
            'ined_id_institucion_padre' => null, 'ined_participacion' => 'Matriz', 'ined_sector' => 'Publica',
        ]);
    }

    public function test_facultad_y_campus_necesitan_una_universidad(): void
    {
        $this->actingAs($this->admin)->post('/instituciones', ['tipo' => 'Campus', 'universidad' => ''] + $this->observadora())
            ->assertSessionHasErrors(['universidad' => 'Elige la universidad a la que pertenece la facultad o el campus.']);

        // El padre debe ser una universidad, no una facultad
        $unam = $this->registrar($this->matriz());
        $this->registrar($this->afiliada($unam, '2607002', $this->zona(7)));
        $facultad = $this->idDe('Facultad de Contaduría y Administración');

        $this->actingAs($this->admin)->post('/instituciones', ['tipo' => 'Campus', 'universidad' => $facultad] + $this->observadora())
            ->assertSessionHasErrors(['universidad' => 'Elige una universidad de la lista.']);
    }

    public function test_observadora_sin_numero_y_con_domicilio_y_zona_obligatorios(): void
    {
        $this->actingAs($this->admin)->post('/instituciones', ['numero' => '1234567'] + $this->observadora())
            ->assertSessionHasErrors(['numero' => 'Solo las afiliadas tienen número de afiliación.']);

        $this->actingAs($this->admin)->post('/instituciones', ['colonia' => '', 'calle' => '', 'num_ext' => '', 'zona' => ''] + $this->observadora())
            ->assertSessionHasErrors(['colonia', 'calle', 'num_ext', 'zona']);

        $this->actingAs($this->admin)->post('/instituciones', $this->observadora())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('institucion_participacion', ['inpa_tipo' => 'Observadora']);
    }

    public function test_la_fecha_desde_no_puede_ser_futura(): void
    {
        $this->actingAs($this->admin)->post('/instituciones', ['desde' => now()->addDay()->toDateString()] + $this->matriz())
            ->assertSessionHasErrors(['desde' => 'La fecha desde la que participa no puede ser futura.']);
    }

    public function test_el_sitio_web_solo_acepta_direcciones_http(): void
    {
        foreach (['javascript:alert(1)', 'www.unam.mx', 'ftp://unam.mx'] as $malo) {
            $this->actingAs($this->admin)->post('/instituciones', ['sitio_web' => $malo] + $this->matriz())->assertSessionHasErrors('sitio_web');
        }
    }

    public function test_los_textos_peligrosos_se_rechazan(): void
    {
        $this->actingAs($this->admin)->post('/instituciones', ['razon_social' => '<script>alert(1)</script>'] + $this->matriz())->assertSessionHasErrors('razon_social');
        $this->actingAs($this->admin)->post('/instituciones', ['calle' => '=HYPERLINK("x")'] + $this->observadora())->assertSessionHasErrors('calle');
    }

    public function test_editar_corrige_la_razon_social_sin_crear_historial_y_lo_registra(): void
    {
        $unam = $this->registrar($this->matriz());
        $this->registrar($this->afiliada($unam, '2607002', $this->zona(7)));
        $id = $this->idDe('Facultad de Contaduría y Administración');

        $this->actingAs($this->admin)->put("/instituciones/{$id}", [
            'razon_social' => 'Facultad de Contaduría y Administración (FCA)', 'numero' => '2607003',
        ] + array_diff_key($this->afiliada($unam, '', $this->zona(7)), ['participacion' => 1, 'desde' => 1, 'numero' => 1]))
            ->assertRedirect("/instituciones/{$id}");

        $this->assertSame(1, DB::table('institucion_nombre')->where('instn_id_institucion', $id)->count());
        $this->assertDatabaseHas('institucion_nombre', ['instn_id_institucion' => $id, 'instn_nombre' => 'Facultad de Contaduría y Administración (FCA)', 'instn_fecha_fin' => null]);

        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Modificacion', 'INSTITUCION_EDUCATIVA', $id], $this->resumen($bitacora));
        $this->assertSame([
            ['Razón social (corrección)', 'Facultad de Contaduría y Administración', 'Facultad de Contaduría y Administración (FCA)'],
            ['Número de afiliación', '2607002', '2607003'],
        ], $this->detalles($bitacora->bita_id_bitacora));
    }

    public function test_editar_no_cambia_la_participacion(): void
    {
        $id = $this->registrar($this->observadora());

        $this->actingAs($this->admin)->put("/instituciones/{$id}", ['participacion' => 'Afiliada', 'desde' => '2020-01-01'] + $this->observadora())
            ->assertSessionHasErrors(['participacion', 'desde']);

        $this->assertDatabaseHas('institucion_educativa', ['ined_id_institucion_educativa' => $id, 'ined_participacion' => 'Observadora']);
    }

    public function test_desactivar_conserva_el_numero_y_cierra_su_periodo_de_participacion(): void
    {
        $unam = $this->registrar($this->matriz());
        $this->registrar($this->afiliada($unam, '2607002', $this->zona(7)));
        $id = $this->idDe('Facultad de Contaduría y Administración');

        $this->actingAs($this->admin)->from('/instituciones')->patch("/instituciones/{$id}/estado", ['activa' => '0', 'fecha' => '2023-06-30'])
            ->assertSessionHas('estado', 'Se desactivó Facultad de Contaduría y Administración: dejó de participar el 30/06/2023. Conserva su número y su historial.');

        $this->assertDatabaseHas('institucion_educativa', ['ined_id_institucion_educativa' => $id, 'ined_activo' => false, 'ined_num_afiliacion' => '2607002']);
        $this->assertDatabaseHas('institucion_participacion', ['inpa_id_institucion' => $id, 'inpa_fecha_inicio' => '1996-03-01', 'inpa_fecha_fin' => '2023-06-30']);
        $bitacora = $this->ultimaBitacora();
        $this->assertSame('Desactivacion', $bitacora->bita_accion);
        $this->assertSame([['Deja de participar el', null, '2023-06-30']], $this->detalles($bitacora->bita_id_bitacora));

        // Su número sigue ocupado
        $this->actingAs($this->admin)->post('/instituciones', ['razon_social' => 'Otra'] + $this->afiliada($unam, '2607002', $this->zona(7)))->assertSessionHasErrors('numero');

        // El filtro por año ya no la cuenta después de que dejó de participar
        $this->actingAs($this->admin)->get('/inicio');
        $this->assertSame(['Facultad de Contaduría y Administración'], $this->filas('/instituciones?participacion=Afiliada&anio=2023'));
        $this->assertSame([], $this->filas('/instituciones?participacion=Afiliada&anio=2024'));
    }

    public function test_activar_abre_un_periodo_nuevo_desde_la_fecha(): void
    {
        $id = $this->registrar($this->observadora());
        $this->actingAs($this->admin)->patch("/instituciones/{$id}/estado", ['activa' => '0', 'fecha' => '2015-12-31']);

        // No puede volver antes de haber salido
        $this->actingAs($this->admin)->patch("/instituciones/{$id}/estado", ['activa' => '1', 'fecha' => '2015-12-30'])
            ->assertSessionHasErrors(['fecha' => 'La fecha no puede ser anterior a cuando dejó de participar (31/12/2015).']);

        $this->actingAs($this->admin)->patch("/instituciones/{$id}/estado", ['activa' => '1', 'fecha' => '2020-01-15'])->assertSessionHasNoErrors();

        $periodos = DB::table('institucion_participacion')->where('inpa_id_institucion', $id)->orderBy('inpa_fecha_inicio')->get(['inpa_tipo', 'inpa_fecha_inicio', 'inpa_fecha_fin'])
            ->map(fn ($p) => [$p->inpa_tipo, $p->inpa_fecha_inicio, $p->inpa_fecha_fin])->all();
        $this->assertSame([['Observadora', '2010-08-15', '2015-12-31'], ['Observadora', '2020-01-15', null]], $periodos);
        $this->assertSame([['Vuelve a participar desde', null, '2020-01-15']], $this->detalles($this->ultimaBitacora()->bita_id_bitacora));
    }

    public function test_si_vuelve_el_mismo_dia_se_reabre_el_mismo_periodo(): void
    {
        $id = $this->registrar($this->observadora());
        $hoy = now()->toDateString();

        // Se desactivó por error y se corrige el mismo día
        $this->actingAs($this->admin)->patch("/instituciones/{$id}/estado", ['activa' => '0', 'fecha' => $hoy])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->patch("/instituciones/{$id}/estado", ['activa' => '1', 'fecha' => $hoy])->assertSessionHasNoErrors();

        $periodos = DB::table('institucion_participacion')->where('inpa_id_institucion', $id)->get(['inpa_fecha_inicio', 'inpa_fecha_fin']);
        $this->assertCount(1, $periodos);
        $this->assertSame(['2010-08-15', null], [$periodos[0]->inpa_fecha_inicio, $periodos[0]->inpa_fecha_fin]);
    }

    public function test_la_fecha_para_desactivar_no_puede_ser_anterior_al_periodo_ni_futura(): void
    {
        $id = $this->registrar($this->observadora());

        $this->actingAs($this->admin)->patch("/instituciones/{$id}/estado", ['activa' => '0', 'fecha' => '2009-01-01'])
            ->assertSessionHasErrors(['fecha' => 'La fecha no puede ser anterior al inicio de su participación actual (15/08/2010).']);
        $this->actingAs($this->admin)->patch("/instituciones/{$id}/estado", ['activa' => '0', 'fecha' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors(['fecha' => 'La fecha no puede ser futura.']);
        $this->actingAs($this->admin)->patch("/instituciones/{$id}/estado", ['activa' => '0'])->assertSessionHasErrors('fecha');

        $this->assertDatabaseHas('institucion_educativa', ['ined_id_institucion_educativa' => $id, 'ined_activo' => true]);
    }

    public function test_sin_personas_sedes_ni_puestos_se_borra_con_su_historial(): void
    {
        $id = $this->registrar($this->observadora());
        $domicilio = (int) DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $id)->value('ined_id_domicilio');

        $this->actingAs($this->admin)->delete("/instituciones/{$id}", [])->assertSessionHasErrors('motivo');

        $this->actingAs($this->admin)->delete("/instituciones/{$id}", ['motivo' => 'Error de captura'])
            ->assertRedirect('/instituciones')
            ->assertSessionHas('estado', 'Se borró Universidad de Guadalajara.');

        $this->assertDatabaseMissing('institucion_educativa', ['ined_id_institucion_educativa' => $id]);
        $this->assertDatabaseMissing('institucion_nombre', ['instn_id_institucion' => $id]);
        $this->assertDatabaseMissing('institucion_participacion', ['inpa_id_institucion' => $id]);
        $this->assertDatabaseMissing('domicilio', ['domi_id_domicilio' => $domicilio]);

        $bitacora = $this->ultimaBitacora();
        $this->assertSame(['Eliminacion', 'INSTITUCION_EDUCATIVA', $id], $this->resumen($bitacora));
        $this->assertSame('Error de captura', $bitacora->bita_motivo);
        $this->assertSame([['Razón social', 'Universidad de Guadalajara', null], ['Participación', 'Observadora', null]], $this->detalles($bitacora->bita_id_bitacora));
    }

    public function test_con_personas_o_sedes_no_se_borra(): void
    {
        $unam = $this->registrar($this->matriz());
        $this->registrar($this->afiliada($unam, '2607002', $this->zona(7)));
        $fca = $this->idDe('Facultad de Contaduría y Administración');
        DB::table('persona')->where('pers_id_persona', $this->admin->usua_id_persona)->update(['pers_id_institucion_educativa' => $fca]);

        $this->actingAs($this->admin)->get('/inicio');
        $this->actingAs($this->admin)->get('/instituciones')
            ->assertSee('No se puede borrar porque tiene 1 persona. Si ya no participa, desactívala: conserva su historial.')
            ->assertSee('No se puede borrar porque tiene 1 sede (facultad o campus).', false);

        $this->actingAs($this->admin)->delete("/instituciones/{$unam}", ['motivo' => 'Duplicado'])->assertSessionHas('aviso');
        $this->actingAs($this->admin)->delete("/instituciones/{$fca}", ['motivo' => 'Duplicado'])->assertSessionHas('aviso');

        $this->assertSame(2, DB::table('institucion_educativa')->count());
    }

    public function test_el_buscador_de_universidad_muestra_zona_y_entidad(): void
    {
        $this->registrar($this->observadora());

        $this->actingAs($this->admin)->get('/instituciones/create')
            ->assertSee('role="combobox"', false)
            ->assertSee('data-nombre="Universidad de Guadalajara"', false)
            ->assertSee('data-detalle="Zona 4 · Centro Occidente · Ciudad de México · Observadora"', false);
    }

    public function test_la_lista_filtra_por_participacion_texto_estado_y_anio(): void
    {
        $unam = $this->registrar($this->matriz());
        $this->registrar($this->afiliada($unam, '2607002', $this->zona(7)));
        $this->registrar($this->observadora());
        $this->actingAs($this->admin)->get('/inicio'); // consume el mensaje "Se registró…"

        $fca = 'Facultad de Contaduría y Administración';
        $udg = 'Universidad de Guadalajara';
        $unamNombre = 'Universidad Nacional Autónoma de México';

        $this->assertSame([$fca], $this->filas('/instituciones?participacion=Afiliada'));
        $this->assertSame([$fca], $this->filas('/instituciones?texto=2607'));
        $this->assertSame([$udg], $this->filas('/instituciones?texto=guadalajara'));
        $this->assertSame([$fca, $unamNombre, $udg], $this->filas('/instituciones'));
        // La FCA es afiliada desde 1996
        $this->assertSame([], $this->filas('/instituciones?participacion=Afiliada&anio=1990'));
        $this->assertSame([$fca], $this->filas('/instituciones?participacion=Afiliada&anio=2000'));
        $this->assertSame([], $this->filas('/instituciones?estado=desactivadas'));
        // Un comodín de búsqueda no rompe ni trae todo
        $this->assertSame([], $this->filas('/instituciones?texto=%25'));
        $this->actingAs($this->admin)->get('/instituciones?texto=%25')->assertOk()->assertSeeText('Ninguna institución coincide con los filtros.');
    }

    public function test_el_formulario_avisa_mientras_se_escribe_si_el_numero_existe(): void
    {
        $unam = $this->registrar($this->matriz());
        $this->registrar($this->afiliada($unam, '2607002', $this->zona(7)));

        $this->actingAs($this->admin)->get('/instituciones/create')
            ->assertOk()
            ->assertSee('data-mensaje-repetido="El número ya pertenece a :existente."', false)
            ->assertSee('2607002', false)
            ->assertSeeText('Los campos con');
    }

    public function test_sin_el_privilegio_responde_403(): void
    {
        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $this->admin->usua_id_rol)
            ->where('ropr_id_privilegio', DB::table('privilegio')->where('priv_nombre', Privilegio::GestionarInstituciones->value)->value('priv_id_privilegio'))
            ->update(['ropr_activo' => false]);

        $this->actingAs($this->admin)->get('/instituciones')->assertForbidden();
        $this->actingAs($this->admin)->get('/instituciones/create')->assertForbidden();
        $this->actingAs($this->admin)->post('/instituciones', $this->matriz())->assertForbidden();
    }

    public function test_el_inicio_cuenta_las_afiliadas_activas(): void
    {
        $unam = $this->registrar($this->matriz());
        $this->registrar($this->afiliada($unam, '2607002', $this->zona(7)));

        $this->actingAs($this->admin)->get('/inicio')->assertSee('data-contar="1"', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function matriz(): array
    {
        return ['tipo' => 'Universidad', 'participacion' => 'Matriz', 'desde' => '1959-01-01', 'razon_social' => 'Universidad Nacional Autónoma de México', 'sector' => 'Publica', 'sitio_web' => 'https://www.unam.mx'];
    }

    /**
     * @return array<string, mixed>
     */
    private function afiliada(int $universidad, string $numero, int $zona): array
    {
        return ['tipo' => 'Facultad', 'universidad' => $universidad, 'participacion' => 'Afiliada', 'numero' => $numero, 'desde' => '1996-03-01',
            'razon_social' => 'Facultad de Contaduría y Administración', 'sector' => 'Publica', 'colonia' => $this->colonia, 'calle' => 'Av. Universidad', 'num_ext' => '3000', 'zona' => $zona];
    }

    /**
     * @return array<string, mixed>
     */
    private function observadora(): array
    {
        return ['tipo' => 'Universidad', 'participacion' => 'Observadora', 'desde' => '2010-08-15', 'razon_social' => 'Universidad de Guadalajara', 'sector' => 'Publica',
            'colonia' => $this->colonia, 'calle' => 'Av. Juárez', 'num_ext' => '976', 'zona' => $this->zona(4)];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function registrar(array $datos): int
    {
        $this->actingAs($this->admin)->post('/instituciones', $datos)->assertSessionHasNoErrors();

        return $this->idDe($datos['razon_social']);
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

    private function idDe(string $nombre): int
    {
        return (int) DB::table('institucion_nombre')->where('instn_nombre', $nombre)->whereNull('instn_fecha_fin')->value('instn_id_institucion');
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
     * @return array{0: string, 1: string, 2: int}
     */
    private function resumen(object $bitacora): array
    {
        return [$bitacora->bita_accion, $bitacora->bita_tabla, (int) $bitacora->bita_referencia];
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

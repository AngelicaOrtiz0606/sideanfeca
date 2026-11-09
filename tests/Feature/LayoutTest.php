<?php

namespace Tests\Feature;

use App\Models\Rol;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreaCuentas;
use Tests\TestCase;

/**
 * Layout institucional: header, menú y footer (secciones 7, 8 y 9).
 */
class LayoutTest extends TestCase
{
    use CreaCuentas;
    use DatabaseTransactions;

    private const LEYENDA = 'Esta página puede ser reproducida con fines no lucrativos';

    public function test_el_login_usa_el_layout_publico_accesible(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertSee('href="#contenido"', false)
            ->assertSee('id="contenido"', false)
            ->assertSee('SIDEANFECA')
            ->assertSee('data-mostrar-contrasenia', false)
            ->assertSee('¿Olvidaste tu contraseña?')
            ->assertSee('href="'.route('contrasenia.solicitar').'"', false)
            ->assertSee('aria-live="polite"', false)
            ->assertDontSee('id="menu-lateral"', false);
    }

    public function test_la_pagina_de_recuperacion_existe_y_regresa_al_login(): void
    {
        $this->get(route('contrasenia.solicitar'))
            ->assertOk()
            ->assertSee('Recuperar contraseña')
            ->assertSee('href="'.route('login').'"', false);
    }

    public function test_el_footer_conserva_la_leyenda_oficial_en_el_orden_pedido(): void
    {
        $this->get('/login')
            ->assertSeeInOrder([
                'Hecho en México',
                'D.R. © '.now()->year,
                self::LEYENDA,
                'previo por escrito de la institución.',
                'AVISO DE PRIVACIDAD',
                'Sitio web administrado por el Centro de Informática de la Facultad de Contaduría y Administración (CIFCA).',
                'Documento de seguridad',
                'Instrumento jurídico',
                'Aviso de privacidad simplificado',
            ])
            ->assertDontSee('href="#"', false);
    }

    public function test_el_boton_para_subir_es_flotante_y_empieza_oculto(): void
    {
        $this->get('/login')
            ->assertSee('class="boton-subir" data-boton-subir hidden', false)
            ->assertSee('Subir al inicio de la página');
    }

    public function test_los_formularios_de_acceso_se_validan_antes_de_enviarse(): void
    {
        $this->get('/login')
            ->assertSee('data-validar', false)
            ->assertSee('type="email"', false)
            ->assertSee('required', false);

        $this->get(route('contrasenia.solicitar'))->assertSee('data-validar', false);
    }

    public function test_la_validacion_del_login_es_discreta_sin_mensajes_repetidos(): void
    {
        $this->get('/login')
            ->assertDontSee('id="correo-retro"', false)
            ->assertDontSee('id="contrasenia-retro"', false)
            ->assertDontSee('para continuar.');
    }

    public function test_volver_a_iniciar_sesion_esta_arriba_del_formulario(): void
    {
        $this->get(route('contrasenia.solicitar'))
            ->assertSeeInOrder(['Volver a iniciar sesión', 'Recuperar contraseña', 'Enviar enlace']);
    }

    public function test_los_enlaces_legales_apuntan_a_los_documentos_de_la_fca(): void
    {
        $this->get('/login')
            ->assertSee('href="https://www.fca.unam.mx/docs/aviso_privacidad.pdf"', false)
            ->assertSee('href="https://www.fca.unam.mx/docs/permanentes/seguridad.pdf"', false)
            ->assertSee('href="https://www.fca.unam.mx/docs/permanentes/aws.pdf"', false)
            ->assertSee('href="https://www.fca.unam.mx/docs/permanentes/aviso_simplificado.pdf"', false);
    }

    public function test_el_anio_del_footer_cambia_solo(): void
    {
        $this->travelTo(now()->setDate(2031, 3, 1));

        $this->get('/login')->assertSee('D.R. © 2031');
    }

    public function test_los_enlaces_legales_usan_la_direccion_configurada(): void
    {
        config(['sideanfeca.enlaces_legales.aviso_privacidad' => 'https://ejemplo.test/aviso']);

        $this->get('/login')
            ->assertSee('href="https://ejemplo.test/aviso"', false);
    }

    public function test_el_header_muestra_nombre_y_perfil_del_superadmin(): void
    {
        $cuenta = $this->crearCuenta(Rol::SUPERADMINISTRADOR, [], [
            'pers_nombre' => 'Marta',
            'pers_primer_apellido' => 'Ruiz',
            'pers_segundo_apellido' => 'Soto',
        ]);

        $this->actingAs($cuenta)->get('/inicio')
            ->assertOk()
            ->assertSee('Marta Ruiz Soto')
            ->assertSee('Superadmin');
    }

    public function test_el_header_muestra_el_perfil_admin(): void
    {
        $this->actingAs($this->crearCuenta(Rol::ADMINISTRADOR))->get('/inicio')
            ->assertSee('Admin')
            ->assertDontSee('Superadmin');
    }

    public function test_salir_es_un_formulario_post(): void
    {
        $this->actingAs($this->crearCuenta())->get('/inicio')
            ->assertSee('method="POST" action="'.route('logout').'"', false);
    }

    public function test_el_menu_marca_la_pagina_actual_y_tiene_boton_accesible(): void
    {
        $this->actingAs($this->crearCuenta())->get('/inicio')
            ->assertSee('id="menu-lateral"', false)
            ->assertSee('aria-controls="menu-lateral"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('href="'.route('inicio').'"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_el_admin_no_ve_enlaces_de_superadmin(): void
    {
        $this->actingAs($this->crearCuenta(Rol::ADMINISTRADOR))->get('/inicio')
            ->assertSee('Personas')
            ->assertSee('Zonas regionales')
            ->assertSee('Puestos')
            ->assertDontSee('designaciones')
            ->assertDontSee('Bitácora')
            ->assertDontSee('Usuarios');
    }

    public function test_el_superadmin_ve_todas_las_secciones(): void
    {
        $this->actingAs($this->crearCuenta(Rol::SUPERADMINISTRADOR))->get('/inicio')
            ->assertSeeInOrder(['Inicio', 'Gestión principal', 'Catálogos', 'Directorios', 'Administración', 'Cuenta'])
            ->assertSee('Bitácora')
            ->assertSee('Usuarios');
    }

    public function test_los_modulos_que_aun_no_existen_no_son_enlaces(): void
    {
        $this->actingAs($this->crearCuenta(Rol::SUPERADMINISTRADOR))->get('/inicio')
            ->assertSee('(próximamente)')
            ->assertSee('aria-disabled="true"', false)
            ->assertSee('Las opciones en gris se activan conforme se terminan sus módulos.');
    }

    public function test_un_privilegio_desactivado_oculta_su_enlace(): void
    {
        $cuenta = $this->crearCuenta(Rol::ADMINISTRADOR);
        $idPrivilegio = DB::table('privilegio')->where('priv_nombre', 'gestionar_zonas')->value('priv_id_privilegio');

        DB::table('rol_privilegio')
            ->where('ropr_id_rol', $cuenta->usua_id_rol)
            ->where('ropr_id_privilegio', $idPrivilegio)
            ->update(['ropr_activo' => false]);

        $this->actingAs($cuenta)->get('/inicio')
            ->assertDontSee('Zonas regionales');
    }

    public function test_tiene_privilegio_respeta_el_rol_y_la_cuenta(): void
    {
        $admin = $this->crearCuenta(Rol::ADMINISTRADOR);
        $inactiva = $this->crearCuenta(Rol::SUPERADMINISTRADOR, ['usua_activo' => false]);

        $this->assertTrue($admin->tienePrivilegio('gestionar_zonas'));
        $this->assertFalse($admin->tienePrivilegio('ver_bitacora'));
        $this->assertFalse($inactiva->tienePrivilegio('ver_bitacora'));
    }
}

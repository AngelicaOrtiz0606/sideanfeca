<?php

namespace App\View\Components;

use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

/**
 * Menú lateral de las pantallas internas.
 *
 * - Un enlace solo aparece si la cuenta tiene su privilegio (sección 7).
 * - Mientras su módulo no exista (la ruta todavía no está definida), el
 *   enlace se muestra como "Próximamente" y no se puede abrir. Al crear la
 *   ruta en su módulo, se activa solo.
 *
 * Uso: <x-menu />
 */
class Menu extends Component
{
    /**
     * Estructura del menú: secciones con sus enlaces.
     * privilegio = null significa que cualquier cuenta con sesión lo ve.
     */
    private const SECCIONES = [
        [
            'titulo' => null,
            'enlaces' => [
                ['etiqueta' => 'Inicio', 'ruta' => 'inicio', 'privilegio' => null, 'icono' => 'casa'],
            ],
        ],
        [
            'titulo' => 'Gestión principal',
            'enlaces' => [
                ['etiqueta' => 'Personas', 'ruta' => 'personas.index', 'privilegio' => 'gestionar_personas', 'icono' => 'personas'],
                ['etiqueta' => 'Instituciones', 'ruta' => 'instituciones.index', 'privilegio' => 'gestionar_instituciones', 'icono' => 'institucion'],
            ],
        ],
        [
            'titulo' => 'Catálogos',
            'enlaces' => [
                ['etiqueta' => 'Cargos', 'ruta' => 'cargos.index', 'privilegio' => 'gestionar_cargos', 'icono' => 'insignia'],
                ['etiqueta' => 'Puestos', 'ruta' => 'puestos.index', 'privilegio' => 'gestionar_puestos', 'icono' => 'maletin'],
                ['etiqueta' => 'Coordinaciones nacionales', 'ruta' => 'coordinaciones.index', 'privilegio' => 'gestionar_coordinaciones', 'icono' => 'organigrama'],
                ['etiqueta' => 'Zonas regionales', 'ruta' => 'zonas.index', 'privilegio' => 'gestionar_zonas', 'icono' => 'mapa'],
                ['etiqueta' => 'Niveles académicos', 'ruta' => 'niveles.index', 'privilegio' => 'gestionar_grados', 'icono' => 'birrete'],
            ],
        ],
        [
            'titulo' => 'Directorios',
            'enlaces' => [
                ['etiqueta' => 'Consultar directorios', 'ruta' => 'directorios.index', 'privilegio' => 'ver_directorios', 'icono' => 'libro'],
                ['etiqueta' => 'Ordenar puestos', 'ruta' => 'directorios.orden', 'privilegio' => 'gestionar_directorios', 'icono' => 'ordenar'],
            ],
        ],
        [
            'titulo' => 'Administración',
            'enlaces' => [
                ['etiqueta' => 'Usuarios', 'ruta' => 'usuarios.index', 'privilegio' => 'gestionar_usuarios', 'icono' => 'escudo'],
                ['etiqueta' => 'Bitácora', 'ruta' => 'bitacora.index', 'privilegio' => 'ver_bitacora', 'icono' => 'historial'],
            ],
        ],
        [
            'titulo' => 'Cuenta',
            'enlaces' => [
                ['etiqueta' => 'Cambiar contraseña', 'ruta' => 'cuenta.contrasenia', 'privilegio' => null, 'icono' => 'llave'],
            ],
        ],
    ];

    /**
     * @var array<int, array{titulo: ?string, enlaces: array<int, array{etiqueta: string, icono: string, url: ?string, actual: bool}>}>
     */
    public array $secciones = [];

    /**
     * ¿Hay enlaces de módulos que todavía no existen? Muestra una nota al final del menú.
     */
    public bool $hayPendientes = false;

    public function __construct()
    {
        $cuenta = Auth::user();

        foreach (self::SECCIONES as $seccion) {
            $enlaces = [];

            foreach ($seccion['enlaces'] as $enlace) {
                if ($enlace['privilegio'] !== null
                    && ! ($cuenta instanceof Usuario && $cuenta->tienePrivilegio($enlace['privilegio']))) {
                    continue;
                }

                $existe = Route::has($enlace['ruta']);
                $this->hayPendientes = $this->hayPendientes || ! $existe;

                $enlaces[] = [
                    'etiqueta' => $enlace['etiqueta'],
                    'icono' => $enlace['icono'],
                    'url' => $existe ? route($enlace['ruta']) : null,
                    'actual' => $existe && request()->routeIs($enlace['ruta'], $enlace['ruta'].'.*'),
                ];
            }

            if ($enlaces !== []) {
                $this->secciones[] = ['titulo' => $seccion['titulo'], 'enlaces' => $enlaces];
            }
        }
    }

    public function render(): View
    {
        return view('components.menu');
    }
}

<?php

namespace App\View\Components;

use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

/**
 * Encabezado institucional: logo, nombre del sistema y, si hay sesión,
 * nombre y perfil de la cuenta con el botón para salir.
 *
 * Uso: <x-header /> en pantallas internas, <x-header :con-menu="false" /> en las públicas.
 */
class Header extends Component
{
    public ?string $nombre = null;

    public ?string $perfil = null;

    public function __construct(public bool $conMenu = true)
    {
        $cuenta = Auth::user();

        if ($cuenta instanceof Usuario) {
            $this->nombre = $cuenta->persona?->nombre_completo;
            $this->perfil = $cuenta->etiquetaPerfil();
        }
    }

    public function render(): View
    {
        return view('components.header');
    }
}

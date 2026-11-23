<?php

namespace App\Http\Controllers\Usuarios;

use App\Http\Controllers\Controller;
use App\Services\Usuarios\UsuarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Cambiar contraseña (cualquier cuenta, la propia): pide la actual y la nueva
 * dos veces. Las sesiones abiertas en otras computadoras se cierran; esta sigue.
 */
class CuentaController extends Controller
{
    public function __construct(private readonly UsuarioService $usuarios) {}

    public function editarContrasenia(): View
    {
        return view('usuarios.contrasenia');
    }

    public function actualizarContrasenia(Request $request): RedirectResponse
    {
        $cuenta = $request->user();
        $datos = $request->validate([
            'actual' => ['required', 'string', function (string $campo, mixed $valor, \Closure $falla) use ($cuenta): void {
                if (! Hash::check((string) $valor, $cuenta->getAuthPassword())) {
                    $falla('Tu contraseña actual no es correcta.');
                }
            }],
            'contrasenia' => ['required', 'string', 'confirmed', 'different:actual', Password::defaults()],
        ], [
            'actual.required' => 'Escribe tu contraseña actual.',
            'contrasenia.required' => 'Escribe tu contraseña nueva.',
            'contrasenia.confirmed' => 'Las dos contraseñas nuevas no coinciden. Escríbelas de nuevo.',
            'contrasenia.different' => 'La contraseña nueva debe ser distinta de la actual.',
        ], ['contrasenia' => 'la contraseña nueva', 'actual' => 'la contraseña actual']);

        $this->usuarios->cambiarContrasenia($cuenta, $datos['contrasenia']);

        // Esta sesión sigue abierta: al terminar, auth.session guarda la huella de la
        // contraseña nueva. Las sesiones de otras computadoras ya no coinciden y se cierran.
        $cuenta->refresh();
        $request->session()->regenerate();

        return redirect()->route('cuenta.contrasenia')->with('estado', 'Listo: tu contraseña cambió. La próxima vez entra con la nueva.');
    }
}

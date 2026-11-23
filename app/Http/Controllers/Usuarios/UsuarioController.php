<?php

namespace App\Http\Controllers\Usuarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usuarios\UsuarioRequest;
use App\Services\Personas\PersonaService;
use App\Services\Usuarios\UsuarioService;
use App\Support\Paginacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Usuarios (privilegio gestionar_usuarios, revisado en la ruta): lista,
 * nueva cuenta (para una persona nueva o una ya registrada), editar y
 * activar o desactivar. Las cuentas no se borran.
 */
class UsuarioController extends Controller
{
    public function __construct(
        private readonly UsuarioService $usuarios,
        private readonly PersonaService $personas,
    ) {}

    public function index(Request $request): View
    {
        $roles = $this->usuarios->roles();
        $filtros = [
            'texto' => trim((string) $request->query('texto')) ?: null,
            'rol' => $roles->firstWhere('id', $request->integer('rol'))?->id,
            'estado' => in_array($request->query('estado'), ['activas', 'desactivadas'], true) ? (string) $request->query('estado') : null,
        ];

        return view('usuarios.index', [
            'cuentas' => Paginacion::de($this->usuarios->listado($filtros)),
            'filtros' => $filtros,
            'roles' => $roles,
        ]);
    }

    public function create(Request $request): View
    {
        // Desde "¿Ya está registrada?": crearle la cuenta a una persona que ya existe
        $persona = $request->filled('persona') ? $this->usuarios->personaSinCuenta($request->integer('persona')) : null;

        return view('usuarios.formulario', [
            'cuenta' => null,
            'persona' => $persona,
            'roles' => $this->usuarios->roles(),
        ]);
    }

    /** ¿Ya está registrada? Mientras se escribe el nombre o la CURP. */
    public function coincidencias(Request $request): JsonResponse
    {
        $nombre = mb_substr(trim((string) $request->query('nombre')), 0, 150);
        $curp = strtoupper(mb_substr(preg_replace('/\s+/', '', (string) $request->query('curp')), 0, 18));

        return response()->json($this->personas->coincidencias($nombre, $curp)->map(function (object $p): array {
            $cuenta = $p->tiene_cuenta ? DB::table('usuario')->where('usua_id_persona', $p->id)->value('usua_id_usuario') : null;

            return [
                'nombre' => $p->nombre,
                'detalle' => collect([
                    $p->misma_curp ? 'Misma CURP' : null,
                    $cuenta ? 'Ya tiene cuenta' : ($p->en_directorio ? 'Está en los directorios, sin cuenta' : 'Registrada, sin cuenta'),
                ])->filter()->implode(' · '),
                'enlace' => $cuenta ? route('usuarios.edit', $cuenta) : route('usuarios.create', ['persona' => $p->id]),
                'accion' => $cuenta ? 'Ver su cuenta' : 'Crearle su cuenta',
            ];
        })->values());
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $persona = null;
        if ($request->filled('persona')) {
            $persona = $this->usuarios->personaSinCuenta($request->integer('persona'))?->id;
            abort_if($persona === null, 422, 'Esa persona ya tiene cuenta o no existe.');
        }

        $datos = $request->datos();
        $this->usuarios->registrar($request->user(), $datos, $persona);

        return redirect()->route('usuarios.index')->with('estado',
            "Se creó la cuenta de {$datos['nombre']} {$datos['primer_apellido']}. Dale su correo y su contraseña temporal; al entrar puede cambiarla en «Cambiar contraseña».");
    }

    public function edit(int $usuario): View
    {
        $cuenta = $this->usuarios->buscar($usuario);
        abort_if($cuenta === null, 404);

        return view('usuarios.formulario', [
            'cuenta' => $cuenta,
            'persona' => null,
            'roles' => $this->usuarios->roles(),
        ]);
    }

    public function update(UsuarioRequest $request, int $usuario): RedirectResponse
    {
        $cuenta = $this->usuarios->buscar($usuario);
        abort_if($cuenta === null, 404);

        $cambio = $this->usuarios->actualizar($request->user(), $usuario, $request->datos());

        return redirect()->route('usuarios.index')->with('estado', $cambio
            ? "Se guardaron los cambios de la cuenta de {$cuenta->nombre}."
            : 'No hubo cambios que guardar.');
    }

    public function cambiarEstado(Request $request, int $usuario): RedirectResponse
    {
        abort_if($this->usuarios->buscar($usuario) === null, 404);

        [$mensaje, $error] = $this->usuarios->cambiarEstado($request->user(), $usuario, $request->boolean('activa'));

        return redirect()->route('usuarios.index', $request->only('texto', 'rol', 'estado', 'page'))
            ->with($error === null ? 'estado' : 'aviso', $error ?? $mensaje);
    }
}

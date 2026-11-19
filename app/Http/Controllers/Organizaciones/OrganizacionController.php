<?php

namespace App\Http\Controllers\Organizaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\EliminarRequest;
use App\Http\Requests\Organizaciones\OrganizacionRequest;
use App\Services\Organizaciones\OrganizacionService;
use App\Support\Paginacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Organizaciones (privilegio gestionar_instituciones, revisado en la ruta).
 * Todo desde la lista: registrar y editar en una ventana, activar o
 * desactivar, y borrar solo si ninguna persona pertenece a ella.
 */
class OrganizacionController extends Controller
{
    public function __construct(private readonly OrganizacionService $organizaciones) {}

    public function index(Request $request): View
    {
        $organizaciones = $this->organizaciones->listado();
        $texto = trim((string) $request->query('texto')) ?: null;

        return view('organizaciones.index', [
            'organizaciones' => $organizaciones,
            'pagina' => Paginacion::de(Paginacion::buscar($organizaciones, $texto, fn ($o) => "{$o->nombre} {$o->municipio} {$o->entidad}")),
            'filtros' => ['texto' => $texto],
            'motivos' => EliminarRequest::MOTIVOS,
        ]);
    }

    public function store(OrganizacionRequest $request): RedirectResponse
    {
        $datos = $request->datos();
        $this->organizaciones->registrar($request->user(), $datos);

        return redirect()->route('organizaciones.index')->with('estado', "Se registró {$datos['nombre']}.");
    }

    public function update(OrganizacionRequest $request, int $organizacion): RedirectResponse
    {
        abort_if($this->organizaciones->buscar($organizacion) === null, 404);

        $datos = $request->datos();
        $this->organizaciones->actualizar($request->user(), $organizacion, $datos);

        return redirect()->route('organizaciones.index')->with('estado', "Se guardaron los cambios de {$datos['nombre']}.");
    }

    public function cambiarEstado(Request $request, int $organizacion): RedirectResponse
    {
        $encontrada = $this->organizaciones->buscar($organizacion);
        abort_if($encontrada === null, 404);

        $activa = $request->boolean('activa');
        $this->organizaciones->cambiarEstado($request->user(), $organizacion, $activa);

        return redirect()->route('organizaciones.index')->with('estado', $activa
            ? "Se activó {$encontrada->orga_nombre}."
            : "Se desactivó {$encontrada->orga_nombre}. Sus personas la conservan.");
    }

    public function destroy(EliminarRequest $request, int $organizacion): RedirectResponse
    {
        $encontrada = $this->organizaciones->buscar($organizacion);
        abort_if($encontrada === null, 404);

        if (! $this->organizaciones->eliminar($request->user(), $organizacion, $request->motivo())) {
            return redirect()->route('organizaciones.index')
                ->with('aviso', $this->organizaciones->motivoParaNoBorrar($organizacion) ?? 'No se pudo borrar la organización.');
        }

        return redirect()->route('organizaciones.index')->with('estado', "Se borró {$encontrada->orga_nombre}.");
    }
}

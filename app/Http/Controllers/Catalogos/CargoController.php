<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\CargoRequest;
use App\Http\Requests\Catalogos\EliminarRequest;
use App\Models\Cargo;
use App\Services\Catalogos\CargoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo de cargos (privilegio gestionar_cargos, revisado en la ruta).
 * Todo desde la lista: registrar y editar en una ventana, activar o
 * desactivar, y borrar solo si no tiene puestos.
 */
class CargoController extends Controller
{
    public function __construct(private readonly CargoService $cargos) {}

    public function index(): View
    {
        return view('catalogos.cargos.index', [
            'cargos' => $this->cargos->listado(),
            'niveles' => Cargo::NIVELES,
            'motivos' => EliminarRequest::MOTIVOS,
        ]);
    }

    public function store(CargoRequest $request): RedirectResponse
    {
        $cargo = $this->cargos->registrar($request->user(), $request->texto('masculino'), $request->texto('femenino'), $request->nivel());

        return back()->with('estado', "Se registró el cargo {$cargo->carg_nombre_m} / {$cargo->carg_nombre_f}.");
    }

    public function update(CargoRequest $request, Cargo $cargo): RedirectResponse
    {
        $this->cargos->actualizar($request->user(), $cargo, $request->texto('masculino'), $request->texto('femenino'), $request->nivel());

        return back()->with('estado', "Se guardaron los cambios de {$request->texto('masculino')} / {$request->texto('femenino')}.");
    }

    public function cambiarEstado(Request $request, Cargo $cargo): RedirectResponse
    {
        $activo = $request->boolean('activa');
        $this->cargos->cambiarEstado($request->user(), $cargo, $activo);

        return back()->with('estado', $activo
            ? "Se activó el cargo {$cargo->carg_nombre_m}."
            : "Se desactivó el cargo {$cargo->carg_nombre_m}. Sus puestos y su historial se conservan.");
    }

    public function destroy(EliminarRequest $request, Cargo $cargo): RedirectResponse
    {
        if (! $this->cargos->eliminar($request->user(), $cargo, $request->motivo())) {
            return redirect()->route('cargos.index')
                ->with('aviso', $this->cargos->motivoParaNoBorrar($cargo) ?? 'No se pudo borrar el cargo.');
        }

        return redirect()->route('cargos.index')->with('estado', "Se borró el cargo {$cargo->carg_nombre_m}.");
    }
}

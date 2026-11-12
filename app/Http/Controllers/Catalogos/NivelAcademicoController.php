<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\EliminarRequest;
use App\Http\Requests\Catalogos\NivelAcademicoRequest;
use App\Models\NivelAcademico;
use App\Services\Catalogos\NivelAcademicoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo de grados académicos (tabla NIVEL_ACADEMICO; privilegio
 * gestionar_grados, revisado en la ruta).
 * Todo desde la lista: registrar y editar en una ventana, activar o
 * desactivar, y borrar solo si ninguna persona lo tiene.
 */
class NivelAcademicoController extends Controller
{
    public function __construct(private readonly NivelAcademicoService $grados) {}

    public function index(): View
    {
        return view('catalogos.grados.index', [
            'grados' => $this->grados->listado(),
            'motivos' => EliminarRequest::MOTIVOS,
        ]);
    }

    public function store(NivelAcademicoRequest $request): RedirectResponse
    {
        $grado = $this->grados->registrar($request->user(), $request->texto('nombre'), $request->texto('masculina'), $request->texto('femenina'));

        return back()->with('estado', "Se registró el grado {$grado->niac_nombre}.");
    }

    public function update(NivelAcademicoRequest $request, NivelAcademico $grado): RedirectResponse
    {
        $this->grados->actualizar($request->user(), $grado, $request->texto('nombre'), $request->texto('masculina'), $request->texto('femenina'));

        return back()->with('estado', "Se guardaron los cambios de {$request->texto('nombre')}.");
    }

    public function cambiarEstado(Request $request, NivelAcademico $grado): RedirectResponse
    {
        $activo = $request->boolean('activa');
        $this->grados->cambiarEstado($request->user(), $grado, $activo);

        return back()->with('estado', $activo
            ? "Se activó el grado {$grado->niac_nombre}."
            : "Se desactivó el grado {$grado->niac_nombre}. Las personas que ya lo tienen lo conservan.");
    }

    public function destroy(EliminarRequest $request, NivelAcademico $grado): RedirectResponse
    {
        if (! $this->grados->eliminar($request->user(), $grado, $request->motivo())) {
            return redirect()->route('grados.index')
                ->with('aviso', $this->grados->motivoParaNoBorrar($grado) ?? 'No se pudo borrar el grado.');
        }

        return redirect()->route('grados.index')->with('estado', "Se borró el grado {$grado->niac_nombre}.");
    }
}

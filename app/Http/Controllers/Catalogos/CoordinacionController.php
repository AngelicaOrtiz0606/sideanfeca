<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\CoordinacionRequest;
use App\Http\Requests\Catalogos\EliminarRequest;
use App\Http\Requests\Catalogos\OrdenCoordinacionesRequest;
use App\Models\CoordinacionNacional;
use App\Services\Catalogos\CoordinacionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo de coordinaciones nacionales (privilegio gestionar_coordinaciones,
 * revisado en la ruta). Se activan o desactivan; solo se borran si nunca
 * tuvieron puestos.
 */
class CoordinacionController extends Controller
{
    public function __construct(private readonly CoordinacionService $coordinaciones) {}

    public function index(): View
    {
        return view('catalogos.coordinaciones.index', [
            'coordinaciones' => $this->coordinaciones->listado(),
        ]);
    }

    public function show(CoordinacionNacional $coordinacion): View
    {
        return view('catalogos.coordinaciones.show', [
            'coordinacion' => $coordinacion,
            'puestos' => $this->coordinaciones->puestos($coordinacion),
            'motivoParaNoBorrar' => $this->coordinaciones->motivoParaNoBorrar($coordinacion),
            'motivos' => EliminarRequest::MOTIVOS,
        ]);
    }

    public function create(): View
    {
        return view('catalogos.coordinaciones.formulario', ['coordinacion' => new CoordinacionNacional]);
    }

    public function store(CoordinacionRequest $request): RedirectResponse
    {
        $coordinacion = $this->coordinaciones->registrar($request->user(), $request->nombre(), $request->descripcion());

        return redirect()->route('coordinaciones.show', $coordinacion)
            ->with('estado', "Se registró la coordinación {$coordinacion->cona_nombre}. Quedó al final del orden.");
    }

    public function edit(CoordinacionNacional $coordinacion): View
    {
        return view('catalogos.coordinaciones.formulario', ['coordinacion' => $coordinacion]);
    }

    public function update(CoordinacionRequest $request, CoordinacionNacional $coordinacion): RedirectResponse
    {
        $this->coordinaciones->actualizar($request->user(), $coordinacion, $request->nombre(), $request->descripcion());

        return redirect()->route('coordinaciones.show', $coordinacion)->with('estado', 'Se guardaron los cambios de la coordinación.');
    }

    public function cambiarEstado(Request $request, CoordinacionNacional $coordinacion): RedirectResponse
    {
        $activa = $request->boolean('activa');
        $this->coordinaciones->cambiarEstado($request->user(), $coordinacion, $activa);

        return redirect()->route('coordinaciones.show', $coordinacion)->with('estado', $activa
            ? 'Se activó la coordinación.'
            : 'Se desactivó la coordinación. Sus puestos y su historial se conservan.');
    }

    public function destroy(EliminarRequest $request, CoordinacionNacional $coordinacion): RedirectResponse
    {
        $nombre = $coordinacion->cona_nombre;

        if (! $this->coordinaciones->eliminar($request->user(), $coordinacion, $request->motivo())) {
            return redirect()->route('coordinaciones.show', $coordinacion)
                ->with('aviso', $this->coordinaciones->motivoParaNoBorrar($coordinacion) ?? 'No se pudo borrar la coordinación.');
        }

        return redirect()->route('coordinaciones.index')
            ->with('estado', "Se borró la coordinación {$nombre}. Las que seguían subieron un lugar.");
    }

    public function orden(): View
    {
        return view('catalogos.coordinaciones.orden', [
            'coordinaciones' => $this->coordinaciones->listado(),
        ]);
    }

    public function guardarOrden(OrdenCoordinacionesRequest $request): RedirectResponse
    {
        try {
            $cambio = $this->coordinaciones->reordenar($request->user(), $request->ids());
        } catch (QueryException $error) {
            // reordenar_coordinaciones rechazó la lista (le falta o le sobra alguna)
            report($error);

            return redirect()->route('coordinaciones.orden')
                ->with('aviso', 'La lista de coordinaciones cambió mientras ordenabas. Revisa el orden y guarda otra vez.');
        }

        return redirect()->route('coordinaciones.index')
            ->with('estado', $cambio ? 'Se guardó el nuevo orden.' : 'El orden no cambió.');
    }
}

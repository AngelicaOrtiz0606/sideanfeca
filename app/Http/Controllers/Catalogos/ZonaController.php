<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\EliminarRequest;
use App\Http\Requests\Catalogos\ZonaRequest;
use App\Models\EntidadFederativa;
use App\Models\ZonaRegional;
use App\Services\Catalogos\ZonaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Catálogo de zonas regionales (privilegio gestionar_zonas, revisado en la ruta).
 * Registrar y editar se hacen en una ventana sobre la lista o la consulta,
 * así que no hay pantallas aparte para eso.
 */
class ZonaController extends Controller
{
    public function __construct(private readonly ZonaService $zonas) {}

    public function index(): View
    {
        return view('catalogos.zonas.index', [
            'zonas' => $this->zonas->listado(),
            'numerosLibres' => $this->zonas->numerosLibres(),
            'entidades' => $this->entidades(),
            'motivos' => EliminarRequest::MOTIVOS,
        ]);
    }

    public function show(ZonaRegional $zona): View
    {
        return view('catalogos.zonas.show', [
            'zona' => $zona,
            'entidades' => $zona->entidades,
            'instituciones' => $this->zonas->instituciones($zona),
            'todasLasEntidades' => $this->entidades(),
            'todasLasZonas' => $this->zonas->listado(),
        ]);
    }

    public function store(ZonaRequest $request): RedirectResponse
    {
        $zona = $this->zonas->registrar($request->user(), $request->numero(), $request->nombre(), $request->entidades());

        return back()->with('estado', "Se registró la {$zona->etiqueta()}.");
    }

    public function update(ZonaRequest $request, ZonaRegional $zona): RedirectResponse
    {
        $movidas = $this->zonas->actualizar($request->user(), $zona, $request->numero(), $request->nombre(), $request->entidades());

        $mensaje = "Se guardaron los cambios de la {$zona->refresh()->etiqueta()}.";
        if ($movidas !== []) {
            $mensaje .= ' Pasaron a esta zona: '.implode(', ', $movidas).'.';
        }

        return back()->with('estado', $mensaje);
    }

    public function destroy(EliminarRequest $request, ZonaRegional $zona): RedirectResponse
    {
        $etiqueta = $zona->etiqueta();

        if (! $this->zonas->eliminar($request->user(), $zona, $request->motivo())) {
            return redirect()->route('zonas.index')
                ->with('aviso', $this->zonas->motivoParaNoBorrar($zona) ?? 'No se pudo borrar la zona.');
        }

        return redirect()->route('zonas.index')->with('estado', "Se borró la {$etiqueta}.");
    }

    /**
     * Las 32 entidades con su zona actual, para elegir cuáles pasan a una zona.
     *
     * @return Collection<int, EntidadFederativa>
     */
    private function entidades(): Collection
    {
        return EntidadFederativa::query()->with('zona')->orderBy('enfe_nombre')->get();
    }
}

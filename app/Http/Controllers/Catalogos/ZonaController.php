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
 */
class ZonaController extends Controller
{
    public function __construct(private readonly ZonaService $zonas) {}

    public function index(): View
    {
        return view('catalogos.zonas.index', [
            'zonas' => $this->zonas->listado(),
            'hayNumerosLibres' => $this->zonas->numerosLibres() !== [],
        ]);
    }

    public function show(ZonaRegional $zona): View
    {
        return view('catalogos.zonas.show', [
            'zona' => $zona,
            'entidades' => $zona->entidades,
            'instituciones' => $this->zonas->instituciones($zona),
            'motivoParaNoBorrar' => $this->zonas->motivoParaNoBorrar($zona),
            'motivos' => EliminarRequest::MOTIVOS,
        ]);
    }

    public function create(): View|RedirectResponse
    {
        $libres = $this->zonas->numerosLibres();

        if ($libres === []) {
            return redirect()->route('zonas.index')
                ->with('aviso', 'Ya están registradas las 7 zonas. Para agregar otra, primero borra una que no se use.');
        }

        return view('catalogos.zonas.formulario', [
            'zona' => (new ZonaRegional)->forceFill(['zore_numero' => $libres[0]]),
            'entidades' => $this->entidades(),
        ]);
    }

    public function store(ZonaRequest $request): RedirectResponse
    {
        $zona = $this->zonas->registrar($request->user(), $request->numero(), $request->nombre(), $request->entidades());

        return redirect()->route('zonas.show', $zona)->with('estado', "Se registró la {$zona->etiqueta()}.");
    }

    public function edit(ZonaRegional $zona): View
    {
        return view('catalogos.zonas.formulario', [
            'zona' => $zona,
            'entidades' => $this->entidades(),
        ]);
    }

    public function update(ZonaRequest $request, ZonaRegional $zona): RedirectResponse
    {
        $movidas = $this->zonas->actualizar($request->user(), $zona, $request->numero(), $request->nombre(), $request->entidades());

        $mensaje = 'Se guardaron los cambios de la zona.';
        if ($movidas !== []) {
            $mensaje .= ' Pasaron a esta zona: '.implode(', ', $movidas).'.';
        }

        return redirect()->route('zonas.show', $zona)->with('estado', $mensaje);
    }

    public function destroy(EliminarRequest $request, ZonaRegional $zona): RedirectResponse
    {
        $etiqueta = $zona->etiqueta();

        if (! $this->zonas->eliminar($request->user(), $zona, $request->motivo())) {
            return redirect()->route('zonas.show', $zona)
                ->with('aviso', $this->zonas->motivoParaNoBorrar($zona) ?? 'No se pudo borrar la zona.');
        }

        return redirect()->route('zonas.index')->with('estado', "Se borró la {$etiqueta}.");
    }

    /**
     * Las 32 entidades con su zona actual, para elegir cuáles pasan a esta zona.
     *
     * @return Collection<int, EntidadFederativa>
     */
    private function entidades(): Collection
    {
        return EntidadFederativa::query()->with('zona')->orderBy('enfe_nombre')->get();
    }
}

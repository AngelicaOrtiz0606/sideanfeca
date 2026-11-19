<?php

namespace App\Http\Controllers\Puestos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\EliminarRequest;
use App\Http\Requests\Puestos\PuestoRequest;
use App\Services\Puestos\PuestoService;
use App\Support\Paginacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Catálogo de puestos (privilegio gestionar_puestos, revisado en la ruta).
 * Todo desde la lista: registrar y editar en una ventana, activar o
 * desactivar, y borrar solo si nunca tuvo designaciones.
 */
class PuestoController extends Controller
{
    public function __construct(private readonly PuestoService $puestos) {}

    public function index(Request $request): View
    {
        $puestos = $this->puestos->listado();
        $filtros = [
            'texto' => trim((string) $request->query('texto')) ?: null,
            'nivel' => in_array($request->query('nivel'), PuestoService::NIVELES, true) ? $request->query('nivel') : null,
        ];
        $filtrados = Paginacion::buscar($puestos, $filtros['texto'], fn ($p) => "{$p->cargo} {$p->cargo_f} {$p->ambito} {$p->ocupante}")
            ->when($filtros['nivel'], fn ($lista, $nivel) => $lista->where('nivel', $nivel)->values());

        return view('puestos.index', [
            'puestos' => $puestos,
            'pagina' => Paginacion::de($filtrados),
            'filtros' => $filtros,
            'niveles' => PuestoService::NIVELES,
            'cargos' => $this->puestos->cargos(),
            'zonas' => $this->puestos->zonas(),
            'coordinaciones' => $this->puestos->coordinaciones(),
            'instituciones' => $this->puestos->instituciones(),
            'motivos' => EliminarRequest::MOTIVOS,
        ]);
    }

    public function store(PuestoRequest $request): RedirectResponse
    {
        $id = $this->puestos->registrar($request->user(), $request->datos());

        return redirect()->route('puestos.index')->with('estado', 'Se registró el puesto '.$this->puestos->ambito($this->puestos->buscar($id), true).'.');
    }

    public function update(PuestoRequest $request, int $puesto): RedirectResponse
    {
        abort_if($this->puestos->buscar($puesto) === null, 404);

        $this->puestos->actualizar($request->user(), $puesto, $request->datos());

        return redirect()->route('puestos.index')->with('estado', 'Se guardaron los cambios del puesto '.$this->puestos->ambito($this->puestos->buscar($puesto), true).'.');
    }

    public function cambiarEstado(Request $request, int $puesto): RedirectResponse
    {
        $encontrado = $this->puestos->buscar($puesto);
        abort_if($encontrado === null, 404);

        $activo = $request->boolean('activa');
        $this->puestos->cambiarEstado($request->user(), $puesto, $activo);
        $nombre = $this->puestos->ambito($encontrado, true);

        return redirect()->route('puestos.index')->with('estado', $activo
            ? "Se activó el puesto {$nombre}."
            : "Se desactivó el puesto {$nombre}. Conserva su historia y ya no recibe designaciones nuevas.");
    }

    public function destroy(EliminarRequest $request, int $puesto): RedirectResponse
    {
        $encontrado = $this->puestos->buscar($puesto);
        abort_if($encontrado === null, 404);

        if (! $this->puestos->eliminar($request->user(), $puesto, $request->motivo())) {
            return redirect()->route('puestos.index')
                ->with('aviso', $this->puestos->motivoParaNoBorrar($puesto) ?? 'No se pudo borrar el puesto.');
        }

        return redirect()->route('puestos.index')->with('estado', 'Se borró el puesto '.$this->puestos->ambito($encontrado, true).'.');
    }
}

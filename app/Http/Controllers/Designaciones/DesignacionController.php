<?php

namespace App\Http\Controllers\Designaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Designaciones\AgregarDesignacionesRequest;
use App\Services\Designaciones\DesignacionService;
use App\Services\Personas\PersonaService;
use App\Services\Puestos\PuestoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Designaciones de una persona, desde su ficha (privilegio
 * gestionar_designaciones, revisado en la ruta):
 * - Agregar designación: una o varias, con el mismo formulario de filas
 *   que Nueva persona.
 * - Terminar designación: la persona deja el puesto en una fecha
 *   (desactivar_designacion).
 * - Borrar designación: solo si se capturó por error, con motivo
 *   (eliminar_designacion_erronea).
 */
class DesignacionController extends Controller
{
    public function __construct(
        private readonly PersonaService $personas,
        private readonly DesignacionService $designaciones,
    ) {}

    public function create(int $persona): View
    {
        $encontrada = $this->personas->buscar($persona);
        abort_if($encontrada === null, 404);
        $puestos = app(PuestoService::class);

        return view('personas.designaciones', [
            'persona' => $encontrada,
            'nombre' => $this->personas->nombreCompleto($encontrada),
            'cargos' => $puestos->cargos()->where('activo', true)->values(),
            'zonas' => $puestos->zonas(),
            'coordinaciones' => $puestos->coordinaciones()->where('activo', true)->values(),
        ]);
    }

    public function store(AgregarDesignacionesRequest $request, int $persona): RedirectResponse
    {
        $encontrada = $this->personas->buscar($persona);
        abort_if($encontrada === null, 404);

        $nuevas = $request->designaciones($request->institucion());
        $this->designaciones->agregar($request->user(), $persona, $encontrada->genero, $nuevas);

        return redirect()->route('personas.show', ['persona' => $persona, 'pestana' => 'designaciones'])
            ->with('estado', count($nuevas) > 1 ? 'Se registraron las designaciones.' : 'Se registró la designación.');
    }

    /**
     * La persona deja el puesto. "fecha" es el primer día que ya no lo ocupa.
     */
    public function terminar(Request $request, int $persona, int $designacion): RedirectResponse
    {
        $encontrada = $this->designacionDe($persona, $designacion);
        $datos = $request->validate([
            'fecha' => ['required', 'date', 'after:'.$encontrada->desde],
        ], [
            'fecha.required' => 'Escribe desde qué día ya no ocupa el puesto.',
            'fecha.date' => 'Escribe una fecha válida.',
            'fecha.after' => 'Debe ser después del día en que empezó.',
        ]);

        $error = $this->designaciones->terminar($request->user(), $designacion, $datos['fecha']);

        return redirect()->route('personas.show', ['persona' => $persona, 'pestana' => 'designaciones'])
            ->with($error === null ? 'estado' : 'aviso', $error ?? 'Se registró que deja el puesto. El puesto queda libre desde esa fecha.');
    }

    /**
     * Borra una designación capturada por error (nunca ocurrió).
     */
    public function destroy(Request $request, int $persona, int $designacion): RedirectResponse
    {
        $this->designacionDe($persona, $designacion);
        if (($motivo = $this->designaciones->motivoParaNoBorrar($persona)) !== null) {
            return redirect()->route('personas.show', ['persona' => $persona, 'pestana' => 'designaciones'])->with('aviso', $motivo);
        }
        $datos = $request->validate([
            'motivo' => ['required', Rule::in(DesignacionService::MOTIVOS_PARA_BORRAR)],
        ], [
            'motivo.required' => 'Elige el motivo.',
            'motivo.in' => 'Elige un motivo de la lista.',
        ]);

        $error = $this->designaciones->borrar($request->user(), $designacion, $datos['motivo']);

        return redirect()->route('personas.show', ['persona' => $persona, 'pestana' => 'designaciones'])
            ->with($error === null ? 'estado' : 'aviso', $error ?? 'Se borró la designación. Queda en la bitácora con su motivo.');
    }

    private function designacionDe(int $persona, int $designacion): object
    {
        abort_if($this->personas->buscar($persona) === null, 404);
        $encontrada = $this->designaciones->deLaPersona($persona, $designacion);
        abort_if($encontrada === null, 404);

        return $encontrada;
    }
}

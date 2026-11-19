<?php

namespace App\Http\Controllers\Personas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personas\PersonaRequest;
use App\Services\Personas\PersonaService;
use App\Services\Puestos\PuestoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Personas (privilegio gestionar_personas, revisado en la ruta).
 * Cada fila de la lista: Consultar (ficha de solo lectura, por pestañas) y
 * Editar (el formulario completo, ya lleno). Registrar una persona (con su
 * primer cargo) y borrarla llegan en el siguiente lote.
 */
class PersonaController extends Controller
{
    public function __construct(private readonly PersonaService $personas) {}

    public function index(Request $request): View
    {
        $filtros = [
            'texto' => trim((string) $request->query('texto')) ?: null,
            'grado' => ctype_digit((string) $request->query('grado')) ? (int) $request->query('grado') : null,
            'zona' => ctype_digit((string) $request->query('zona')) ? (int) $request->query('zona') : null,
            'situacion' => in_array($request->query('situacion'), ['con-cargo', 'sin-cargo'], true) ? $request->query('situacion') : null,
        ];

        return view('personas.index', [
            'personas' => $this->personas->listado($filtros),
            'filtros' => $filtros,
            'grados' => $this->personas->grados(),
            'zonas' => DB::table('zona_regional')->orderBy('zore_numero')->get(['zore_id_zona_regional as id', 'zore_numero as numero', 'zore_nombre as nombre']),
        ]);
    }

    public function show(Request $request, int $persona): View
    {
        $encontrada = $this->personas->buscar($persona);
        abort_if($encontrada === null, 404);

        $pestanas = ['general' => 'General', 'contactos' => 'Contactos', 'cargos' => 'Cargos'];
        $pestana = array_key_exists((string) $request->query('pestana'), $pestanas) ? (string) $request->query('pestana') : 'general';

        return view('personas.consulta', [
            'persona' => $encontrada,
            'nombre' => $this->personas->nombreCompleto($encontrada),
            'pestanas' => $pestanas,
            'pestana' => $pestana,
            'contactos' => $this->personas->contactos($persona),
            'cargos' => $this->personas->historialCargos($persona, $encontrada->genero),
            'generos' => PersonaService::GENEROS,
        ]);
    }

    public function edit(int $persona): View
    {
        $encontrada = $this->personas->buscar($persona);
        abort_if($encontrada === null, 404);

        return view('personas.formulario', [
            'persona' => $encontrada,
            'nombre' => $this->personas->nombreCompleto($encontrada),
            'contactos' => $this->personas->contactosFormulario($persona),
            'tiposContacto' => $this->personas->tiposContacto(),
            'grados' => $this->personas->grados($persona),
            'generos' => PersonaService::GENEROS,
            'instituciones' => app(PuestoService::class)->instituciones($encontrada->institucion_id),
            'organizaciones' => $this->personas->organizaciones($encontrada->organizacion_id),
        ]);
    }

    public function update(PersonaRequest $request, int $persona): RedirectResponse
    {
        abort_if($this->personas->buscar($persona) === null, 404);

        $this->personas->actualizar($request->user(), $persona, $request->datos());

        return redirect()->route('personas.show', $persona)->with('estado', 'Se guardaron los cambios.');
    }
}

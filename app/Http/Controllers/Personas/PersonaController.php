<?php

namespace App\Http\Controllers\Personas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personas\PersonaRequest;
use App\Services\Personas\PersonaService;
use App\Services\Puestos\PuestoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Personas (privilegio gestionar_personas, revisado en la ruta).
 * Cada fila de la lista: Consultar (ficha de solo lectura, por pestañas) y
 * Editar (el formulario completo, ya lleno). "Nueva persona" la registra con
 * su primer cargo; mientras se escribe su nombre o CURP avisa si ya existe,
 * y si solo tenía cuenta se usan sus datos en lugar de duplicarla.
 */
class PersonaController extends Controller
{
    public function __construct(private readonly PersonaService $personas) {}

    public function index(Request $request): View
    {
        $filtros = [
            'texto' => trim((string) $request->query('texto')) ?: null,
            'cargo' => ctype_digit((string) $request->query('cargo')) ? (int) $request->query('cargo') : null,
            'zona' => ctype_digit((string) $request->query('zona')) ? (int) $request->query('zona') : null,
            'situacion' => in_array($request->query('situacion'), ['con-cargo', 'sin-cargo'], true) ? $request->query('situacion') : null,
        ];

        return view('personas.index', [
            'personas' => $this->personas->listado($filtros),
            'filtros' => $filtros,
            'cargos' => app(PuestoService::class)->cargos(),
            'zonas' => app(PuestoService::class)->zonas(),
        ]);
    }

    /**
     * Nueva persona. Con ?persona= (alguien que solo tiene cuenta) el
     * formulario llega con sus datos, para registrarle su primer cargo.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $existente = null;
        if (ctype_digit((string) $request->query('persona'))) {
            $id = (int) $request->query('persona');
            $existente = $this->personas->buscarSoloCuenta($id);
            if ($existente === null) {
                // Ya está en los directorios: su ficha es el lugar
                return $this->personas->buscar($id) !== null
                    ? redirect()->route('personas.show', $id)
                    : redirect()->route('personas.create');
            }
        }

        $persona = $existente ?? (object) [
            'id' => null, 'nombre' => '', 'primer_apellido' => '', 'segundo_apellido' => '', 'curp' => '', 'genero' => null,
            'institucion_id' => null, 'organizacion_id' => null, 'grados' => collect(),
        ];

        return view('personas.formulario', [
            'persona' => $persona,
            'esNueva' => true,
            'nombre' => $existente ? $this->personas->nombreCompleto($existente) : null,
            'contactos' => $existente ? $this->personas->contactosFormulario((int) $existente->id) : ['correo' => null, 'telefono' => null, 'otros' => collect()],
            ...$this->catalogos($existente),
        ]);
    }

    public function store(PersonaRequest $request): RedirectResponse
    {
        $datos = $request->datos() + ['cargo' => $request->cargo()];
        $id = $this->personas->registrar($request->user(), $datos, $request->existente());

        return redirect()->route('personas.show', $id)->with('estado', 'Se registró '.$this->personas->nombreCompleto((object) $datos).' con su cargo.');
    }

    /**
     * Mientras se escribe en Nueva persona: quién se parece (nombre o CURP).
     */
    public function coincidencias(Request $request): JsonResponse
    {
        $nombre = mb_substr(trim((string) $request->query('nombre')), 0, 150);
        $curp = strtoupper(mb_substr(preg_replace('/\s+/', '', (string) $request->query('curp')), 0, 18));

        return response()->json($this->personas->coincidencias($nombre, $curp)->map(fn (object $p) => [
            'nombre' => $p->nombre,
            'misma_curp' => $p->misma_curp,
            'en_directorio' => $p->en_directorio,
            'tiene_cuenta' => $p->tiene_cuenta,
            'enlace' => $p->en_directorio ? route('personas.show', $p->id) : route('personas.create', ['persona' => $p->id]),
        ])->values());
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
            'esNueva' => false,
            'nombre' => $this->personas->nombreCompleto($encontrada),
            'contactos' => $this->personas->contactosFormulario($persona),
            ...$this->catalogos($encontrada),
        ]);
    }

    public function update(PersonaRequest $request, int $persona): RedirectResponse
    {
        abort_if($this->personas->buscar($persona) === null, 404);

        $this->personas->actualizar($request->user(), $persona, $request->datos());

        return redirect()->route('personas.show', $persona)->with('estado', 'Se guardaron los cambios.');
    }

    /**
     * Las listas para elegir en el formulario.
     *
     * @return array<string, mixed>
     */
    private function catalogos(?object $persona): array
    {
        $puestos = app(PuestoService::class);

        return [
            'tiposContacto' => $this->personas->tiposContacto(),
            'grados' => $this->personas->grados($persona?->id),
            'generos' => PersonaService::GENEROS,
            'instituciones' => $puestos->instituciones($persona?->institucion_id),
            'organizaciones' => $this->personas->organizaciones($persona?->organizacion_id),
            // Para el primer cargo (al registrar)
            'cargos' => $puestos->cargos()->where('activo', true)->values(),
            'zonas' => $puestos->zonas(),
            'coordinaciones' => $puestos->coordinaciones()->where('activo', true)->values(),
        ];
    }
}

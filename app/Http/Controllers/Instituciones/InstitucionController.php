<?php

namespace App\Http\Controllers\Instituciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\EliminarRequest;
use App\Http\Requests\Instituciones\EstadoInstitucionRequest;
use App\Http\Requests\Instituciones\InstitucionRequest;
use App\Services\Instituciones\InstitucionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Instituciones (privilegio gestionar_instituciones, revisado en la ruta).
 * Tienen mucha información, así que registrar y editar van en pantalla propia.
 * Se desactivan (conservan su número); solo se borran si no tienen personas,
 * sedes ni puestos (por ejemplo, una captura por error).
 */
class InstitucionController extends Controller
{
    public function __construct(private readonly InstitucionService $instituciones) {}

    public function index(Request $request): View
    {
        $filtros = [
            'texto' => trim((string) $request->query('texto')) ?: null,
            'participacion' => in_array($request->query('participacion'), InstitucionService::PARTICIPACIONES, true) ? $request->query('participacion') : null,
            'zona' => ctype_digit((string) $request->query('zona')) ? (int) $request->query('zona') : null,
            'estado' => in_array($request->query('estado'), ['activas', 'desactivadas'], true) ? $request->query('estado') : null,
            'anio' => ctype_digit((string) $request->query('anio')) ? (int) $request->query('anio') : null,
        ];

        return view('instituciones.index', [
            'instituciones' => $this->instituciones->listado($filtros),
            'filtros' => $filtros,
            'participaciones' => InstitucionService::PARTICIPACIONES,
            'zonas' => $this->zonas(),
            'anios' => $this->instituciones->anios(),
            'motivos' => EliminarRequest::MOTIVOS,
        ]);
    }

    public function create(): View
    {
        return $this->formulario(null);
    }

    public function store(InstitucionRequest $request): RedirectResponse
    {
        $datos = $request->datos();
        $this->instituciones->registrar($request->user(), $datos);

        return redirect()->route('instituciones.index')->with('estado', "Se registró {$datos['razon_social']}.");
    }

    public function edit(int $institucion): View
    {
        $encontrada = $this->instituciones->buscar($institucion);
        abort_if($encontrada === null, 404);

        return $this->formulario($encontrada);
    }

    public function update(InstitucionRequest $request, int $institucion): RedirectResponse
    {
        abort_if($this->instituciones->buscar($institucion) === null, 404);

        $datos = $request->datos();
        $this->instituciones->actualizar($request->user(), $institucion, $datos);

        return redirect()->route('instituciones.index')->with('estado', "Se guardaron los cambios de {$datos['razon_social']}.");
    }

    public function cambiarEstado(EstadoInstitucionRequest $request, int $institucion): RedirectResponse
    {
        $encontrada = $this->instituciones->buscar($institucion);
        abort_if($encontrada === null, 404);

        $activa = $request->boolean('activa');
        $fecha = (string) $request->validated('fecha');
        $this->instituciones->cambiarEstado($request->user(), $institucion, $activa, $fecha);

        $dia = date('d/m/Y', strtotime($fecha));

        return back()->with('estado', $activa
            ? "Se activó {$encontrada->nombre}: vuelve a participar desde el {$dia}."
            : "Se desactivó {$encontrada->nombre}: dejó de participar el {$dia}. Conserva su número y su historial.");
    }

    public function destroy(EliminarRequest $request, int $institucion): RedirectResponse
    {
        $encontrada = $this->instituciones->buscar($institucion);
        abort_if($encontrada === null, 404);

        if (! $this->instituciones->eliminar($request->user(), $institucion, $request->motivo())) {
            return redirect()->route('instituciones.index')
                ->with('aviso', $this->instituciones->motivoParaNoBorrar($institucion) ?? 'No se pudo borrar la institución.');
        }

        return redirect()->route('instituciones.index')->with('estado', "Se borró {$encontrada->nombre}.");
    }

    private function formulario(?object $institucion): View
    {
        return view('instituciones.formulario', [
            'institucion' => $institucion,
            'tipos' => InstitucionService::TIPOS,
            'participaciones' => InstitucionService::PARTICIPACIONES,
            'sectores' => InstitucionService::SECTORES,
            'universidades' => $this->instituciones->universidades()->reject(fn ($u) => $u->id === $institucion?->id)->values(),
            'numerosUsados' => $this->instituciones->numerosUsados(),
            'zonas' => $this->zonas(),
        ]);
    }

    private function zonas()
    {
        return DB::table('zona_regional')->orderBy('zore_numero')->get(['zore_id_zona_regional as id', 'zore_numero as numero', 'zore_nombre as nombre']);
    }
}

<?php

namespace App\Http\Controllers\Instituciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\EliminarRequest;
use App\Http\Requests\Instituciones\EstadoInstitucionRequest;
use App\Http\Requests\Instituciones\InstitucionRequest;
use App\Http\Requests\Instituciones\ParticipacionRequest;
use App\Http\Requests\Instituciones\RazonSocialRequest;
use App\Services\Instituciones\InstitucionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Instituciones (privilegio gestionar_instituciones, revisado en la ruta).
 * Registrar va en pantalla propia, paso a paso. Se consultan por pestañas y
 * cada sección de la consulta se edita en su propia ventana.
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
        return $this->formulario();
    }

    public function store(InstitucionRequest $request): RedirectResponse
    {
        $datos = $request->datos();
        $id = $this->instituciones->registrar($request->user(), $datos);
        $numero = $this->instituciones->buscar($id)?->numero;

        return redirect()->route('instituciones.index')
            ->with('estado', "Se registró {$datos['razon_social']}".($numero ? " con el número {$numero}" : '').'.');
    }

    /**
     * Consulta: pestañas General, Participación, Razón social y (si es
     * universidad) Sedes. Desde aquí se registran los cambios con historial.
     */
    public function show(Request $request, int $institucion): View
    {
        $encontrada = $this->instituciones->buscar($institucion);
        abort_if($encontrada === null, 404);

        $esUniversidad = $encontrada->tipo === 'Universidad';
        $pestanas = ['general' => 'General', 'participacion' => 'Participación', 'razon-social' => 'Razón social'] + ($esUniversidad ? ['sedes' => 'Sedes'] : []);
        $pestana = array_key_exists((string) $request->query('pestana'), $pestanas) ? (string) $request->query('pestana') : 'general';

        return view('instituciones.consulta', [
            'institucion' => $encontrada,
            'pestanas' => $pestanas,
            'pestana' => $pestana,
            'participaciones' => $this->instituciones->historialParticipacion($institucion),
            'nombres' => $this->instituciones->historialNombres($institucion),
            'sedes' => $esUniversidad ? $this->instituciones->sedes($institucion) : collect(),
            'inicios' => $this->instituciones->iniciosVigentes($institucion),
            'posibles' => $this->instituciones->participacionesPosibles($encontrada),
            'motivoParticipacion' => $this->instituciones->motivoParaNoCambiarParticipacion($encontrada),
            'numerosUsados' => $this->instituciones->numerosUsados(),
            'motivoTipo' => $this->instituciones->motivoParaNoCambiarTipo($encontrada),
            'tipos' => InstitucionService::TIPOS,
            'sectores' => InstitucionService::SECTORES,
            'universidades' => $this->instituciones->universidades()->reject(fn ($u) => $u->id === $encontrada->id)->values(),
            'zonas' => $this->zonas(),
        ]);
    }

    public function cambiarRazonSocial(RazonSocialRequest $request, int $institucion): RedirectResponse
    {
        $encontrada = $this->instituciones->buscar($institucion);
        abort_if($encontrada === null, 404);

        $nombre = $request->razonSocial();
        $desde = (string) $request->validated('desde');
        $this->instituciones->cambiarRazonSocial($request->user(), $institucion, $nombre, $desde);

        return redirect()->route('instituciones.show', ['institucion' => $institucion, 'pestana' => 'razon-social'])
            ->with('estado', 'Se registró el cambio de razón social desde el '.date('d/m/Y', strtotime($desde)).'.');
    }

    public function cambiarParticipacion(ParticipacionRequest $request, int $institucion): RedirectResponse
    {
        $encontrada = $request->institucion();
        abort_if($encontrada === null, 404);

        $participacion = (string) $request->validated('participacion');
        $desde = (string) $request->validated('desde');
        $this->instituciones->cambiarParticipacion($request->user(), $institucion, $participacion, $request->numero(), $desde);

        return redirect()->route('instituciones.show', ['institucion' => $institucion, 'pestana' => 'participacion'])
            ->with('estado', 'Ahora es afiliada desde el '.date('d/m/Y', strtotime($desde)).', con el número '.$this->instituciones->buscar($institucion)?->numero.'.');
    }

    public function update(InstitucionRequest $request, int $institucion): RedirectResponse
    {
        abort_if($this->instituciones->buscar($institucion) === null, 404);

        $this->instituciones->actualizar($request->user(), $institucion, $request->datos());

        return redirect()->route('instituciones.show', ['institucion' => $institucion, 'pestana' => $request->seccion() === 'razon' ? 'razon-social' : null])
            ->with('estado', $request->seccion() === 'razon' ? 'Se corrigió la razón social.' : 'Se guardaron los cambios.');
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

    private function formulario(): View
    {
        return view('instituciones.formulario', [
            'tipos' => InstitucionService::TIPOS,
            'participaciones' => InstitucionService::PARTICIPACIONES,
            'sectores' => InstitucionService::SECTORES,
            'universidades' => $this->instituciones->universidades(),
            'numerosUsados' => $this->instituciones->numerosUsados(),
            'zonas' => $this->zonas(),
        ]);
    }

    private function zonas()
    {
        return DB::table('zona_regional')->orderBy('zore_numero')->get(['zore_id_zona_regional as id', 'zore_numero as numero', 'zore_nombre as nombre']);
    }
}

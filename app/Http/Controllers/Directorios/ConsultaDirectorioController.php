<?php

namespace App\Http\Controllers\Directorios;

use App\Http\Controllers\Controller;
use App\Services\Directorios\ConsultaDirectorioService;
use App\Support\Paginacion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consultar directorios (privilegio ver_directorios, revisado en la ruta):
 * el directorio elegido como está hoy, con sus filtros, para ver, copiar los
 * correos o exportar a CSV. Sin personas ocultas y solo con contactos visibles.
 */
class ConsultaDirectorioController extends Controller
{
    public function __construct(private readonly ConsultaDirectorioService $directorios) {}

    public function index(Request $request): View
    {
        $consulta = $this->consulta($request);

        return view('directorios.index', $consulta + [
            'pagina' => Paginacion::de($consulta['filas']),
            'correos' => $this->directorios->correos($consulta['filas']),
        ]);
    }

    /** El mismo directorio y filtros que la pantalla, completo, en CSV (UTF-8 con BOM para Excel). */
    public function csv(Request $request): StreamedResponse
    {
        ['elegido' => $elegido, 'filtros' => $filtros, 'filas' => $filas, 'esInstituciones' => $esInstituciones] = $this->consulta($request);

        $encabezados = $esInstituciones
            ? ['No. de afiliación', 'Institución', 'Universidad', 'Participación', 'Zona', 'Entidad', 'Titular', 'Grado', 'Cargo', 'Teléfonos', 'Celulares', 'Correos']
            : ['Puesto', 'Grado', 'Nombre', 'Institución u organización', 'Teléfonos', 'Celulares', 'Correos'];
        $renglon = fn (object $f) => $esInstituciones
            ? [$f->numero, $f->institucion, $f->universidad, $f->participacion, $f->zona, $f->entidad, $f->vacante ? 'Vacante' : $f->nombre, $f->grado, $f->vacante ? '' : $f->puesto]
            : [$f->puesto, $f->grado, $f->vacante ? 'Vacante' : $f->nombre, $f->pertenencia];

        $archivo = str($elegido->nombre)->slug().'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($encabezados, $filas, $renglon): void {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\u{FEFF}");
            fputcsv($salida, $encabezados, escape: '');
            foreach ($filas as $fila) {
                $datos = [...$renglon($fila), implode('; ', $fila->telefonos), implode('; ', $fila->celulares), implode('; ', $fila->correos)];
                fputcsv($salida, array_map($this->seguroParaHojaDeCalculo(...), $datos), escape: '');
            }
            fclose($salida);
        }, $archivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * El directorio elegido, los filtros válidos y sus filas.
     *
     * @return array{tipos: Collection<int, object>, elegido: object, esInstituciones: bool, filtros: array<string, mixed>, usa: array<string, bool>, filas: Collection<int, object>, zonas: Collection<int, object>, coordinaciones: Collection<int, object>, entidades: Collection<int, object>, participaciones: array<int, string>}
     */
    private function consulta(Request $request): array
    {
        $tipos = $this->directorios->tipos();
        $elegido = $tipos->firstWhere('id', $request->integer('directorio')) ?? $tipos->first();
        $esInstituciones = $elegido->nombre === ConsultaDirectorioService::INSTITUCIONES;

        $zonas = DB::table('zona_regional')->orderBy('zore_numero')->get(['zore_id_zona_regional as id', 'zore_numero as numero', 'zore_nombre as nombre']);
        $coordinaciones = DB::table('coordinacion_nacional')->orderBy('cona_orden')->get(['cona_id_coordinacion_nacional as id', 'cona_nombre as nombre']);
        $entidades = DB::table('entidad_federativa')->orderBy('enfe_nombre')->get(['enfe_id_entidad_federativa as id', 'enfe_nombre as nombre']);

        // Solo los filtros que aplican a ese directorio, y solo con valores que existen
        $usa = fn (string $filtro) => in_array($filtro, match (true) {
            $esInstituciones => ['zona', 'entidad', 'participacion'],
            $elegido->nombre === 'Consejos Regionales' => ['zona'],
            $elegido->nombre === 'Coordinaciones Nacionales' => ['coordinacion', 'zona'],
            default => [],
        }, true);
        $filtros = [
            'zona' => $usa('zona') ? $zonas->firstWhere('id', $request->integer('zona'))?->id : null,
            'coordinacion' => $usa('coordinacion') ? $coordinaciones->firstWhere('id', $request->integer('coordinacion'))?->id : null,
            'entidad' => $usa('entidad') ? $entidades->firstWhere('id', $request->integer('entidad'))?->id : null,
            'participacion' => $usa('participacion') && in_array($request->query('participacion'), ConsultaDirectorioService::PARTICIPACIONES, true)
                ? (string) $request->query('participacion') : null,
        ];

        $filas = $esInstituciones
            ? $this->directorios->instituciones($filtros['zona'], $filtros['entidad'], $filtros['participacion'])
            : $this->directorios->puestos($elegido->id, $filtros['zona'], $filtros['coordinacion']);

        return [
            'tipos' => $tipos,
            'elegido' => $elegido,
            'esInstituciones' => $esInstituciones,
            'filtros' => $filtros,
            'usa' => array_filter(['zona' => $usa('zona'), 'coordinacion' => $usa('coordinacion'), 'entidad' => $usa('entidad'), 'participacion' => $usa('participacion')]),
            'filas' => $filas,
            'zonas' => $zonas,
            'coordinaciones' => $coordinaciones,
            'entidades' => $entidades,
            'participaciones' => ConsultaDirectorioService::PARTICIPACIONES,
        ];
    }

    /** Un valor que empieza con =, +, - o @ se abriría en Excel como fórmula: se antepone un apóstrofo. */
    private function seguroParaHojaDeCalculo(?string $valor): string
    {
        $valor = (string) $valor;

        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'".$valor : $valor;
    }
}

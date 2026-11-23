<?php

namespace App\Http\Controllers;

use App\Services\BitacoraService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bitácora completa (privilegio ver_bitacora: solo superadministrador).
 * Solo se consulta: nada se edita ni se borra. Filtros por texto, acción,
 * qué se modificó y fechas; de 10 en 10, y todo lo filtrado en CSV.
 */
class BitacoraController extends Controller
{
    public function __construct(private readonly BitacoraService $bitacora) {}

    public function index(Request $request): View
    {
        $filtros = $this->filtros($request);

        return view('bitacora.index', [
            'movimientos' => $this->bitacora->listado($filtros),
            'filtros' => $filtros,
            'acciones' => $this->bitacora->acciones(),
            'tablas' => $this->bitacora->tablas(),
        ]);
    }

    /** Lo mismo que la pantalla, completo, en CSV (UTF-8 con BOM para Excel). */
    public function csv(Request $request): StreamedResponse
    {
        $filtros = $this->filtros($request);

        return response()->streamDownload(function () use ($filtros): void {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\u{FEFF}");
            fputcsv($salida, ['Fecha', 'Hora', 'Quién', 'Acción', 'Qué hizo', 'Motivo', 'Cambios'], escape: '');
            $this->bitacora->porPartes($filtros, function (Collection $movimientos) use ($salida): void {
                foreach ($movimientos as $m) {
                    fputcsv($salida, array_map($this->seguroParaHojaDeCalculo(...), [
                        $m['fecha']->format('d/m/Y'),
                        $m['fecha']->format('H:i'),
                        $m['quien'],
                        $m['accion'],
                        "{$m['quien']} {$m['verbo']} {$m['objeto']}.",
                        $m['motivo'],
                        implode('; ', $m['detalles']),
                    ]), escape: '');
                }
            });
            fclose($salida);
        }, 'bitacora-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Solo filtros válidos: acción y tabla de la lista, fechas reales (si
     * "desde" es posterior a "hasta", se intercambian).
     *
     * @return array{texto: ?string, accion: ?string, tabla: ?string, desde: ?string, hasta: ?string}
     */
    private function filtros(Request $request): array
    {
        $fecha = function (mixed $valor): ?string {
            if (! is_string($valor) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes)) {
                return null;
            }

            return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]) ? $valor : null;
        };

        $desde = $fecha($request->query('desde'));
        $hasta = $fecha($request->query('hasta'));
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'texto' => mb_substr(trim((string) $request->query('texto')), 0, 100) ?: null,
            'accion' => array_key_exists((string) $request->query('accion'), $this->bitacora->acciones()) ? (string) $request->query('accion') : null,
            'tabla' => array_key_exists((string) $request->query('tabla'), $this->bitacora->tablas()) ? (string) $request->query('tabla') : null,
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    /** Un valor que empieza con =, +, - o @ se abriría en Excel como fórmula: se antepone un apóstrofo. */
    private function seguroParaHojaDeCalculo(?string $valor): string
    {
        $valor = (string) $valor;

        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'".$valor : $valor;
    }
}

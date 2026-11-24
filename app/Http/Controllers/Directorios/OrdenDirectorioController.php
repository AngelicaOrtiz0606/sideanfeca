<?php

namespace App\Http\Controllers\Directorios;

use App\Http\Controllers\Controller;
use App\Services\Directorios\DirectorioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Directorios de puestos (M7, privilegio gestionar_directorios): elegir el
 * directorio, agregar o quitar puestos y cambiar su orden.
 */
class OrdenDirectorioController extends Controller
{
    public function __construct(private readonly DirectorioService $directorios) {}

    public function index(Request $request): View
    {
        $tipos = $this->directorios->tipos();
        $elegido = $tipos->firstWhere('id', (int) $request->query('directorio')) ?? $tipos->first();

        return view('directorios.orden', [
            'tipos' => $tipos,
            'elegido' => $elegido,
            'puestos' => $elegido ? $this->directorios->puestosDe($elegido->id) : collect(),
            'disponibles' => $elegido ? $this->directorios->disponibles($elegido->id) : collect(),
        ]);
    }

    public function agregar(Request $request, int $tipo): RedirectResponse
    {
        $this->tipoValido($tipo);
        $datos = $request->validate(
            ['puesto' => ['required', 'integer', Rule::exists('puesto', 'pues_id_puesto')->where('pues_activo', true)]],
            ['puesto.required' => 'Elige el puesto que quieres agregar.', 'puesto.exists' => 'Elige un puesto activo de la lista.'],
        );

        $error = $this->directorios->agregar($request->user(), $tipo, (int) $datos['puesto']);

        return $this->regresar($tipo)->with($error === null ? 'estado' : 'aviso', $error ?? 'Se agregó el puesto al final del directorio.');
    }

    public function quitar(Request $request, int $tipo, int $puesto): RedirectResponse
    {
        $this->tipoValido($tipo);
        $this->directorios->quitar($request->user(), $tipo, $puesto);

        return $this->regresar($tipo)->with('estado', 'Se quitó el puesto del directorio. Los que seguían subieron un lugar.');
    }

    public function guardarOrden(Request $request, int $tipo): RedirectResponse
    {
        $this->tipoValido($tipo);
        $datos = $request->validate([
            'orden' => ['required', 'array'],
            'orden.*' => ['integer', 'distinct'],
            'movido' => ['required', 'integer'],
        ]);

        $error = $this->directorios->reordenar($request->user(), $tipo, (int) $datos['movido'], array_map('intval', $datos['orden']));

        return $this->regresar($tipo)->with(
            $error === null ? 'estado' : 'aviso',
            $error === null ? 'Se guardó el nuevo orden.' : 'El directorio cambió mientras ordenabas. Revisa el orden y guarda otra vez.'
        );
    }

    private function tipoValido(int $tipo): void
    {
        abort_unless(DB::table('tipo_directorio')->where('tidi_id_tipo_directorio', $tipo)->where('tidi_nombre', '!=', DirectorioService::INSTITUCIONES)->exists(), 404);
    }

    private function regresar(int $tipo): RedirectResponse
    {
        return redirect()->route('directorios.orden', ['directorio' => $tipo]);
    }
}

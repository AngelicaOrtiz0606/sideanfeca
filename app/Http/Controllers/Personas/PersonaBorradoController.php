<?php

namespace App\Http\Controllers\Personas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogos\EliminarRequest;
use App\Services\Personas\PersonaService;
use App\Support\FuncionSql;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Borrado de personas (M8). Todo pasa por las funciones de la base, que
 * revisan permisos y reglas y escriben su bitácora:
 * - Borrar (administrador): borrar_persona. Sin cuenta se elimina por
 *   completo; con cuenta se oculta. No con una designación vigente.
 * - Personas ocultas (superadministrador): mostrar_persona para que
 *   vuelva a aparecer, o eliminar_persona_definitiva (con cuenta: se
 *   conservan nombre y CURP y la cuenta se desactiva).
 * - Cancelación ARCO (superadministrador): cancelar_arco, con doble
 *   confirmación (escribir el nombre completo). Borra todo lo personal.
 */
class PersonaBorradoController extends Controller
{
    public function __construct(private readonly PersonaService $personas) {}

    public function destroy(EliminarRequest $request, int $persona): RedirectResponse
    {
        $encontrada = $this->personas->buscar($persona);
        abort_if($encontrada === null, 404);

        if (($motivo = $this->personas->motivoParaNoBorrar($request->user(), $encontrada)) !== null) {
            return redirect()->route('personas.index')->with('aviso', $motivo);
        }

        [$resultado, $error] = FuncionSql::llamar('borrar_persona', [$request->user()->usua_id_usuario, $persona, $request->motivo()]);
        $nombre = $this->personas->nombreCompleto($encontrada);

        return redirect()->route('personas.index')->with(
            $error === null ? 'estado' : 'aviso',
            $error ?? ($encontrada->tiene_cuenta
                ? "Se ocultó a {$nombre}: tiene cuenta de usuario, así que no se borró. Un superadministrador puede volver a mostrarla."
                : "Se borró a {$nombre} por completo.")
        );
    }

    public function ocultas(): View
    {
        return view('personas.ocultas', [
            'ocultas' => $this->personas->ocultas(),
            'motivos' => EliminarRequest::MOTIVOS,
        ]);
    }

    public function mostrar(Request $request, int $persona): RedirectResponse
    {
        $encontrada = $this->personas->cualquiera($persona);
        abort_if($encontrada === null || ! $encontrada->oculta, 404);

        [, $error] = FuncionSql::llamar('mostrar_persona', [$request->user()->usua_id_usuario, $persona]);

        return redirect()->route('personas.ocultas')->with(
            $error === null ? 'estado' : 'aviso',
            $error ?? "{$encontrada->nombre_completo} vuelve a aparecer para los administradores."
        );
    }

    public function definitiva(EliminarRequest $request, int $persona): RedirectResponse
    {
        $encontrada = $this->personas->cualquiera($persona);
        abort_if($encontrada === null, 404);

        [$resultado, $error] = FuncionSql::llamar('eliminar_persona_definitiva', [$request->user()->usua_id_usuario, $persona, $request->motivo()]);

        return redirect()->route('personas.ocultas')->with(
            $error === null ? 'estado' : 'aviso',
            $error ?? ($encontrada->tiene_cuenta
                ? "Se borraron los datos de {$encontrada->nombre_completo}. Se conservaron su nombre y su CURP, y su cuenta quedó desactivada."
                : "Se borró a {$encontrada->nombre_completo} por completo.")
        );
    }

    /**
     * Cancelación ARCO: se confirma escribiendo el nombre completo tal como aparece.
     */
    public function arco(Request $request, int $persona): RedirectResponse
    {
        $encontrada = $this->personas->cualquiera($persona);
        abort_if($encontrada === null, 404);

        $escrito = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $request->input('confirmacion'))));
        if ($escrito !== mb_strtolower($encontrada->nombre_completo)) {
            return back()->withErrors(['confirmacion' => 'Escribe el nombre completo tal como aparece para confirmar.'])->withInput(['_id' => $persona]);
        }

        [, $error] = FuncionSql::llamar('cancelar_arco', [$request->user()->usua_id_usuario, $persona]);
        if ($error !== null) {
            return back()->with('aviso', $error);
        }

        return redirect()->route($encontrada->oculta ? 'personas.ocultas' : 'personas.index')
            ->with('estado', "Cancelación ARCO aplicada a la persona #{$persona}: se borraron todos sus datos personales.");
    }
}

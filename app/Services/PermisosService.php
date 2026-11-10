<?php

namespace App\Services;

use App\Enums\Privilegio;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Revisa los privilegios de una cuenta con la función SQL
 * cuenta_tiene_privilegio (ROL_PRIVILEGIO activo y cuenta activa).
 *
 * - Una sola consulta por petición: la respuesta se guarda en la petición
 *   actual y se descarta al terminar.
 * - Por eso un cambio de privilegios aplica en el siguiente clic, sin cerrar
 *   ni volver a iniciar sesión.
 */
class PermisosService
{
    private const CLAVE = 'sideanfeca.privilegios';

    public function __construct(private readonly Request $peticion) {}

    public function tiene(Usuario $cuenta, Privilegio|string $privilegio): bool
    {
        $nombre = $privilegio instanceof Privilegio ? $privilegio->value : $privilegio;

        return in_array($nombre, $this->de($cuenta), true);
    }

    /**
     * Nombres de los privilegios que tiene la cuenta en este momento.
     *
     * @return array<int, string>
     */
    public function de(Usuario $cuenta): array
    {
        $guardados = $this->peticion->attributes->get(self::CLAVE, []);
        $id = $cuenta->getKey();

        if (! array_key_exists($id, $guardados)) {
            $guardados[$id] = DB::table('privilegio')
                ->whereRaw('cuenta_tiene_privilegio(?, priv_nombre)', [$id])
                ->orderBy('priv_nombre')
                ->pluck('priv_nombre')
                ->all();

            $this->peticion->attributes->set(self::CLAVE, $guardados);
        }

        return $guardados[$id];
    }
}

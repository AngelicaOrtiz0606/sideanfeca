<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Llama una función de la base (borrar_persona, cancelar_arco, …). Las
 * funciones revisan permisos y reglas, escriben su bitácora y devuelven un
 * texto. Si rechazan la acción (raise exception), su mensaje ya está escrito
 * para la persona usuaria y se devuelve como error.
 */
class FuncionSql
{
    /**
     * @param  array<int, mixed>  $parametros
     * @return array{0: ?string, 1: ?string} [texto que devolvió la función, error en palabras]
     */
    public static function llamar(string $funcion, array $parametros): array
    {
        $marcas = implode(', ', array_fill(0, count($parametros), '?'));

        try {
            $fila = DB::selectOne("select {$funcion}({$marcas}) as resultado", $parametros);

            return [$fila->resultado ?? null, null];
        } catch (QueryException $error) {
            if (preg_match('/ERROR:\s+(.+?)(\s+\(Connection:|\s+CONTEXT:|\n|$)/u', $error->getMessage(), $partes) === 1) {
                return [null, trim($partes[1])];
            }
            throw $error;
        }
    }
}

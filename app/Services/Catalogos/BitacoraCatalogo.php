<?php

namespace App\Services\Catalogos;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Registro en la bitácora para los catálogos, siempre con las funciones SQL
 * bitacora_registrar y bitacora_detalle_registrar (nunca directo en las tablas).
 *
 * Ojo: los campos "Nombre", "Correo", "Contacto", etc. están reservados para
 * datos personales y la base no guarda su valor. En los catálogos se usa el
 * nombre completo del campo ("Nombre de la zona", "Nombre de la coordinación").
 */
trait BitacoraCatalogo
{
    private function bitacora(Usuario $actor, string $accion, string $tabla, int $referencia, ?string $motivo = null): int
    {
        return (int) DB::selectOne(
            'select bitacora_registrar(?, ?, ?, ?, ?) as id',
            [$actor->usua_id_usuario, $accion, $tabla, $referencia, $motivo],
        )->id;
    }

    private function detalle(int $idBitacora, int $referencia, string $campo, ?string $anterior, ?string $nuevo): void
    {
        DB::select(
            'select bitacora_detalle_registrar(?, ?, ?, ?, ?)',
            [$idBitacora, $referencia, $campo, $anterior, $nuevo],
        );
    }
}

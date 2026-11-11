<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Coordinación nacional temática (tabla COORDINACION_NACIONAL).
 * - Nunca se borra: se desactiva, y sus puestos e historial se conservan.
 * - Su orden (1, 2, 3...) no tiene huecos ni repetidos; se cambia solo con
 *   la función SQL reordenar_coordinaciones.
 * Se crea y cambia solo con CoordinacionService, que registra la bitácora.
 */
class CoordinacionNacional extends Model
{
    protected $table = 'coordinacion_nacional';

    protected $primaryKey = 'cona_id_coordinacion_nacional';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cona_orden' => 'integer',
            'cona_activo' => 'boolean',
        ];
    }
}

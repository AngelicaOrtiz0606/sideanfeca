<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cargo (tabla CARGO): nombre en masculino y en femenino, y su nivel.
 * El nivel define el ámbito de sus puestos (lo revisa validar_puesto):
 *   Nacional: sin zona ni institución · Regional: con zona · Institucional: de una institución.
 * Se crea y cambia solo con CargoService, que registra la bitácora.
 */
class Cargo extends Model
{
    public const NIVELES = ['Nacional', 'Regional', 'Institucional'];

    protected $table = 'cargo';

    protected $primaryKey = 'carg_id_cargo';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['carg_activo' => 'boolean'];
    }
}

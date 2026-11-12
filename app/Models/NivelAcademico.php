<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Grado académico (tabla NIVEL_ACADEMICO): nombre y abreviaturas en
 * masculino y femenino (Dr. / Dra.). Se asigna a las personas (M5).
 * Se crea y cambia solo con NivelAcademicoService, que registra la bitácora.
 */
class NivelAcademico extends Model
{
    protected $table = 'nivel_academico';

    protected $primaryKey = 'niac_id_nivel_academico';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['niac_activo' => 'boolean'];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Privilegio del sistema (tabla PRIVILEGIO). PRIV_NOMBRE es la clave que
 * usan el código y las funciones SQL, por ejemplo 'gestionar_usuarios'.
 * Los carga SeguridadSeeder.
 */
class Privilegio extends Model
{
    protected $table = 'privilegio';

    protected $primaryKey = 'priv_id_privilegio';

    public $timestamps = false;
}

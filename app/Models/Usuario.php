<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable
{
    // Nombre real de la tabla y de sus columnas (en minúsculas, como las crea PostgreSQL)
    protected $table = 'usuario';
    protected $primaryKey = 'usua_id_usuario';

    // Nuestra tabla no tiene created_at ni updated_at
    public $timestamps = false;

    // Nunca mostrar la contraseña en respuestas o registros
    protected $hidden = ['usua_contrasenia'];

    // Laravel debe usar nuestra columna de contraseña
    public function getAuthPasswordName(): string
    {
        return 'usua_contrasenia';
    }

    public function getAuthPassword(): string
    {
        return $this->usua_contrasenia;
    }
}

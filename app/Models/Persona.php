<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Persona registrada en los directorios (tabla PERSONA).
 *
 * En M1 solo se usa para mostrar el nombre de quien inicia sesión.
 * Las altas, cambios y borrados llegan en M5 y M8, siempre a través de
 * las funciones SQL que registran la bitácora.
 */
class Persona extends Model
{
    protected $table = 'persona';

    protected $primaryKey = 'pers_id_persona';

    public $timestamps = false;

    // La CURP es un dato personal: no sale al convertir el modelo a arreglo o JSON
    protected $hidden = ['pers_curp'];

    protected function casts(): array
    {
        return [
            'pers_oculta_admin' => 'boolean',
        ];
    }

    public function usuario(): HasOne
    {
        return $this->hasOne(Usuario::class, 'usua_id_persona', 'pers_id_persona');
    }

    /**
     * Nombre y apellidos en una sola cadena. El segundo apellido es opcional.
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter([
            $this->pers_nombre,
            $this->pers_primer_apellido,
            $this->pers_segundo_apellido,
        ]))));
    }
}

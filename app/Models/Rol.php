<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rol de una cuenta (tabla ROL): Superadministrador o Administrador.
 * Los carga SeguridadSeeder.
 */
class Rol extends Model
{
    public const SUPERADMINISTRADOR = 'Superadministrador';

    public const ADMINISTRADOR = 'Administrador';

    protected $table = 'rol';

    protected $primaryKey = 'rol_id_rol';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'rol_activo' => 'boolean',
        ];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'usua_id_rol', 'rol_id_rol');
    }

    /**
     * Privilegios ACTIVOS del rol (ROL_PRIVILEGIO.ROPR_ACTIVO = true),
     * con el mismo criterio que la función SQL cuenta_tiene_privilegio.
     */
    public function privilegios(): BelongsToMany
    {
        return $this->belongsToMany(
            Privilegio::class,
            'rol_privilegio',
            'ropr_id_rol',
            'ropr_id_privilegio',
        )->wherePivot('ropr_activo', true);
    }
}

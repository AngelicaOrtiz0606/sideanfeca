<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entidad federativa (tabla ENTIDAD_FEDERATIVA): las 32 se cargan con
 * CatalogosInicialesSeeder. Desde el sistema solo se cambia su zona regional.
 */
class EntidadFederativa extends Model
{
    protected $table = 'entidad_federativa';

    protected $primaryKey = 'enfe_id_entidad_federativa';

    public $timestamps = false;

    public function zona(): BelongsTo
    {
        return $this->belongsTo(ZonaRegional::class, 'enfe_id_zona_regional', 'zore_id_zona_regional');
    }
}

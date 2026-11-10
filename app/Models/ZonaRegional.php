<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zona regional de ANFECA (tabla ZONA_REGIONAL): número del 1 al 7 y nombre,
 * ambos únicos. Se crea, cambia y borra solo con ZonaService, que registra
 * la bitácora.
 */
class ZonaRegional extends Model
{
    protected $table = 'zona_regional';

    protected $primaryKey = 'zore_id_zona_regional';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['zore_numero' => 'integer'];
    }

    public function entidades(): HasMany
    {
        return $this->hasMany(EntidadFederativa::class, 'enfe_id_zona_regional', 'zore_id_zona_regional')
            ->orderBy('enfe_nombre');
    }

    /**
     * "Zona 1 · Noroeste": así se nombra en la bitácora y en las pantallas.
     */
    public function etiqueta(): string
    {
        return "Zona {$this->zore_numero} · {$this->zore_nombre}";
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Domicilio con más espacio: hay direcciones de oficina que no cabían, por
 * ejemplo "Blvd. Manuel Ávila Camacho 5, Torre B, Piso 10, Suite 1000".
 * - Calle: de 60 a 100 caracteres.
 * - Número exterior: de 10 a 20 (por ejemplo "Km 12.5" o "Mz 5 Lt 12").
 * - Número interior: de 10 a 60 (torre, piso, suite, despacho...).
 * Solo se amplían: no se pierde ningún dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table DOMICILIO
            alter column DOMI_CALLE type VARCHAR(100),
            alter column DOMI_NUM_EXT type VARCHAR(20),
            alter column DOMI_NUM_INT type VARCHAR(60)');
    }

    public function down(): void
    {
        // Falla si ya hay domicilios más largos: así no se recorta ninguno sin aviso
        DB::statement('alter table DOMICILIO
            alter column DOMI_CALLE type VARCHAR(60),
            alter column DOMI_NUM_EXT type VARCHAR(10),
            alter column DOMI_NUM_INT type VARCHAR(10)');
    }
};

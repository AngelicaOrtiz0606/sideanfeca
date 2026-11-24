<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nombre de cargo con más espacio: de 100 a 150 caracteres, en masculino y en
 * femenino. Hay cargos de organizaciones con nombre largo, por ejemplo
 * "Presidente del Consejo de Acreditación en la Enseñanza de la Contaduría
 * y Administración, A.C. (CACECA)" (103). Solo se amplía: no se pierde nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table CARGO
            alter column CARG_NOMBRE_M type VARCHAR(150),
            alter column CARG_NOMBRE_F type VARCHAR(150)');
    }

    public function down(): void
    {
        // Falla si ya hay nombres más largos: así no se recorta ninguno sin aviso
        DB::statement('alter table CARGO
            alter column CARG_NOMBRE_M type VARCHAR(100),
            alter column CARG_NOMBRE_F type VARCHAR(100)');
    }
};

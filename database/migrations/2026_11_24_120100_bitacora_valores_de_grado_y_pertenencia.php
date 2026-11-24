<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La bitácora guarda el antes y el después del grado académico y de la
 * pertenencia (por ejemplo "FCA UNAM → CACECA"): no identifican a nadie por
 * sí solos. Los demás datos personales (nombre, apellidos, CURP, género,
 * correo y contactos) siguen sin valor: la bitácora no se puede borrar y la
 * cancelación ARCO exige borrar esos datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->camposPersonales("'Nombre','Primer apellido','Segundo apellido','CURP','Género','Correo','Contacto'");
    }

    public function down(): void
    {
        $this->camposPersonales("'Nombre','Primer apellido','Segundo apellido','CURP','Género','Correo','Contacto','Grado académico','Pertenencia'");
    }

    private function camposPersonales(string $campos): void
    {
        DB::unprepared(<<<SQL
            create or replace function bitacora_detalle_registrar(p_bitacora INT4, p_referencia INT4, p_campo VARCHAR,
                                                                  p_anterior VARCHAR, p_nuevo VARCHAR)
            returns void as \$\$
            begin
                if p_campo in ({$campos})
                   and (p_anterior is not null or p_nuevo is not null) then
                    raise exception 'Los campos personales no guardan valores en la bitácora (campo: %).', p_campo;
                end if;

                insert into BITACORA_DETALLE (BIDE_ID_BITACORA, BIDE_REFERENCIA, BIDE_CAMPO, BIDE_ANTERIOR, BIDE_NUEVO)
                values (p_bitacora, p_referencia, p_campo, p_anterior, p_nuevo);
            end;
            \$\$ language plpgsql;
            SQL);
    }
};

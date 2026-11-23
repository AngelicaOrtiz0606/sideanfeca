<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Un puesto nacional puede ser de una organización (por ejemplo, Director
 * General de CACECA): PUESTO.PUES_ID_ORGANIZACION.
 * - Solo los puestos nacionales la llevan, y nunca junto con una coordinación.
 * - Quien lo ocupe debe pertenecer a esa organización (como los puestos
 *   institucionales con su institución).
 * - El puesto sigue sin repetirse: la organización entra en PUES_PUESTO_UQ.
 * No cambia ningún dato que ya exista.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            alter table PUESTO add column PUES_ID_ORGANIZACION INT4 null;
            alter table PUESTO add constraint FK_PUES_ORGANIZACION foreign key (PUES_ID_ORGANIZACION)
                references ORGANIZACION (ORGA_ID_ORGANIZACION);
            create index PUES_ORGANIZACION_FK on PUESTO (PUES_ID_ORGANIZACION);

            alter table PUESTO drop constraint PUES_PUESTO_UQ;
            alter table PUESTO add constraint PUES_PUESTO_UQ unique nulls not distinct (
                PUES_ID_CARGO,
                PUES_ID_ZONA_REGIONAL,
                PUES_ID_COORDINACION_NACIONAL,
                PUES_ID_INSTITUCION_EDUCATIVA,
                PUES_ID_ORGANIZACION
            );

            create or replace function validar_puesto()
            returns trigger as $$
            declare
                v_nivel varchar(15);
            begin
                select CARG_NIVEL into v_nivel
                from CARGO
                where CARG_ID_CARGO = new.PUES_ID_CARGO;

                if v_nivel = 'Institucional' then
                    if new.PUES_ID_INSTITUCION_EDUCATIVA is null
                       or new.PUES_ID_ZONA_REGIONAL is not null
                       or new.PUES_ID_COORDINACION_NACIONAL is not null
                       or new.PUES_ID_ORGANIZACION is not null then
                        raise exception 'Un puesto institucional solo lleva institución.';
                    end if;

                elsif v_nivel = 'Regional' then
                    if new.PUES_ID_ZONA_REGIONAL is null
                       or new.PUES_ID_INSTITUCION_EDUCATIVA is not null
                       or new.PUES_ID_ORGANIZACION is not null then
                        raise exception 'Un puesto regional lleva zona y no institución ni organización.';
                    end if;

                elsif v_nivel = 'Nacional' then
                    if new.PUES_ID_ZONA_REGIONAL is not null
                       or new.PUES_ID_INSTITUCION_EDUCATIVA is not null then
                        raise exception 'Un puesto nacional solo puede llevar coordinación u organización.';
                    end if;
                    if new.PUES_ID_COORDINACION_NACIONAL is not null and new.PUES_ID_ORGANIZACION is not null then
                        raise exception 'Un puesto nacional lleva coordinación u organización, no las dos.';
                    end if;
                end if;

                if new.PUES_TITULARIDAD and v_nivel <> 'Institucional' then
                    raise exception 'Solo los puestos institucionales pueden tener titularidad.';
                end if;

                return new;
            end;
            $$ language plpgsql;

            create or replace function validar_designacion()
            returns trigger as $$
            declare
                v_inst_puesto  INT4;
                v_org_puesto   INT4;
                v_inst_persona INT4;
                v_org_persona  INT4;
            begin
                select PUES_ID_INSTITUCION_EDUCATIVA, PUES_ID_ORGANIZACION into v_inst_puesto, v_org_puesto
                from PUESTO where PUES_ID_PUESTO = new.DESI_ID_PUESTO;

                select PERS_ID_INSTITUCION_EDUCATIVA, PERS_ID_ORGANIZACION
                  into v_inst_persona, v_org_persona
                from PERSONA where PERS_ID_PERSONA = new.DESI_ID_PERSONA;

                -- Un puesto inactivo no recibe designaciones nuevas
                if not (select PUES_ACTIVO from PUESTO where PUES_ID_PUESTO = new.DESI_ID_PUESTO) then
                    raise exception 'El puesto está inactivo.';
                end if;

                -- Toda designación exige pertenencia a una institución u organización
                if v_inst_persona is null and v_org_persona is null then
                    raise exception 'Para tener designación, la persona debe pertenecer a una institución u organización.';
                end if;

                -- Si el puesto es institucional, la persona debe ser de esa misma institución
                if v_inst_puesto is not null and v_inst_persona is distinct from v_inst_puesto then
                    raise exception 'La persona debe pertenecer a la institución del puesto.';
                end if;

                -- Si el puesto es de una organización, la persona debe ser de esa misma organización
                if v_org_puesto is not null and v_org_persona is distinct from v_org_puesto then
                    raise exception 'La persona debe pertenecer a la organización del puesto.';
                end if;

                return new;
            end;
            $$ language plpgsql;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            create or replace function validar_designacion()
            returns trigger as $$
            declare
                v_inst_puesto  INT4;
                v_inst_persona INT4;
                v_org_persona  INT4;
            begin
                select PUES_ID_INSTITUCION_EDUCATIVA into v_inst_puesto
                from PUESTO where PUES_ID_PUESTO = new.DESI_ID_PUESTO;

                select PERS_ID_INSTITUCION_EDUCATIVA, PERS_ID_ORGANIZACION
                  into v_inst_persona, v_org_persona
                from PERSONA where PERS_ID_PERSONA = new.DESI_ID_PERSONA;

                if not (select PUES_ACTIVO from PUESTO where PUES_ID_PUESTO = new.DESI_ID_PUESTO) then
                    raise exception 'El puesto está inactivo.';
                end if;

                if v_inst_persona is null and v_org_persona is null then
                    raise exception 'Para tener designación, la persona debe pertenecer a una institución u organización.';
                end if;

                if v_inst_puesto is not null and v_inst_persona is distinct from v_inst_puesto then
                    raise exception 'La persona debe pertenecer a la institución del puesto.';
                end if;

                return new;
            end;
            $$ language plpgsql;

            create or replace function validar_puesto()
            returns trigger as $$
            declare
                v_nivel varchar(15);
            begin
                select CARG_NIVEL into v_nivel
                from CARGO
                where CARG_ID_CARGO = new.PUES_ID_CARGO;

                if v_nivel = 'Institucional' then
                    if new.PUES_ID_INSTITUCION_EDUCATIVA is null
                       or new.PUES_ID_ZONA_REGIONAL is not null
                       or new.PUES_ID_COORDINACION_NACIONAL is not null then
                        raise exception 'Un puesto institucional solo lleva institución.';
                    end if;

                elsif v_nivel = 'Regional' then
                    if new.PUES_ID_ZONA_REGIONAL is null
                       or new.PUES_ID_INSTITUCION_EDUCATIVA is not null then
                        raise exception 'Un puesto regional lleva zona y no institución.';
                    end if;

                elsif v_nivel = 'Nacional' then
                    if new.PUES_ID_ZONA_REGIONAL is not null
                       or new.PUES_ID_INSTITUCION_EDUCATIVA is not null then
                        raise exception 'Un puesto nacional solo puede llevar coordinación.';
                    end if;
                end if;

                if new.PUES_TITULARIDAD and v_nivel <> 'Institucional' then
                    raise exception 'Solo los puestos institucionales pueden tener titularidad.';
                end if;

                return new;
            end;
            $$ language plpgsql;

            -- Falla si ya hay dos puestos que solo se distinguen por su organización: así no se pierde ninguno sin aviso
            alter table PUESTO drop constraint PUES_PUESTO_UQ;
            alter table PUESTO add constraint PUES_PUESTO_UQ unique nulls not distinct (
                PUES_ID_CARGO,
                PUES_ID_ZONA_REGIONAL,
                PUES_ID_COORDINACION_NACIONAL,
                PUES_ID_INSTITUCION_EDUCATIVA
            );
            drop index PUES_ORGANIZACION_FK;
            alter table PUESTO drop column PUES_ID_ORGANIZACION;
            SQL);
    }
};

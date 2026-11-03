/*==============================================================*/
/* PROYECTO:  SIDEANFECA                                        */
/* DBMS:      PostgreSQL 18                                     */
/* Versión:   script_19                                         */
/*                                                              */
/* - Usuarios, roles, privilegios, bitácora e inicios de sesión:*/
/*   tablas propias (sección 7). Laravel autentica con USUARIO  */
/*   y autoriza con ROL / ROL_PRIVILEGIO / PRIVILEGIO.          */
/* - Catálogos de domicilio (entidad, municipio, colonia): se   */
/*   cargan desde el CSV oficial de Correos de México.          */
/* - Orden de puestos y coordinaciones: manual. Se reordena con */
/*   las funciones reordenar_* (una transacción, en bitácora).  */
/* - Fechas de designación: se guarda el ÚLTIMO día con cargo    */
/*   (rango cerrado). El formulario captura el primer día sin   */
/*   cargo y Laravel guarda ese día menos uno.                  */
/* - Reglas que dependen de Laravel (no de la BD):              */
/*     · Coordinador y secretario técnico regional con          */
/*       coordinación                                           */
/*     · Teléfono con lada/extensión según TIPO_CONTACTO        */
/*==============================================================*/

create extension if not exists btree_gist;


/*==============================================================*/
/* REGLAS DE BORRADO Y BITÁCORA (script 19)
   - Nada se borra en cascada desde la interfaz. Las funciones de
     borrado son la única vía, y dejan registro en bitácora.
   - Persona SIN cuenta: borrado completo (eliminar_persona_definitiva).
   - Persona CON cuenta:
       · Administrador: "borrar" = OCULTAR (PERS_OCULTA_ADMIN). No se
         borra nada. El superadmin la sigue viendo.
       · Superadmin (decisión de la organización): se borran contactos,
         grados, designaciones y pertenencia; se conservan nombre y
         CURP; la cuenta se desactiva.
       · Cancelación ARCO (cancelar_arco): se borra todo lo personal,
         incluido nombre y CURP. La cuenta queda anonimizada.
   - Toda persona tiene al menos una designación o una cuenta
     (trigger diferido: validar_persona_con_razon).
   - Designaciones: solo se borran desde eliminar_designacion_erronea
     o eliminar_persona_definitiva (trigger bloquear_borrado_designacion).
   - Cuentas (USUARIO): nunca se borran. Se desactivan o se anonimizan.
   - Bitácora: solo inserción. Guarda referencias (números), no nombres
     ni correos en texto. Los nombres se muestran desde la ficha.
   - Inicios de sesión (LOG_ACCESO): solo inserción, sin correos.
   - Recuperación de contraseña: tabla temporal con hash del código. */

/*==============================================================*/
/* 1. GEOGRAFÍA                                                 */
/*==============================================================*/

/* Zonas regionales de ANFECA (1 a 7) */
create table ZONA_REGIONAL (
   ZORE_ID_ZONA_REGIONAL SERIAL not null,
   ZORE_NUMERO           INT4 not null,
   ZORE_NOMBRE           VARCHAR(60) not null,
   constraint PK_ZONA_REGIONAL primary key (ZORE_ID_ZONA_REGIONAL),
   constraint ZORE_NUMERO_UQ unique (ZORE_NUMERO),
   constraint ZORE_NOMBRE_UQ unique (ZORE_NOMBRE),
   constraint CKC_ZORE_NUMERO check (ZORE_NUMERO between 1 and 7)
);

/* Entidades federativas. ENFE_CLAVE = clave oficial de 2 dígitos
   (ej. '09' = CDMX). Se carga por seeder, porque aquí se asigna
   la zona regional. */
create table ENTIDAD_FEDERATIVA (
   ENFE_ID_ENTIDAD_FEDERATIVA SERIAL not null,
   ENFE_CLAVE            CHAR(2) not null,
   ENFE_NOMBRE           VARCHAR(60) not null,
   ENFE_ID_ZONA_REGIONAL INT4 not null,
   constraint PK_ENTIDAD_FEDERATIVA primary key (ENFE_ID_ENTIDAD_FEDERATIVA),
   constraint ENFE_CLAVE_UQ unique (ENFE_CLAVE),
   constraint ENFE_NOMBRE_UQ unique (ENFE_NOMBRE),
   constraint FK_ENFE_ZONA foreign key (ENFE_ID_ZONA_REGIONAL)
      references ZONA_REGIONAL (ZORE_ID_ZONA_REGIONAL)
);
create index ENFE_ZONA_FK on ENTIDAD_FEDERATIVA (ENFE_ID_ZONA_REGIONAL);

/* Municipios y alcaldías. Se importan desde el catálogo de Correos. */
create table ALCALDIA_MUNICIPIO (
   ALMU_ID_ALCALDIA_MUNICIPIO SERIAL not null,
   ALMU_ID_ENTIDAD_FEDERATIVA INT4 not null,
   ALMU_CLAVE            CHAR(3) not null,
   ALMU_NOMBRE           VARCHAR(100) not null,
   constraint PK_ALCALDIA_MUNICIPIO primary key (ALMU_ID_ALCALDIA_MUNICIPIO),
   constraint ALMU_ENTIDAD_CLAVE_UQ unique (ALMU_ID_ENTIDAD_FEDERATIVA, ALMU_CLAVE),
   constraint FK_ALMU_ENTIDAD foreign key (ALMU_ID_ENTIDAD_FEDERATIVA)
      references ENTIDAD_FEDERATIVA (ENFE_ID_ENTIDAD_FEDERATIVA)
);
create index ALMU_ENTIDAD_FK on ALCALDIA_MUNICIPIO (ALMU_ID_ENTIDAD_FEDERATIVA);

/* Colonias. Un código postal tiene varias colonias, por eso la
   llave es (código postal, id de asentamiento). Se importan. */
create table COLONIA (
   COLO_ID_COLONIA       SERIAL not null,
   COLO_ID_ALCALDIA_MUNICIPIO INT4 not null,
   COLO_CODIGO_POSTAL    CHAR(5) not null,
   COLO_ID_ASENTA_CPCONS CHAR(4) not null,
   COLO_NOMBRE           VARCHAR(100) not null,
   constraint PK_COLONIA primary key (COLO_ID_COLONIA),
   constraint COLO_CP_ASENTA_UQ unique (COLO_CODIGO_POSTAL, COLO_ID_ASENTA_CPCONS),
   constraint CKC_COLO_CP check (COLO_CODIGO_POSTAL ~ '^[0-9]{5}$'),
   constraint FK_COLO_ALCALDIA foreign key (COLO_ID_ALCALDIA_MUNICIPIO)
      references ALCALDIA_MUNICIPIO (ALMU_ID_ALCALDIA_MUNICIPIO)
);
create index COLO_ALCALDIA_FK on COLONIA (COLO_ID_ALCALDIA_MUNICIPIO);

/* Domicilio: calle y números. La colonia viene del catálogo. */
create table DOMICILIO (
   DOMI_ID_DOMICILIO     SERIAL not null,
   DOMI_ID_COLONIA       INT4 not null,
   DOMI_CALLE            VARCHAR(60) not null,
   DOMI_NUM_EXT          VARCHAR(10) not null,
   DOMI_NUM_INT          VARCHAR(10) null,
   constraint PK_DOMICILIO primary key (DOMI_ID_DOMICILIO),
   constraint FK_DOMI_COLONIA foreign key (DOMI_ID_COLONIA)
      references COLONIA (COLO_ID_COLONIA)
);
create index DOMI_COLONIA_FK on DOMICILIO (DOMI_ID_COLONIA);


/*==============================================================*/
/* 2. CATÁLOGOS                                                 */
/*==============================================================*/

/* Universidad, Facultad, Campus */
create table TIPO_INSTITUCION_EDUCATIVA (
   TIIE_ID_TIPO_INSTITUCION SERIAL not null,
   TIIE_NOMBRE           VARCHAR(15) not null,
   constraint PK_TIPO_INSTITUCION_EDUCATIVA primary key (TIIE_ID_TIPO_INSTITUCION),
   constraint TIIE_NOMBRE_UQ unique (TIIE_NOMBRE)
);

/* Consejo Nacional Directivo, Consejos Regionales,
   Coordinaciones Nacionales, Instituciones */
create table TIPO_DIRECTORIO (
   TIDI_ID_TIPO_DIRECTORIO SERIAL not null,
   TIDI_NOMBRE           VARCHAR(50) not null,
   TIDI_DESCRIPCION      VARCHAR(100) null,
   constraint PK_TIPO_DIRECTORIO primary key (TIDI_ID_TIPO_DIRECTORIO),
   constraint TIDI_NOMBRE_UQ unique (TIDI_NOMBRE)
);

/* Teléfono: lada y/o extensión.
   Celular: solo lada (opcional).
   Correo: sin campos extra. */
create table TIPO_CONTACTO (
   TICO_ID_TIPO_CONTACTO SERIAL not null,
   TICO_NOMBRE           VARCHAR(10) not null,
   TICO_PERMITE_LADA     BOOL not null default false,
   TICO_PERMITE_EXTENSION BOOL not null default false,
   constraint PK_TIPO_CONTACTO primary key (TICO_ID_TIPO_CONTACTO),
   constraint TICO_NOMBRE_UQ unique (TICO_NOMBRE)
);

/* Grados académicos. Abreviatura masculina y femenina. */
create table NIVEL_ACADEMICO (
   NIAC_ID_NIVEL_ACADEMICO SERIAL not null,
   NIAC_NOMBRE           VARCHAR(50) not null,
   NIAC_ABR_M            VARCHAR(10) not null,
   NIAC_ABR_F            VARCHAR(10) not null,
   NIAC_ACTIVO           BOOL not null default true,
   constraint PK_NIVEL_ACADEMICO primary key (NIAC_ID_NIVEL_ACADEMICO),
   constraint NIAC_NOMBRE_UQ unique (NIAC_NOMBRE)
);

/* Coordinaciones nacionales temáticas. Se pueden desactivar. */
create table COORDINACION_NACIONAL (
   CONA_ID_COORDINACION_NACIONAL SERIAL not null,
   CONA_NOMBRE           VARCHAR(70) not null,
   CONA_DESCRIPCION      VARCHAR(100) null,
   CONA_ORDEN            INT4 not null,
   CONA_ACTIVO           BOOL not null default true,
   constraint PK_COORDINACION_NACIONAL primary key (CONA_ID_COORDINACION_NACIONAL),
   constraint CONA_NOMBRE_UQ unique (CONA_NOMBRE),
   /* Orden de visualización: se puede reordenar en una sola transacción */
   constraint CONA_ORDEN_UQ unique (CONA_ORDEN) deferrable initially deferred,
   constraint CKC_CONA_ORDEN check (CONA_ORDEN >= 1)
);

/* Cargos. El nivel define el ámbito de sus puestos:
   Nacional   -> sin zona ni institución (coordinación opcional)
   Regional   -> lleva zona (coordinación opcional; Laravel exige
                 coordinación para coordinador y secretario técnico)
   Institucional -> lleva institución */
create table CARGO (
   CARG_ID_CARGO         SERIAL not null,
   CARG_NOMBRE_M         VARCHAR(100) not null,
   CARG_NOMBRE_F         VARCHAR(100) not null,
   CARG_NIVEL            VARCHAR(15) not null,
   CARG_ACTIVO           BOOL not null default true,
   constraint PK_CARGO primary key (CARG_ID_CARGO),
   constraint CARG_NOMBRE_M_UQ unique (CARG_NOMBRE_M),
   constraint CARG_NOMBRE_F_UQ unique (CARG_NOMBRE_F),
   constraint CKC_CARG_NIVEL check (CARG_NIVEL in ('Nacional','Regional','Institucional'))
);

/* Organizaciones que no son instituciones afiliadas ni observadoras
   (ej. CACECA). No tienen número de afiliación ni zona. Solo
   tienen domicilio. */
create table ORGANIZACION (
   ORGA_ID_ORGANIZACION  SERIAL not null,
   ORGA_NOMBRE           VARCHAR(150) not null,
   ORGA_ID_DOMICILIO     INT4 not null,
   ORGA_SITIO_WEB        VARCHAR(200) null,
   ORGA_ACTIVO           BOOL not null default true,
   constraint PK_ORGANIZACION primary key (ORGA_ID_ORGANIZACION),
   constraint ORGA_NOMBRE_UQ unique (ORGA_NOMBRE),
   constraint FK_ORGA_DOMICILIO foreign key (ORGA_ID_DOMICILIO)
      references DOMICILIO (DOMI_ID_DOMICILIO)
);
create index ORGA_DOMICILIO_FK on ORGANIZACION (ORGA_ID_DOMICILIO);


/*==============================================================*/
/* 3. INSTITUCIONES                                             */
/*==============================================================*/

/* Instituciones educativas (la facultad o escuela es la que tiene
   número de afiliación; su padre es la universidad).

   INED_PARTICIPACION = participación ACTUAL. El historial está en
   INSTITUCION_PARTICIPACION. Laravel mantiene ambas sincronizadas
   en la misma transacción.

   - Matriz:      solo universidades. Sin número ni padre.
                  Dirección y zona opcionales.
   - Afiliada:    número obligatorio (7 dígitos, texto).
                  Dirección y zona obligatorias.
   - Observadora: sin número. Dirección y zona obligatorias.
   Las instituciones no se borran. Si no pagan se desactivan con
   INED_ACTIVO = false y conservan su número. */
create table INSTITUCION_EDUCATIVA (
   INED_ID_INSTITUCION_EDUCATIVA SERIAL not null,
   INED_ID_TIPO_INSTITUCION INT4 not null,
   INED_ID_INSTITUCION_PADRE INT4 null,
   INED_PARTICIPACION    VARCHAR(15) not null,
   INED_NUM_AFILIACION   CHAR(7) null,
   INED_SECTOR           VARCHAR(10) not null,
   INED_SITIO_WEB        VARCHAR(200) null,
   INED_ID_DOMICILIO     INT4 null,
   /* Zona: se sugiere desde la entidad del domicilio al registrar
      (lo hace Laravel), pero el usuario puede cambiarla. La BD no
      la deriva. Obligatoria para afiliada y observadora. */
   INED_ID_ZONA_REGIONAL INT4 null,
   INED_ACTIVO           BOOL not null default true,
   INED_FECHA_REGISTRO   TIMESTAMP not null default now(),

   constraint PK_INSTITUCION_EDUCATIVA primary key (INED_ID_INSTITUCION_EDUCATIVA),
   constraint INED_NUM_AFILIACION_UQ unique (INED_NUM_AFILIACION),

   constraint CKC_INED_PARTICIPACION check (
       INED_PARTICIPACION in ('Matriz','Afiliada','Observadora')),
   constraint CKC_INED_SECTOR check (INED_SECTOR in ('Publica','Privada')),

   /* Una matriz no tiene número ni padre */
   constraint CKC_INED_MATRIZ check (
       INED_PARTICIPACION <> 'Matriz'
       or (INED_NUM_AFILIACION is null and INED_ID_INSTITUCION_PADRE is null)
   ),

   /* Afiliadas y observadoras deben tener dirección y zona */
   constraint CKC_INED_DIRECCION check (
       INED_PARTICIPACION = 'Matriz'
       or (INED_ID_DOMICILIO is not null and INED_ID_ZONA_REGIONAL is not null)
   ),

   /* Solo la afiliada tiene número, de 7 dígitos.
      "is not null" evita que un CHECK en NULL se acepte. */
   constraint CKC_INED_NUMERO check (
       (INED_PARTICIPACION = 'Afiliada'
           and INED_NUM_AFILIACION is not null
           and INED_NUM_AFILIACION ~ '^[0-9]{7}$')
       or
       (INED_PARTICIPACION <> 'Afiliada' and INED_NUM_AFILIACION is null)
   ),

   /* Una institución no puede ser padre de sí misma */
   constraint CKC_INED_NO_PROPIA check (
       INED_ID_INSTITUCION_PADRE is null
       or INED_ID_INSTITUCION_PADRE <> INED_ID_INSTITUCION_EDUCATIVA
   ),

   constraint FK_INED_TIPO foreign key (INED_ID_TIPO_INSTITUCION)
      references TIPO_INSTITUCION_EDUCATIVA (TIIE_ID_TIPO_INSTITUCION),
   constraint FK_INED_PADRE foreign key (INED_ID_INSTITUCION_PADRE)
      references INSTITUCION_EDUCATIVA (INED_ID_INSTITUCION_EDUCATIVA),
   constraint FK_INED_DOMICILIO foreign key (INED_ID_DOMICILIO)
      references DOMICILIO (DOMI_ID_DOMICILIO),
   constraint FK_INED_ZONA foreign key (INED_ID_ZONA_REGIONAL)
      references ZONA_REGIONAL (ZORE_ID_ZONA_REGIONAL)
);
create index INED_PADRE_FK on INSTITUCION_EDUCATIVA (INED_ID_INSTITUCION_PADRE);
create index INED_TIPO_FK on INSTITUCION_EDUCATIVA (INED_ID_TIPO_INSTITUCION);
create index INED_DOMICILIO_FK on INSTITUCION_EDUCATIVA (INED_ID_DOMICILIO);
create index INED_ZONA_FK on INSTITUCION_EDUCATIVA (INED_ID_ZONA_REGIONAL);

/* Reglas que necesitan leer otra tabla (tipo y padre):
   - Solo una universidad puede ser matriz
   - Facultades y campus deben tener una universidad como padre
   - Solo facultades y campus pueden tener padre */
create or replace function validar_institucion()
returns trigger as $$
declare
    v_tipo       varchar(15);
    v_tipo_padre varchar(15);
begin
    select TIIE_NOMBRE into v_tipo
    from TIPO_INSTITUCION_EDUCATIVA
    where TIIE_ID_TIPO_INSTITUCION = new.INED_ID_TIPO_INSTITUCION;

    if new.INED_PARTICIPACION = 'Matriz' and v_tipo <> 'Universidad' then
        raise exception 'Solo una universidad puede ser matriz.';
    end if;

    if v_tipo in ('Facultad', 'Campus') then
        if new.INED_ID_INSTITUCION_PADRE is null then
            raise exception 'Facultades y campus deben pertenecer a una universidad.';
        end if;

        select t.TIIE_NOMBRE into v_tipo_padre
        from INSTITUCION_EDUCATIVA i
        join TIPO_INSTITUCION_EDUCATIVA t
          on t.TIIE_ID_TIPO_INSTITUCION = i.INED_ID_TIPO_INSTITUCION
        where i.INED_ID_INSTITUCION_EDUCATIVA = new.INED_ID_INSTITUCION_PADRE;

        if v_tipo_padre <> 'Universidad' then
            raise exception 'El padre de una facultad o campus debe ser una universidad.';
        end if;
    elsif new.INED_ID_INSTITUCION_PADRE is not null then
        raise exception 'Solo facultades y campus pueden tener una universidad padre.';
    end if;

    return new;
end;
$$ language plpgsql;

create trigger trg_validar_institucion
before insert or update on INSTITUCION_EDUCATIVA
for each row execute function validar_institucion();


/* Historial de razones sociales. Un solo nombre vigente por
   institución: EXCLUDE impide que dos periodos se traslapen. */
create table INSTITUCION_NOMBRE (
   INSTN_ID_INSTITUCION_NOMBRE SERIAL not null,
   INSTN_ID_INSTITUCION  INT4 not null,
   INSTN_NOMBRE          VARCHAR(200) not null,
   INSTN_FECHA_INICIO    DATE not null,
   INSTN_FECHA_FIN       DATE null,
   INSTN_FECHA_REGISTRO  TIMESTAMP not null default now(),

   constraint PK_INSTITUCION_NOMBRE primary key (INSTN_ID_INSTITUCION_NOMBRE),
   constraint CKC_INSTN_FECHAS check (
       INSTN_FECHA_FIN is null or INSTN_FECHA_FIN >= INSTN_FECHA_INICIO
   ),
   constraint EXC_INSTN_PERIODO exclude using gist (
       INSTN_ID_INSTITUCION with =,
       daterange(INSTN_FECHA_INICIO, coalesce(INSTN_FECHA_FIN, 'infinity'::date), '[]') with &&
   ),
   constraint FK_INSTN_INSTITUCION foreign key (INSTN_ID_INSTITUCION)
      references INSTITUCION_EDUCATIVA (INED_ID_INSTITUCION_EDUCATIVA)
);

/* Historial de participación (bitácora). Solo informativo y para el
   filtro por año en instituciones. NO genera directorios.
   Un registro por cada periodo de participación: Matriz, Afiliada
   u Observadora. Si INPA_FECHA_FIN es null, es el periodo actual. */
create table INSTITUCION_PARTICIPACION (
   INPA_ID_PARTICIPACION SERIAL not null,
   INPA_ID_INSTITUCION   INT4 not null,
   INPA_TIPO             VARCHAR(15) not null,
   INPA_FECHA_INICIO     DATE not null,
   INPA_FECHA_FIN        DATE null,
   constraint PK_INSTITUCION_PARTICIPACION primary key (INPA_ID_PARTICIPACION),
   constraint CKC_INPA_TIPO check (INPA_TIPO in ('Matriz','Afiliada','Observadora')),
   constraint CKC_INPA_FECHAS check (
       INPA_FECHA_FIN is null or INPA_FECHA_FIN >= INPA_FECHA_INICIO
   ),
   constraint INPA_TIPO_INICIO_UQ unique (INPA_ID_INSTITUCION, INPA_TIPO, INPA_FECHA_INICIO),
   constraint FK_INPA_INSTITUCION foreign key (INPA_ID_INSTITUCION)
      references INSTITUCION_EDUCATIVA (INED_ID_INSTITUCION_EDUCATIVA)
);
create index INPA_INSTITUCION_FK on INSTITUCION_PARTICIPACION (INPA_ID_INSTITUCION);
/* Índice para el filtro por año: tipo + fecha de inicio */
create index INPA_TIPO_ANIO_IDX on INSTITUCION_PARTICIPACION (INPA_TIPO, INPA_FECHA_INICIO);


/*==============================================================*/
/* 4. PERSONAS                                                  */
/*==============================================================*/

/* Persona: información personal. Pertenencia opcional (institución u
   organización). La designación sí la exige (ver validar_designacion).
   PERS_OCULTA_ADMIN = true: el administrador no la ve; el superadmin
   sí. No borra nada (ver borrar_persona). */
create table PERSONA (
   PERS_ID_PERSONA       SERIAL not null,
   PERS_NOMBRE           VARCHAR(60) not null,
   PERS_PRIMER_APELLIDO VARCHAR(45) not null,
   PERS_SEGUNDO_APELLIDO VARCHAR(45) null,
   PERS_CURP             VARCHAR(18) null,
   PERS_GENERO           VARCHAR(1) not null,
   PERS_ID_INSTITUCION_EDUCATIVA INT4 null,
   PERS_ID_ORGANIZACION  INT4 null,
   /* Oculta = no aparece en pantallas de administración ni en el sitio
      público ni en directorios generados (lo filtra Laravel). Su puesto
      se muestra como "Vacante" en esos lugares. El superadmin la ve. */
   PERS_OCULTA_ADMIN     BOOL not null default false,
   constraint PK_PERSONA primary key (PERS_ID_PERSONA),
   constraint CKC_PERS_GENERO check (PERS_GENERO in ('F','M')),
   /* CURP opcional. Si se captura: 18 caracteres, en mayúsculas, y única. */
   constraint CKC_PERS_CURP check (PERS_CURP is null or
       PERS_CURP ~ '^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$'),
   constraint PERS_CURP_UQ unique (PERS_CURP),
   constraint FK_PERS_INSTITUCION foreign key (PERS_ID_INSTITUCION_EDUCATIVA)
      references INSTITUCION_EDUCATIVA (INED_ID_INSTITUCION_EDUCATIVA),
   constraint FK_PERS_ORGANIZACION foreign key (PERS_ID_ORGANIZACION)
      references ORGANIZACION (ORGA_ID_ORGANIZACION)
);
create index PERS_INSTITUCION_FK on PERSONA (PERS_ID_INSTITUCION_EDUCATIVA);
create index PERS_ORGANIZACION_FK on PERSONA (PERS_ID_ORGANIZACION);

/* Grados académicos de una persona (puede tener varios) */
create table PERSONA_NIVEL_ACADEMICO (
   PENA_ID_PERSONA       INT4 not null,
   PENA_ID_NIVEL_ACADEMICO INT4 not null,
   constraint PK_PERSONA_NIVEL_ACADEMICO primary key (PENA_ID_PERSONA, PENA_ID_NIVEL_ACADEMICO),
   constraint FK_PENA_PERSONA foreign key (PENA_ID_PERSONA)
      references PERSONA (PERS_ID_PERSONA) on delete cascade,
   constraint FK_PENA_NIVEL foreign key (PENA_ID_NIVEL_ACADEMICO)
      references NIVEL_ACADEMICO (NIAC_ID_NIVEL_ACADEMICO)
);
create index PENA_NIVEL_ACADEMICO_FK on PERSONA_NIVEL_ACADEMICO (PENA_ID_NIVEL_ACADEMICO);

/* Contactos. CONT_VISIBLE = true: se muestra en el sitio web.
   La lada y la extensión según TIPO_CONTACTO las valida Laravel. */
create table CONTACTO (
   CONT_ID_CONTACTO      SERIAL not null,
   CONT_ID_PERSONA       INT4 not null,
   CONT_ID_TIPO_CONTACTO INT4 not null,
   CONT_VALOR            VARCHAR(120) not null,
   CONT_LADA             VARCHAR(5) null,
   CONT_EXTENSION        VARCHAR(5) null,
   CONT_VISIBLE          BOOL not null default true,
   constraint PK_CONTACTO primary key (CONT_ID_CONTACTO),
   constraint FK_CONT_PERSONA foreign key (CONT_ID_PERSONA)
      references PERSONA (PERS_ID_PERSONA) on delete cascade,
   constraint FK_CONT_TIPO foreign key (CONT_ID_TIPO_CONTACTO)
      references TIPO_CONTACTO (TICO_ID_TIPO_CONTACTO)
);
create index CONT_PERSONA_FK on CONTACTO (CONT_ID_PERSONA);
create index CONT_TIPO_CONTACTO_FK on CONTACTO (CONT_ID_TIPO_CONTACTO);


/*==============================================================*/
/* 5. PUESTOS Y DESIGNACIONES                                   */
/*==============================================================*/

/* Puesto = cargo + ámbito concreto.
   - Institucional: solo institución
   - Regional:      zona (obligatoria); coordinación opcional
   - Nacional:      sin zona ni institución; coordinación opcional
   La titularidad solo aplica a puestos institucionales.

   NULLS NOT DISTINCT: sin esto, dos puestos "Presidente nacional"
   (con zona, coordinación e institución vacías) se aceptarían. */
create table PUESTO (
   PUES_ID_PUESTO        SERIAL not null,
   PUES_ID_CARGO         INT4 not null,
   PUES_ID_ZONA_REGIONAL INT4 null,
   PUES_ID_COORDINACION_NACIONAL INT4 null,
   PUES_ID_INSTITUCION_EDUCATIVA INT4 null,
   PUES_TITULARIDAD      BOOL not null default false,
   PUES_ACTIVO           BOOL not null default true,
   constraint PK_PUESTO primary key (PUES_ID_PUESTO),
   constraint PUES_PUESTO_UQ unique nulls not distinct (
       PUES_ID_CARGO,
       PUES_ID_ZONA_REGIONAL,
       PUES_ID_COORDINACION_NACIONAL,
       PUES_ID_INSTITUCION_EDUCATIVA
   ),
   constraint FK_PUES_CARGO foreign key (PUES_ID_CARGO)
      references CARGO (CARG_ID_CARGO),
   constraint FK_PUES_ZONA foreign key (PUES_ID_ZONA_REGIONAL)
      references ZONA_REGIONAL (ZORE_ID_ZONA_REGIONAL),
   constraint FK_PUES_COORDINACION foreign key (PUES_ID_COORDINACION_NACIONAL)
      references COORDINACION_NACIONAL (CONA_ID_COORDINACION_NACIONAL),
   constraint FK_PUES_INSTITUCION foreign key (PUES_ID_INSTITUCION_EDUCATIVA)
      references INSTITUCION_EDUCATIVA (INED_ID_INSTITUCION_EDUCATIVA)
);
create index PUES_ZONA_FK on PUESTO (PUES_ID_ZONA_REGIONAL);
create index PUES_COORDINACION_FK on PUESTO (PUES_ID_COORDINACION_NACIONAL);
create index PUES_INSTITUCION_FK on PUESTO (PUES_ID_INSTITUCION_EDUCATIVA);

/* Las reglas de ámbito dependen del nivel del cargo (otra tabla). */
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

create trigger trg_validar_puesto
before insert or update on PUESTO
for each row execute function validar_puesto();


/* Asignación de una persona a un puesto.
   DESI_FECHA_INICIO y DESI_FECHA_FIN son el PRIMER y el ÚLTIMO día
   con cargo (rango cerrado). El formulario captura el primer día
   sin cargo ("deja el cargo el"); Laravel llama a
   desactivar_designacion, que guarda ese día menos uno.
   EXCLUDE: un mismo puesto no puede tener dos periodos traslapados,
   sin importar si son pasados, actuales o futuros. */
create table DESIGNACION (
   DESI_ID_DESIGNACION   SERIAL not null,
   DESI_ID_PUESTO        INT4 not null,
   DESI_ID_PERSONA       INT4 not null,
   DESI_FECHA_INICIO     DATE not null,
   DESI_FECHA_FIN        DATE null,
   DESI_FECHA_REGISTRO   TIMESTAMP not null default now(),
   constraint PK_DESIGNACION primary key (DESI_ID_DESIGNACION),
   constraint CKC_DESI_FECHAS check (
       DESI_FECHA_FIN is null or DESI_FECHA_FIN >= DESI_FECHA_INICIO
   ),
   constraint EXC_DESI_PUESTO_PERIODO exclude using gist (
       DESI_ID_PUESTO with =,
       daterange(DESI_FECHA_INICIO, coalesce(DESI_FECHA_FIN, 'infinity'::date), '[]') with &&
   ),
   constraint FK_DESI_PUESTO foreign key (DESI_ID_PUESTO)
      references PUESTO (PUES_ID_PUESTO),
   constraint FK_DESI_PERSONA foreign key (DESI_ID_PERSONA)
      references PERSONA (PERS_ID_PERSONA)
);
create index DESI_PERSONA_FK on DESIGNACION (DESI_ID_PERSONA);

/* Coherencia: si el puesto es institucional, la persona debe
   pertenecer a esa misma institución. Así el titular de una
   facultad no puede ser alguien de otra institución u organización. */
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

    return new;
end;
$$ language plpgsql;

create trigger trg_validar_designacion
before insert or update on DESIGNACION
for each row execute function validar_designacion();


/*==============================================================*/
/* 6. DIRECTORIOS (membresía y orden manual)                    */
/*==============================================================*/

/* Qué puestos aparecen en cada directorio, y en qué orden.
   OJO: el directorio de INSTITUCIONES no usa esta tabla. Es una
   consulta sobre INSTITUCION_EDUCATIVA: su titular es la persona
   con un puesto institucional de esa facultad y titularidad vigente.
   - El orden es MANUAL: lo define el usuario en la pantalla de orden.
   - Al agregar un puesto, Laravel le asigna el orden máximo + 1.
   - Un mismo puesto puede estar en varios directorios, con órdenes
     distintos en cada uno.
   - Los puestos institucionales no van en directorios.
   - El orden es único por tipo de directorio y diferible, para que
     reordenar_puestos_directorio pueda reescribir la lista completa.
   Los filtros (zona, coordinación, año) se aplican al consultar,
   sin cambiar el orden. */
create table PUESTO_DIRECTORIO (
   PUDI_ID_PUESTO        INT4 not null,
   PUDI_ID_TIPO_DIRECTORIO INT4 not null,
   PUDI_ORDEN            INT4 not null,
   constraint PK_PUESTO_DIRECTORIO primary key (PUDI_ID_PUESTO, PUDI_ID_TIPO_DIRECTORIO),
   constraint PUDI_ORDEN_UQ unique (PUDI_ID_TIPO_DIRECTORIO, PUDI_ORDEN)
       deferrable initially deferred,
   constraint CKC_PUDI_ORDEN check (PUDI_ORDEN >= 1),
   constraint FK_PUDI_PUESTO foreign key (PUDI_ID_PUESTO)
      references PUESTO (PUES_ID_PUESTO) on delete cascade,
   constraint FK_PUDI_TIPO foreign key (PUDI_ID_TIPO_DIRECTORIO)
      references TIPO_DIRECTORIO (TIDI_ID_TIPO_DIRECTORIO)
);
create index PUDI_TIPO_DIRECTORIO_FK on PUESTO_DIRECTORIO (PUDI_ID_TIPO_DIRECTORIO);

create or replace function validar_puesto_directorio()
returns trigger as $$
declare
    v_nivel varchar(15);
begin
    select c.CARG_NIVEL into v_nivel
    from PUESTO p
    join CARGO c on c.CARG_ID_CARGO = p.PUES_ID_CARGO
    where p.PUES_ID_PUESTO = new.PUDI_ID_PUESTO;

    if v_nivel = 'Institucional' then
        raise exception 'Los puestos institucionales no van en directorios.';
    end if;

    return new;
end;
$$ language plpgsql;

create trigger trg_validar_puesto_directorio
before insert or update on PUESTO_DIRECTORIO
for each row execute function validar_puesto_directorio();


/*==============================================================*/
/* 7. SEGURIDAD: privilegios, roles, usuarios, bitácora e       */
/*    inicios de sesión                                         */
/*==============================================================*/

/* Privilegios del sistema (crear institución, editar persona,
   ver directorios...). PRIV_NOMBRE es la clave que usa el código. */
create table PRIVILEGIO (
   PRIV_ID_PRIVILEGIO    SERIAL not null,
   PRIV_NOMBRE           VARCHAR(100) not null,
   PRIV_DESCRIPCION      VARCHAR(100) null,
   constraint PK_PRIVILEGIO primary key (PRIV_ID_PRIVILEGIO),
   constraint PRIV_NOMBRE_UQ unique (PRIV_NOMBRE)
);

/* Roles (Superadministrador, Administrador). */
create table ROL (
   ROL_ID_ROL            SERIAL not null,
   ROL_NOMBRE            VARCHAR(80) not null,
   ROL_DESCRIPCION       VARCHAR(100) null,
   ROL_ACTIVO            BOOL not null default true,
   constraint PK_ROL primary key (ROL_ID_ROL),
   constraint ROL_NOMBRE_UQ unique (ROL_NOMBRE)
);

/* Qué privilegios tiene cada rol (N:M). */
create table ROL_PRIVILEGIO (
   ROPR_ID_ROL           INT4 not null,
   ROPR_ID_PRIVILEGIO    INT4 not null,
   ROPR_ACTIVO           BOOL not null default true,
   constraint PK_ROL_PRIVILEGIO primary key (ROPR_ID_ROL, ROPR_ID_PRIVILEGIO),
   constraint FK_ROPR_ROL foreign key (ROPR_ID_ROL)
      references ROL (ROL_ID_ROL),
   constraint FK_ROPR_PRIVILEGIO foreign key (ROPR_ID_PRIVILEGIO)
      references PRIVILEGIO (PRIV_ID_PRIVILEGIO)
);
create index ROPR_PRIVILEGIO_FK on ROL_PRIVILEGIO (ROPR_ID_PRIVILEGIO);

/* Cuentas de acceso al sistema. Entra con correo.
   La cuenta nunca se borra: la bitácora y los inicios de sesión
   dependen de ella. Se desactiva, o se anonimiza en ARCO. */
create table USUARIO (
   USUA_ID_USUARIO       SERIAL not null,
   USUA_ID_PERSONA       INT4 null,
   USUA_ID_ROL           INT4 not null,
   USUA_CORREO           VARCHAR(254) not null,
   USUA_CONTRASENIA      VARCHAR(255) not null,
   USUA_FECHA_REGISTRO   TIMESTAMP not null default now(),
   USUA_ACTIVO           BOOL not null default true,
   constraint PK_USUARIO primary key (USUA_ID_USUARIO),
   constraint USUA_PERSONA_UQ unique (USUA_ID_PERSONA),
   constraint CKC_USUA_CORREO check (USUA_CORREO ~ '^[^@\s]+@[^@\s]+\.[^@\s]+$'),
   /* Cuenta activa siempre tiene persona. Una cuenta anonimizada
      (ARCO) no tiene persona y queda desactivada. */
   constraint CKC_USUA_ACTIVA_PERSONA check (USUA_ACTIVO = false or USUA_ID_PERSONA is not null),
   constraint FK_USUA_PERSONA foreign key (USUA_ID_PERSONA)
      references PERSONA (PERS_ID_PERSONA),
   constraint FK_USUA_ROL foreign key (USUA_ID_ROL)
      references ROL (ROL_ID_ROL)
);
create index USUA_ROL_FK on USUARIO (USUA_ID_ROL);

/* Un correo no se repite, sin importar mayúsculas. */
create unique index USUA_CORREO_UQ on USUARIO (lower(USUA_CORREO));

/* Recuperación de contraseña. TEMPORAL: cada fila dura minutos.
   Se guarda solo el HASH SHA-256 del código (nunca el código),
   con expiración corta, un solo uso y límite de intentos.
   Se borra al usarse, al vencer (limpiar_recuperaciones) y en ARCO. */
create table RECUPERACION_CONTRASENIA (
   RECO_ID_RECUPERACION  SERIAL not null,
   RECO_ID_USUARIO       INT4 not null,
   RECO_TOKEN_HASH       VARCHAR(64) not null,
   RECO_FECHA_CREACION   TIMESTAMP not null default now(),
   RECO_FECHA_EXPIRA     TIMESTAMP not null,
   RECO_FECHA_USO        TIMESTAMP null,
   RECO_INTENTOS         INT4 not null default 0,
   constraint PK_RECUPERACION_CONTRASENIA primary key (RECO_ID_RECUPERACION),
   constraint RECO_TOKEN_HASH_UQ unique (RECO_TOKEN_HASH),
   constraint CKC_RECO_FECHAS check (RECO_FECHA_EXPIRA > RECO_FECHA_CREACION),
   constraint CKC_RECO_INTENTOS check (RECO_INTENTOS between 0 and 5),
   constraint FK_RECO_USUARIO foreign key (RECO_ID_USUARIO)
      references USUARIO (USUA_ID_USUARIO)
);
create index RECO_USUARIO_FK on RECUPERACION_CONTRASENIA (RECO_ID_USUARIO);


/* BITÁCORA: quién hizo qué, sobre qué registro, cuándo y por qué.
   - BITA_ID_USUARIO: quién actuó (cuenta). Su nombre se muestra desde
     la ficha del usuario.
   - BITA_TABLA y BITA_REFERENCIA: sobre qué registro (número). El
     nombre de la persona NO se guarda aquí: se muestra desde su ficha.
     Si la ficha se borra (ARCO), el front dice "persona eliminada #n".
   - BITA_MOTIVO: de una lista cerrada. */
create table BITACORA (
   BITA_ID_BITACORA      SERIAL not null,
   BITA_FECHA_HORA       TIMESTAMP not null default now(),
   BITA_ID_USUARIO       INT4 not null,
   BITA_ACCION           VARCHAR(30) not null,
   BITA_TABLA            VARCHAR(40) not null,
   BITA_REFERENCIA       INT4 null,
   BITA_MOTIVO           VARCHAR(60) null,
   constraint PK_BITACORA primary key (BITA_ID_BITACORA),
   constraint CKC_BITA_ACCION check (BITA_ACCION in (
      'Registro','Modificacion','Reordenamiento','Activacion','Desactivacion',
      'Ocultacion','Restauracion','Eliminacion','Anonimizacion')),
   constraint CKC_BITA_MOTIVO check (BITA_MOTIVO is null or BITA_MOTIVO in (
      'Error de captura','Decisión de la organización','Cancelación ARCO','Duplicado')),
   constraint FK_BITA_USUARIO foreign key (BITA_ID_USUARIO)
      references USUARIO (USUA_ID_USUARIO)
);
create index BITA_USUARIO_FK on BITACORA (BITA_ID_USUARIO);
create index BITA_FECHA_IDX on BITACORA (BITA_FECHA_HORA);
create index BITA_TABLA_REF_IDX on BITACORA (BITA_TABLA, BITA_REFERENCIA);

/* DETALLE de la bitácora: qué campo cambió, con valores anterior y nuevo.
   Regla: los campos personales (nombre, CURP, correo, contactos,
   grados...) NO guardan valores, solo el nombre del campo. Los
   campos organizacionales (orden, fechas de cargo, puesto...) sí.
   BIDE_REFERENCIA: a qué registro se refiere esta fila, si es
   distinto del principal (ej. cada puesto que cambió de lugar). */
create table BITACORA_DETALLE (
   BIDE_ID_DETALLE       SERIAL not null,
   BIDE_ID_BITACORA      INT4 not null,
   BIDE_REFERENCIA       INT4 null,
   BIDE_CAMPO            VARCHAR(60) not null,
   BIDE_ANTERIOR         VARCHAR(255) null,
   BIDE_NUEVO            VARCHAR(255) null,
   constraint PK_BITACORA_DETALLE primary key (BIDE_ID_DETALLE),
   constraint FK_BIDE_BITACORA foreign key (BIDE_ID_BITACORA)
      references BITACORA (BITA_ID_BITACORA)
);
create index BIDE_BITACORA_FK on BITACORA_DETALLE (BIDE_ID_BITACORA);

/* INICIOS DE SESIÓN: éxito, fallo, cuenta inactiva y cierre.
   Sin correos: si el correo no existe, solo queda la IP y la hora.
   Los inicios de sesión son para seguridad, no para la bitácora. */
create table LOG_ACCESO (
   LOGA_ID_LOG           SERIAL not null,
   LOGA_FECHA_HORA       TIMESTAMP not null default now(),
   LOGA_ID_USUARIO       INT4 null,
   LOGA_RESULTADO        VARCHAR(20) not null,
   LOGA_IP               VARCHAR(45) not null,
   constraint PK_LOG_ACCESO primary key (LOGA_ID_LOG),
   constraint CKC_LOGA_RESULTADO check (LOGA_RESULTADO in
      ('Exitoso','Fallido','Cuenta inactiva','Cierre de sesión')),
   /* Solo un intento fallido puede no tener cuenta */
   constraint CKC_LOGA_USUARIO check (LOGA_RESULTADO = 'Fallido' or LOGA_ID_USUARIO is not null),
   constraint FK_LOGA_USUARIO foreign key (LOGA_ID_USUARIO)
      references USUARIO (USUA_ID_USUARIO)
);
create index LOGA_USUARIO_FK on LOG_ACCESO (LOGA_ID_USUARIO);
create index LOGA_FECHA_IDX on LOG_ACCESO (LOGA_FECHA_HORA);

/* Solo inserción: bitácora, detalle e inicios de sesión.
   (TRUNCATE no lo cubre; la cuenta de la aplicación no debe tenerlo.) */
create or replace function bitacora_solo_insercion()
returns trigger as $$
begin
    raise exception 'La bitácora y los inicios de sesión son de solo inserción.';
end;
$$ language plpgsql;

create trigger trg_bitacora_solo_insercion
before update or delete on BITACORA
for each row execute function bitacora_solo_insercion();

create trigger trg_bitacora_detalle_solo_insercion
before update or delete on BITACORA_DETALLE
for each row execute function bitacora_solo_insercion();

create trigger trg_log_acceso_solo_insercion
before update or delete on LOG_ACCESO
for each row execute function bitacora_solo_insercion();

/* Una cuenta nunca se borra: la bitácora y los inicios de sesión
   dependen de ella. Se desactiva con cambiar_activo_usuario, o se
   anonimiza en cancelar_arco. */
create or replace function bloquear_borrado_usuario()
returns trigger as $$
begin
    raise exception 'Las cuentas no se borran. Desactívala o usa cancelar_arco.';
end;
$$ language plpgsql;

create trigger trg_bloquear_borrado_usuario
before delete on USUARIO
for each row execute function bloquear_borrado_usuario();


/* Helpers de bitácora. Laravel los llama dentro de la misma
   transacción que el cambio, para que nunca quede un cambio sin
   registro (ni al revés). */
create or replace function bitacora_registrar(p_actor INT4, p_accion VARCHAR, p_tabla VARCHAR,
                                              p_referencia INT4, p_motivo VARCHAR)
returns INT4 as $$
declare
    v_id INT4;
begin
    if p_accion in ('Eliminacion','Ocultacion','Anonimizacion')
       and nullif(btrim(p_motivo), '') is null then
        raise exception 'Esta acción requiere motivo.';
    end if;

    insert into BITACORA (BITA_ID_USUARIO, BITA_ACCION, BITA_TABLA, BITA_REFERENCIA, BITA_MOTIVO)
    values (p_actor, p_accion, p_tabla, p_referencia, nullif(btrim(p_motivo), ''))
    returning BITA_ID_BITACORA into v_id;

    return v_id;
end;
$$ language plpgsql;

create or replace function bitacora_detalle_registrar(p_bitacora INT4, p_referencia INT4, p_campo VARCHAR,
                                                      p_anterior VARCHAR, p_nuevo VARCHAR)
returns void as $$
begin
    if p_campo in ('Nombre','Primer apellido','Segundo apellido','CURP','Género',
                   'Correo','Contacto','Grado académico','Pertenencia')
       and (p_anterior is not null or p_nuevo is not null) then
        raise exception 'Los campos personales no guardan valores en la bitácora (campo: %).', p_campo;
    end if;

    insert into BITACORA_DETALLE (BIDE_ID_BITACORA, BIDE_REFERENCIA, BIDE_CAMPO, BIDE_ANTERIOR, BIDE_NUEVO)
    values (p_bitacora, p_referencia, p_campo, p_anterior, p_nuevo);
end;
$$ language plpgsql;

/* Laravel llama esta función en cada intento de inicio de sesión,
   cierre de sesión o cuenta inactiva. p_usuario = null solo si el
   correo no existe. */
create or replace function registrar_acceso(p_usuario INT4, p_resultado VARCHAR, p_ip VARCHAR)
returns INT4 as $$
declare
    v_id INT4;
begin
    insert into LOG_ACCESO (LOGA_ID_USUARIO, LOGA_RESULTADO, LOGA_IP)
    values (p_usuario, p_resultado, p_ip)
    returning LOGA_ID_LOG into v_id;
    return v_id;
end;
$$ language plpgsql;

/* Limpieza de recuperaciones: vencidas o ya usadas. Laravel la programa. */
create or replace function limpiar_recuperaciones()
returns INT4 as $$
declare
    v_borradas INT4;
begin
    delete from RECUPERACION_CONTRASENIA
     where RECO_FECHA_EXPIRA < now() or RECO_FECHA_USO is not null;
    get diagnostics v_borradas = row_count;
    return v_borradas;
end;
$$ language plpgsql;


/*==============================================================*/
/* 7b. ACCIONES SOBRE DATOS                                     */
/*   Todas dejan registro en bitácora, en la misma transacción. */
/*==============================================================*/

/* Bloquea el borrado directo de designaciones. Solo las funciones de
   borrado activan el permiso, y solo dentro de su transacción. */
create or replace function bloquear_borrado_designacion()
returns trigger as $$
begin
    if coalesce(current_setting('sideanfeca.borrado_definitivo', true), '') <> 'on' then
        raise exception 'Las designaciones no se borran directamente. Usa eliminar_designacion_erronea o eliminar_persona_definitiva.';
    end if;
    return old;
end;
$$ language plpgsql;

create trigger trg_bloquear_borrado_designacion
before delete on DESIGNACION
for each row execute function bloquear_borrado_designacion();

/* Helper: ¿la cuenta activa tiene este privilegio? */
create or replace function cuenta_tiene_privilegio(p_actor INT4, p_privilegio text)
returns boolean as $$
    select exists (
        select 1
        from USUARIO u
        join ROL_PRIVILEGIO rp on rp.ROPR_ID_ROL = u.USUA_ID_ROL and rp.ROPR_ACTIVO
        join PRIVILEGIO p on p.PRIV_ID_PRIVILEGIO = rp.ROPR_ID_PRIVILEGIO
        where u.USUA_ID_USUARIO = p_actor
          and u.USUA_ACTIVO
          and p.PRIV_NOMBRE = p_privilegio
    );
$$ language sql stable;

/* REGLA DIFERIDA: toda persona tiene al menos una designación o una
   cuenta. Se revisa al confirmar la transacción, así que Laravel puede
   crear la persona y su designación en el mismo paso. */
create or replace function validar_persona_con_razon()
returns trigger as $$
declare
    v_persona INT4;
begin
    if lower(tg_table_name) = 'persona' then
        v_persona := new.PERS_ID_PERSONA;
    elsif lower(tg_table_name) = 'designacion' then
        v_persona := old.DESI_ID_PERSONA;
    else
        v_persona := coalesce(new.USUA_ID_PERSONA, old.USUA_ID_PERSONA);
    end if;

    -- Si la persona ya no existe (se borró completa), no hay nada que revisar
    if v_persona is null
       or not exists (select 1 from PERSONA where PERS_ID_PERSONA = v_persona) then
        return null;
    end if;

    if not exists (select 1 from DESIGNACION where DESI_ID_PERSONA = v_persona)
       and not exists (select 1 from USUARIO where USUA_ID_PERSONA = v_persona) then
        raise exception 'La persona quedaría sin designación y sin cuenta. Si debe desaparecer, bórrala completa.';
    end if;

    return null;
end;
$$ language plpgsql;

create constraint trigger trg_persona_con_razon
after insert or update on PERSONA
deferrable initially deferred
for each row execute function validar_persona_con_razon();

create constraint trigger trg_designacion_con_razon
after delete on DESIGNACION
deferrable initially deferred
for each row execute function validar_persona_con_razon();

create constraint trigger trg_usuario_con_razon
after update of USUA_ID_PERSONA on USUARIO
deferrable initially deferred
for each row execute function validar_persona_con_razon();


/* Activar o desactivar una cuenta. Solo gestionar_usuarios.
   No se puede activar una cuenta anonimizada (no tiene persona). */
create or replace function cambiar_activo_usuario(p_actor INT4, p_usuario INT4, p_activo BOOL)
returns text as $$
declare
    v_persona INT4;
begin
    if not cuenta_tiene_privilegio(p_actor, 'gestionar_usuarios') then
        raise exception 'La cuenta no tiene permiso para gestionar usuarios.';
    end if;

    select USUA_ID_PERSONA into v_persona from USUARIO where USUA_ID_USUARIO = p_usuario;
    if not found then
        raise exception 'La cuenta no existe.';
    end if;

    if not p_activo and p_actor = p_usuario then
        raise exception 'No puedes desactivar tu propia cuenta.';
    end if;

    if p_activo and v_persona is null then
        raise exception 'Esta cuenta no tiene persona vinculada y no puede activarse.';
    end if;

    update USUARIO set USUA_ACTIVO = p_activo where USUA_ID_USUARIO = p_usuario;

    if p_activo then
        perform bitacora_registrar(p_actor, 'Activacion', 'USUARIO', p_usuario, null);
        return 'Cuenta activada. Su registro en directorios no cambia.';
    end if;

    perform bitacora_registrar(p_actor, 'Desactivacion', 'USUARIO', p_usuario, null);
    return 'Cuenta desactivada.';
end;
$$ language plpgsql;


/* DESACTIVAR (dejar el cargo) una designación.
   p_fecha_deja_cargo = primer día SIN cargo (lo que captura el usuario,
   o hoy si pulsa "desactivar hoy"). Se guarda el día anterior como
   último día con cargo. Así no hay traslape con el sustituto que
   empieza ese mismo día. */
create or replace function desactivar_designacion(p_actor INT4, p_designacion INT4, p_fecha_deja_cargo DATE)
returns text as $$
declare
    v_inicio  DATE;
    v_fin     DATE;
    v_nuevo_fin DATE;
    v_bita    INT4;
begin
    if not cuenta_tiene_privilegio(p_actor, 'gestionar_designaciones') then
        raise exception 'La cuenta no tiene permiso para gestionar designaciones.';
    end if;

    select DESI_FECHA_INICIO, DESI_FECHA_FIN into v_inicio, v_fin
    from DESIGNACION where DESI_ID_DESIGNACION = p_designacion;

    if not found then
        raise exception 'La designación no existe.';
    end if;

    v_nuevo_fin := p_fecha_deja_cargo - 1;

    if v_nuevo_fin < v_inicio then
        raise exception 'La designación quedaría sin días de cargo. Si fue un error de captura, bórrala.';
    end if;

    update DESIGNACION set DESI_FECHA_FIN = v_nuevo_fin where DESI_ID_DESIGNACION = p_designacion;

    v_bita := bitacora_registrar(p_actor, 'Modificacion', 'DESIGNACION', p_designacion, null);
    perform bitacora_detalle_registrar(v_bita, p_designacion, 'Fecha en que deja el cargo',
                                       case when v_fin is null then null else (v_fin + 1)::text end,
                                       p_fecha_deja_cargo::text);

    return 'Fecha de baja registrada.';
end;
$$ language plpgsql;

/* Designación capturada por error (nunca ocurrió): se borra. No deja
   huecos en la historia. Solo se permite con motivo de error o duplicado. */
create or replace function eliminar_designacion_erronea(p_actor INT4, p_designacion INT4, p_motivo VARCHAR)
returns text as $$
declare
    v_persona INT4;
    v_puesto  INT4;
    v_bita    INT4;
begin
    if not cuenta_tiene_privilegio(p_actor, 'gestionar_designaciones') then
        raise exception 'La cuenta no tiene permiso para gestionar designaciones.';
    end if;

    if p_motivo not in ('Error de captura', 'Duplicado') then
        raise exception 'Motivo no válido para borrar una designación.';
    end if;

    select DESI_ID_PERSONA, DESI_ID_PUESTO into v_persona, v_puesto
    from DESIGNACION where DESI_ID_DESIGNACION = p_designacion;

    if not found then
        raise exception 'La designación no existe.';
    end if;

    perform set_config('sideanfeca.borrado_definitivo', 'on', true);
    delete from DESIGNACION where DESI_ID_DESIGNACION = p_designacion;

    v_bita := bitacora_registrar(p_actor, 'Eliminacion', 'DESIGNACION', p_designacion, p_motivo);
    perform bitacora_detalle_registrar(v_bita, v_persona, 'Persona', null, null);
    perform bitacora_detalle_registrar(v_bita, v_puesto, 'Puesto', null, null);

    return 'Designación eliminada.';
end;
$$ language plpgsql;

/* BORRADO DEFINITIVO de una persona (superadmin o administrador,
   según el caso). Motivos: error de captura, duplicado o decisión de
   la organización. Nunca sobre uno mismo.
   - Sin cuenta: se borra todo (contactos, grados, designaciones y ficha).
   - Con cuenta: requiere gestionar_usuarios. Se borran contactos,
     grados, designaciones y pertenencia; se CONSERVAN nombre,
     segundo apellido y CURP (para recuperarla si regresa); la cuenta
     se desactiva.
   Si el borrado es por ARCO, usar cancelar_arco. */
create or replace function eliminar_persona_definitiva(p_actor INT4, p_persona INT4, p_motivo VARCHAR)
returns text as $$
declare
    v_cuenta        INT4;
    v_designaciones INT4;
    v_bita          INT4;
begin
    if not cuenta_tiene_privilegio(p_actor, 'eliminar_definitivo') then
        raise exception 'La cuenta no tiene permiso para el borrado definitivo.';
    end if;

    if p_motivo not in ('Error de captura', 'Duplicado', 'Decisión de la organización') then
        raise exception 'Motivo no válido para el borrado definitivo.';
    end if;

    if not exists (select 1 from PERSONA where PERS_ID_PERSONA = p_persona) then
        raise exception 'La persona no existe.';
    end if;

    select USUA_ID_USUARIO into v_cuenta from USUARIO where USUA_ID_PERSONA = p_persona;

    if v_cuenta is not null then
        if not cuenta_tiene_privilegio(p_actor, 'gestionar_usuarios') then
            raise exception 'Solo quien gestiona usuarios puede borrar a una persona con cuenta.';
        end if;
        if v_cuenta = p_actor then
            raise exception 'No puedes borrar tu propia persona. Pídelo a otro superadmin.';
        end if;
    end if;

    select count(*) into v_designaciones from DESIGNACION where DESI_ID_PERSONA = p_persona;

    perform set_config('sideanfeca.borrado_definitivo', 'on', true);

    delete from CONTACTO where CONT_ID_PERSONA = p_persona;
    delete from PERSONA_NIVEL_ACADEMICO where PENA_ID_PERSONA = p_persona;
    delete from DESIGNACION where DESI_ID_PERSONA = p_persona;

    if v_cuenta is null then
        delete from PERSONA where PERS_ID_PERSONA = p_persona;

        v_bita := bitacora_registrar(p_actor, 'Eliminacion', 'PERSONA', p_persona, p_motivo);
        perform bitacora_detalle_registrar(v_bita, null, 'Designaciones eliminadas', null, v_designaciones::text);
        return 'Persona eliminada por completo.';
    end if;

    update PERSONA
       set PERS_ID_INSTITUCION_EDUCATIVA = null,
           PERS_ID_ORGANIZACION = null
     where PERS_ID_PERSONA = p_persona;

    update USUARIO set USUA_ACTIVO = false where USUA_ID_USUARIO = v_cuenta;

    perform bitacora_registrar(p_actor, 'Desactivacion', 'USUARIO', v_cuenta, p_motivo);

    v_bita := bitacora_registrar(p_actor, 'Eliminacion', 'PERSONA', p_persona, p_motivo);
    perform bitacora_detalle_registrar(v_bita, null, 'Designaciones eliminadas', null, v_designaciones::text);
    perform bitacora_detalle_registrar(v_bita, null, 'Pertenencia', null, null);
    perform bitacora_detalle_registrar(v_bita, null, 'Contacto', null, null);
    perform bitacora_detalle_registrar(v_bita, null, 'Grado académico', null, null);

    return 'Datos eliminados. Se conservaron el nombre y la CURP; la cuenta quedó desactivada.';
end;
$$ language plpgsql;

/* BORRAR desde la pantalla del administrador.
   - Persona CON cuenta: se OCULTA (no se borra nada). El superadmin
     la sigue viendo con toda su información.
   - Persona SIN cuenta: se borra por completo (eliminar_persona_definitiva). */
create or replace function borrar_persona(p_actor INT4, p_persona INT4, p_motivo VARCHAR)
returns text as $$
declare
    v_bita INT4;
begin
    if exists (select 1 from USUARIO where USUA_ID_PERSONA = p_persona) then
        if not cuenta_tiene_privilegio(p_actor, 'gestionar_personas') then
            raise exception 'La cuenta no tiene permiso para gestionar personas.';
        end if;
        if p_motivo not in ('Error de captura', 'Duplicado', 'Decisión de la organización') then
            raise exception 'Motivo no válido para ocultar a una persona.';
        end if;

        update PERSONA set PERS_OCULTA_ADMIN = true where PERS_ID_PERSONA = p_persona;
        perform bitacora_registrar(p_actor, 'Ocultacion', 'PERSONA', p_persona, p_motivo);
        return 'Persona oculta para administradores. El superadmin la sigue viendo.';
    end if;

    return eliminar_persona_definitiva(p_actor, p_persona, p_motivo);
end;
$$ language plpgsql;

/* Mostrar de nuevo una persona oculta (por ejemplo, al volver a
   capturarla con su CURP). */
create or replace function mostrar_persona(p_actor INT4, p_persona INT4)
returns text as $$
begin
    if not cuenta_tiene_privilegio(p_actor, 'gestionar_personas') then
        raise exception 'La cuenta no tiene permiso para gestionar personas.';
    end if;

    update PERSONA set PERS_OCULTA_ADMIN = false where PERS_ID_PERSONA = p_persona;
    if not found then
        raise exception 'La persona no existe.';
    end if;

    perform bitacora_registrar(p_actor, 'Restauracion', 'PERSONA', p_persona, null);
    return 'Persona visible de nuevo.';
end;
$$ language plpgsql;

/* CANCELACIÓN ARCO. Solo quien gestiona usuarios y puede borrar
   definitivamente. Se borra TODO lo personal, incluido nombre y CURP.
   - Contactos, grados, designaciones y ficha: se borran.
   - Cuenta (si existe): se desactiva, se quita el vínculo con la
     persona, el correo se anonimiza, la contraseña queda inutilizable
     y sus recuperaciones pendientes se borran. La fila de la cuenta se
     conserva porque la bitácora la referencia.
   - Puestos: quedan vacantes si ya no tienen titular.
   - Bitácora: solo el número de persona, sin nombre. */
create or replace function cancelar_arco(p_actor INT4, p_persona INT4)
returns text as $$
declare
    v_cuenta INT4;
    v_bita   INT4;
begin
    if not (cuenta_tiene_privilegio(p_actor, 'gestionar_usuarios')
            and cuenta_tiene_privilegio(p_actor, 'eliminar_definitivo')) then
        raise exception 'La cuenta no tiene permiso para atender una cancelación ARCO.';
    end if;

    if not exists (select 1 from PERSONA where PERS_ID_PERSONA = p_persona) then
        raise exception 'La persona no existe.';
    end if;

    select USUA_ID_USUARIO into v_cuenta from USUARIO where USUA_ID_PERSONA = p_persona;

    if v_cuenta = p_actor then
        raise exception 'No puedes atender una cancelación ARCO sobre tu propia cuenta.';
    end if;

    perform set_config('sideanfeca.borrado_definitivo', 'on', true);

    v_bita := bitacora_registrar(p_actor, 'Eliminacion', 'PERSONA', p_persona, 'Cancelación ARCO');
    perform bitacora_detalle_registrar(v_bita, null, 'Datos personales', null, null);

    if v_cuenta is not null then
        delete from RECUPERACION_CONTRASENIA where RECO_ID_USUARIO = v_cuenta;

        update USUARIO
           set USUA_ACTIVO = false,
               USUA_ID_PERSONA = null,
               USUA_CORREO = 'anonimo-' || v_cuenta || '@invalido.invalid',
               USUA_CONTRASENIA = '!ANONIMIZADA'
         where USUA_ID_USUARIO = v_cuenta;

        perform bitacora_registrar(p_actor, 'Anonimizacion', 'USUARIO', v_cuenta, 'Cancelación ARCO');
    end if;

    delete from CONTACTO where CONT_ID_PERSONA = p_persona;
    delete from PERSONA_NIVEL_ACADEMICO where PENA_ID_PERSONA = p_persona;
    delete from DESIGNACION where DESI_ID_PERSONA = p_persona;
    delete from PERSONA where PERS_ID_PERSONA = p_persona;

    return 'Cancelación ARCO aplicada.';
end;
$$ language plpgsql;


/*==============================================================*/
/* 7c. ORDEN DE DIRECTORIOS (una transacción, con bitácora)     */
/*==============================================================*/

/* Reordenar los puestos de un directorio. p_puestos = lista completa
   de puestos del directorio, en el nuevo orden. p_puesto_movido =
   el que el usuario arrastró (para la referencia de la bitácora). */
create or replace function reordenar_puestos_directorio(p_actor INT4, p_tipo_directorio INT4,
                                                        p_puesto_movido INT4, p_puestos INT4[])
returns text as $$
declare
    v_total  INT4;
    v_i      INT4;
    v_puesto INT4;
    v_old    INT4;
    v_nombre VARCHAR(50);
    v_bita   INT4;
begin
    if not cuenta_tiene_privilegio(p_actor, 'gestionar_directorios') then
        raise exception 'La cuenta no tiene permiso para gestionar directorios.';
    end if;

    select count(*) into v_total from PUESTO_DIRECTORIO where PUDI_ID_TIPO_DIRECTORIO = p_tipo_directorio;
    if v_total = 0 then
        return 'El directorio no tiene puestos.';
    end if;

    if p_puestos is null
       or cardinality(p_puestos) <> v_total
       or (select count(distinct x) from unnest(p_puestos) x) <> v_total
       or (select count(*) from PUESTO_DIRECTORIO
            where PUDI_ID_TIPO_DIRECTORIO = p_tipo_directorio
              and PUDI_ID_PUESTO = any(p_puestos)) <> v_total then
        raise exception 'La lista debe contener todos los puestos del directorio, una sola vez.';
    end if;

    if not (p_puesto_movido = any(p_puestos)) then
        raise exception 'El puesto movido no está en la lista.';
    end if;

    select TIDI_NOMBRE into v_nombre from TIPO_DIRECTORIO where TIDI_ID_TIPO_DIRECTORIO = p_tipo_directorio;

    v_bita := bitacora_registrar(p_actor, 'Reordenamiento', 'PUESTO_DIRECTORIO', p_puesto_movido, null);
    perform bitacora_detalle_registrar(v_bita, null, 'Directorio', null, v_nombre);

    for v_i in 1..v_total loop
        v_puesto := p_puestos[v_i];

        select PUDI_ORDEN into v_old from PUESTO_DIRECTORIO
         where PUDI_ID_TIPO_DIRECTORIO = p_tipo_directorio and PUDI_ID_PUESTO = v_puesto;

        if v_old is distinct from v_i then
            update PUESTO_DIRECTORIO set PUDI_ORDEN = v_i
             where PUDI_ID_TIPO_DIRECTORIO = p_tipo_directorio and PUDI_ID_PUESTO = v_puesto;

            perform bitacora_detalle_registrar(v_bita, v_puesto, 'Orden en el directorio',
                                               v_old::text, v_i::text);
        end if;
    end loop;

    return 'Orden actualizado.';
end;
$$ language plpgsql;

/* Reordenar coordinaciones nacionales. Misma regla. */
create or replace function reordenar_coordinaciones(p_actor INT4, p_coordinacion_movida INT4, p_coordinaciones INT4[])
returns text as $$
declare
    v_total  INT4;
    v_i      INT4;
    v_cona   INT4;
    v_old    INT4;
    v_bita   INT4;
begin
    if not cuenta_tiene_privilegio(p_actor, 'gestionar_coordinaciones') then
        raise exception 'La cuenta no tiene permiso para gestionar coordinaciones.';
    end if;

    select count(*) into v_total from COORDINACION_NACIONAL;
    if v_total = 0 then
        return 'No hay coordinaciones.';
    end if;

    if p_coordinaciones is null
       or cardinality(p_coordinaciones) <> v_total
       or (select count(distinct x) from unnest(p_coordinaciones) x) <> v_total
       or (select count(*) from COORDINACION_NACIONAL
            where CONA_ID_COORDINACION_NACIONAL = any(p_coordinaciones)) <> v_total then
        raise exception 'La lista debe contener todas las coordinaciones, una sola vez.';
    end if;

    if not (p_coordinacion_movida = any(p_coordinaciones)) then
        raise exception 'La coordinación movida no está en la lista.';
    end if;

    v_bita := bitacora_registrar(p_actor, 'Reordenamiento', 'COORDINACION_NACIONAL', p_coordinacion_movida, null);

    for v_i in 1..v_total loop
        v_cona := p_coordinaciones[v_i];

        select CONA_ORDEN into v_old from COORDINACION_NACIONAL
         where CONA_ID_COORDINACION_NACIONAL = v_cona;

        if v_old is distinct from v_i then
            update COORDINACION_NACIONAL set CONA_ORDEN = v_i
             where CONA_ID_COORDINACION_NACIONAL = v_cona;

            perform bitacora_detalle_registrar(v_bita, v_cona, 'Orden', v_old::text, v_i::text);
        end if;
    end loop;

    return 'Orden actualizado.';
end;
$$ language plpgsql;


/*==============================================================*/
/* 8. CATÁLOGOS INICIALES                                       */
/*   Zonas, entidades, coordinaciones, cargos y personas los    */
/*   carga el usuario o un seeder de Laravel.                   */
/*==============================================================*/

insert into TIPO_INSTITUCION_EDUCATIVA (TIIE_NOMBRE)
values ('Universidad'), ('Facultad'), ('Campus');

insert into TIPO_DIRECTORIO (TIDI_NOMBRE, TIDI_DESCRIPCION)
values
   ('Consejo Nacional Directivo', 'Directorio nacional, sin filtros'),
   ('Consejos Regionales', 'Filtrado por zona'),
   ('Coordinaciones Nacionales', 'Filtrado por coordinación y zona'),
   ('Instituciones', 'Consulta por zona, participación y año; no es lista de puestos');

insert into TIPO_CONTACTO (TICO_NOMBRE, TICO_PERMITE_LADA, TICO_PERMITE_EXTENSION)
values
   ('Teléfono', true, true),
   ('Celular', true, false),
   ('Correo', false, false);

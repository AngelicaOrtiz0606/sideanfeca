/*==============================================================*/
/* PROYECTO:  SIDEANFECA                                        */
/* DBMS:      PostgreSQL 18                                     */
/* Versión:   script_13                                      */
/*                                                              */
/* - Usuarios, roles, privilegios y bitácora: tablas propias    */
/*   (sección 8). Laravel autentica con USUARIO y autoriza con  */
/*   ROL / ROL_PRIVILEGIO / PRIVILEGIO.                         */
/* - Catálogos de domicilio (entidad, municipio, colonia): se   */
/*   cargan desde el CSV oficial de Correos de México.          */
/* - Orden de los puestos en cada directorio: manual, en        */
/*   PUESTO_DIRECTORIO. No hay reglas de orden automáticas.     */
/* - Historial de participación: solo informativo y para el     */
/*   filtro de año. No genera directorios.                      */
/* - Reglas que dependen de Laravel (no de la BD):              */
/*     · Persona con al menos una designación                   */
/*     · Coordinador y secretario técnico regional con          */
/*       coordinación                                           */
/*     · Teléfono con lada/extensión según TIPO_CONTACTO        */
/*==============================================================*/

create extension if not exists btree_gist;


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
   COLO_TIPO             VARCHAR(30) not null,
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

/* Coordinaciones nacionales temáticas */
create table COORDINACION_NACIONAL (
   CONA_ID_COORDINACION_NACIONAL SERIAL not null,
   CONA_NOMBRE           VARCHAR(70) not null,
   CONA_DESCRIPCION      VARCHAR(100) null,
   CONA_FECHA_INICIO     DATE not null,
   CONA_FECHA_FIN        DATE null,
   constraint PK_COORDINACION_NACIONAL primary key (CONA_ID_COORDINACION_NACIONAL),
   constraint CONA_NOMBRE_UQ unique (CONA_NOMBRE),
   constraint CKC_CONA_FECHAS check (
       CONA_FECHA_FIN is null or CONA_FECHA_FIN >= CONA_FECHA_INICIO
   )
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

/* Toda persona pertenece a UNA institución O a UNA organización
   (exactamente una). Sus cargos se registran en DESIGNACION.
   Laravel valida que tenga al menos una designación. */
create table PERSONA (
   PERS_ID_PERSONA       SERIAL not null,
   PERS_NOMBRE           VARCHAR(60) not null,
   PERS_PRIMER_APELLIDO VARCHAR(45) not null,
   PERS_SEGUNDO_APELLIDO VARCHAR(45) null,
   PERS_CURP             VARCHAR(18) null,
   PERS_GENERO           VARCHAR(1) not null,
   PERS_ID_INSTITUCION_EDUCATIVA INT4 null,
   PERS_ID_ORGANIZACION  INT4 null,
   PERS_ACTIVO           BOOL not null default true,
   constraint PK_PERSONA primary key (PERS_ID_PERSONA),
   constraint CKC_PERS_GENERO check (PERS_GENERO in ('F','M')),
   /* CURP opcional. Si se captura: 18 caracteres, en mayúsculas, y única. */
   constraint CKC_PERS_CURP check (PERS_CURP is null or
       PERS_CURP ~ '^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$'),
   constraint PERS_CURP_UQ unique (PERS_CURP),
   /* Exactamente una pertenencia: si una es nula, la otra no */
   constraint CKC_PERS_PERTENENCIA check (
       (PERS_ID_INSTITUCION_EDUCATIVA is null) <> (PERS_ID_ORGANIZACION is null)
   ),
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
      references PERSONA (PERS_ID_PERSONA),
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
      references PERSONA (PERS_ID_PERSONA),
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
   EXCLUDE: un mismo puesto no puede tener dos periodos que se
   traslapen. Para asignarlo a otra persona, primero se cierra la
   designación anterior (DESI_FECHA_FIN). Un suplente empieza el
   día siguiente al fin de la anterior. */
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
begin
    select PUES_ID_INSTITUCION_EDUCATIVA into v_inst_puesto
    from PUESTO
    where PUES_ID_PUESTO = new.DESI_ID_PUESTO;

    if v_inst_puesto is not null then
        select PERS_ID_INSTITUCION_EDUCATIVA into v_inst_persona
        from PERSONA
        where PERS_ID_PERSONA = new.DESI_ID_PERSONA;

        if v_inst_persona is distinct from v_inst_puesto then
            raise exception 'La persona debe pertenecer a la institución del puesto.';
        end if;
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
     Laravel pueda reescribir toda la lista en una sola transacción.
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
      references PUESTO (PUES_ID_PUESTO),
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
/* 7. SEGURIDAD: privilegios, roles, usuarios y bitácora        */
/*   Laravel autentica con USUARIO y autoriza con                */
/*   ROL -> ROL_PRIVILEGIO -> PRIVILEGIO.                        */
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

/* Roles (Administrador, Capturista, Consulta...). */
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

/* Cuentas de acceso al sistema.
   USUA_ID_PERSONA es opcional: el superusuario puede ser una persona
   del directorio; una administradora puede no serlo.
   Cada persona tiene como máximo un usuario. */
create table USUARIO (
   USUA_ID_USUARIO       SERIAL not null,
   USUA_ID_PERSONA       INT4 null,
   USUA_ID_ROL           INT4 not null,
   USUA_NOMBRE_USUARIO   VARCHAR(30) not null,
   USUA_CORREO           VARCHAR(254) not null,
   USUA_CONTRASENIA      VARCHAR(255) not null,
   USUA_FECHA_REGISTRO   TIMESTAMP not null default now(),
   USUA_ACTIVO           BOOL not null default true,
   constraint PK_USUARIO primary key (USUA_ID_USUARIO),
   constraint USUA_NOMBRE_USUARIO_UQ unique (USUA_NOMBRE_USUARIO),
   constraint USUA_PERSONA_UQ unique (USUA_ID_PERSONA),
   constraint CKC_USUA_CORREO check (USUA_CORREO ~ '^[^@\s]+@[^@\s]+\.[^@\s]+$'),
   constraint FK_USUA_PERSONA foreign key (USUA_ID_PERSONA)
      references PERSONA (PERS_ID_PERSONA),
   constraint FK_USUA_ROL foreign key (USUA_ID_ROL)
      references ROL (ROL_ID_ROL)
);
create index USUA_ROL_FK on USUARIO (USUA_ID_ROL);

/* Un correo no se repite, sin importar mayúsculas. */
create unique index USUA_CORREO_UQ on USUARIO (lower(USUA_CORREO));

/* Recuperación de contraseña. Se guarda solo el HASH del token
   (nunca el token en texto plano), con fecha de expiración y de uso. */
create table RECUPERACION_CONTRASENIA (
   RECO_ID_RECUPERACION  SERIAL not null,
   RECO_ID_USUARIO       INT4 not null,
   RECO_TOKEN_HASH       VARCHAR(64) not null,
   RECO_FECHA_CREACION   TIMESTAMP not null default now(),
   RECO_FECHA_EXPIRA     TIMESTAMP not null,
   RECO_FECHA_USO        TIMESTAMP null,
   constraint PK_RECUPERACION_CONTRASENIA primary key (RECO_ID_RECUPERACION),
   constraint RECO_TOKEN_HASH_UQ unique (RECO_TOKEN_HASH),
   constraint CKC_RECO_FECHAS check (RECO_FECHA_EXPIRA > RECO_FECHA_CREACION),
   constraint FK_RECO_USUARIO foreign key (RECO_ID_USUARIO)
      references USUARIO (USUA_ID_USUARIO)
);
create index RECO_USUARIO_FK on RECUPERACION_CONTRASENIA (RECO_ID_USUARIO);


/* Bitácora: registro de cada alta, modificación, activación o
   desactivación. Quién lo hizo = USUARIO (puede no tener persona). */
create table BITACORA (
   BITA_ID_BITACORA      SERIAL not null,
   BITA_FECHA_HORA       TIMESTAMP not null default now(),
   BITA_ID_USUARIO       INT4 not null,
   BITA_ACCION           VARCHAR(40) not null,
   BITA_ENTIDAD          VARCHAR(50) not null,
   BITA_ID_REGISTRO      INT4 null,
   BITA_DESCRIPCION      VARCHAR(100) null,
   constraint PK_BITACORA primary key (BITA_ID_BITACORA),
   constraint CKC_BITA_ACCION check (BITA_ACCION in
      ('Registro','Modificacion','Activacion','Desactivacion')),
   constraint FK_BITA_USUARIO foreign key (BITA_ID_USUARIO)
      references USUARIO (USUA_ID_USUARIO)
);
create index BITA_USUARIO_FK on BITACORA (BITA_ID_USUARIO);

/* La bitácora solo se inserta: no se edita ni se borra.
   (TRUNCATE no lo cubre; la cuenta de la aplicación no debe tenerlo.) */
create or replace function bitacora_solo_insercion()
returns trigger as $$
begin
    raise exception 'La bitácora es de solo inserción.';
end;
$$ language plpgsql;

create trigger trg_bitacora_solo_insercion
before update or delete on BITACORA
for each row execute function bitacora_solo_insercion();


/*==============================================================*/
/* 8. CATÁLOGOS INICIALES (ejemplo)                             */
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

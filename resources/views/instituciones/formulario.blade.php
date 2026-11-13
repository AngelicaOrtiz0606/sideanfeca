{{--
    Registro y edición de una institución, en pantalla propia y por secciones.
    - Las opciones que no aplican se ocultan solas (institucion-formulario.js):
      la universidad solo para facultad o campus; el número solo para afiliada;
      matriz solo para universidad; el domicilio es opcional para una matriz.
    - Al editar, la participación y su fecha no se cambian aquí (tienen su propia
      acción con historial) y la razón social se corrige sin historial.
--}}
@extends('layouts.admin')

@php
    $esNueva = $institucion === null;
    $tipo = old('tipo', $institucion?->tipo);
    $participacion = $esNueva ? old('participacion') : $institucion->participacion;
    $sector = old('sector', $institucion?->sector);
    $esMatriz = $participacion === 'Matriz';
    $descripcionesTipo = ['Universidad' => 'Puede ser matriz o tener facultades y campus.', 'Facultad' => 'Pertenece a una universidad.', 'Campus' => 'Pertenece a una universidad.'];
    $descripcionesParticipacion = ['Matriz' => 'Solo universidades. Sin número.', 'Afiliada' => 'Con número de afiliación de 7 dígitos.', 'Observadora' => 'Sin número de afiliación.'];
@endphp

@section('titulo', $esNueva ? 'Nueva institución' : 'Editar institución')

@section('contenido')
    <a href="{{ route('instituciones.index') }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        Instituciones
    </a>

    <header class="pagina__encabezado">
        <div>
            @unless ($esNueva)
                <p class="pagina__antetitulo">{{ $institucion->participacion }} · {{ $institucion->tipo }}</p>
            @endunless
            <h1 class="pagina__titulo">{{ $esNueva ? 'Nueva institución' : $institucion->nombre }}</h1>
        </div>
    </header>

    <form method="POST" action="{{ $esNueva ? route('instituciones.store') : route('instituciones.update', $institucion->id) }}"
          class="formulario formulario--pantalla formulario--institucion" novalidate data-validar data-institucion>
        @csrf
        @unless ($esNueva)
            @method('PUT')
            <input type="hidden" name="_id" value="{{ $institucion->id }}">
        @endunless

        <x-nota-obligatorios />
        <x-errores-formulario />

        {{-- 1. Tipo y participación --}}
        <fieldset class="tarjeta seccion-formulario">
            <legend class="seccion-formulario__titulo">Tipo y participación</legend>

            <div class="campo">
                <span class="campo__etiqueta" id="etiqueta-tipo">Tipo <x-obligatorio /></span>
                <div class="opciones-nivel__lista" role="radiogroup" aria-labelledby="etiqueta-tipo">
                    @foreach ($tipos as $opcion)
                        <label class="opcion-nivel">
                            <input type="radio" name="tipo" value="{{ $opcion }}" required @checked($tipo === $opcion) data-tipo>
                            <span class="opcion-nivel__nombre">{{ $opcion }}</span>
                            <span class="opcion-nivel__ayuda">{{ $descripcionesTipo[$opcion] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="campo" data-solo-con-universidad @unless (in_array($tipo, ['Facultad', 'Campus'], true)) hidden @endunless>
                <label for="universidad" class="campo__etiqueta">Universidad a la que pertenece <x-obligatorio /></label>
                <x-buscador-universidad :universidades="$universidades" :elegida="old('universidad', $institucion?->universidad_id)"
                                        :desactivado="! in_array($tipo, ['Facultad', 'Campus'], true)" />
                @if ($universidades->isEmpty())
                    <p class="campo__ayuda">Todavía no hay universidades registradas. Registra primero la universidad.</p>
                @endif
            </div>

            @if ($esNueva)
                <div class="campo">
                    <span class="campo__etiqueta" id="etiqueta-participacion">Participación <x-obligatorio /></span>
                    <div class="opciones-nivel__lista" role="radiogroup" aria-labelledby="etiqueta-participacion">
                        @foreach ($participaciones as $opcion)
                            <label class="opcion-nivel">
                                <input type="radio" name="participacion" value="{{ $opcion }}" required @checked($participacion === $opcion) data-participacion>
                                <span class="opcion-nivel__nombre">{{ $opcion }}</span>
                                <span class="opcion-nivel__ayuda">{{ $descripcionesParticipacion[$opcion] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="campo__ayuda" data-aviso-matriz hidden>Solo una universidad puede ser matriz.</p>
                </div>
            @else
                <div class="campo">
                    <span class="campo__etiqueta">Participación</span>
                    <p class="campo__salida">{{ $institucion->participacion }}</p>
                    <p class="campo__ayuda">Para cambiarla se usa <strong>Cambio de participación</strong>, que guarda su historial.</p>
                    <input type="hidden" value="{{ $institucion->participacion }}" data-participacion-fija>
                </div>
            @endif

            <div class="formulario__rejilla">
                <div class="campo" data-solo-afiliada @unless ($participacion === 'Afiliada') hidden @endunless>
                    <label for="numero" class="campo__etiqueta">Número de afiliación <x-obligatorio /></label>
                    <input id="numero" name="numero" type="text" inputmode="numeric" maxlength="7" pattern="[0-9]{7}" required
                           class="campo__entrada campo__entrada--numerica" value="{{ old('numero', $institucion?->numero) }}"
                           @unless ($participacion === 'Afiliada') disabled @endunless
                           data-existentes="{{ json_encode($numerosUsados) }}"
                           data-mensaje-repetido="El número ya pertenece a :existente."
                           data-mensaje-formato="El número de afiliación tiene exactamente 7 dígitos."
                           @if ($errors->has('numero')) aria-invalid="true" @endif>
                </div>

                @if ($esNueva)
                    <div class="campo">
                        <label for="desde" class="campo__etiqueta">Participa desde <x-obligatorio /></label>
                        <input id="desde" name="desde" type="date" required min="1900-01-01" max="{{ now()->toDateString() }}"
                               class="campo__entrada" value="{{ old('desde', now()->toDateString()) }}"
                               @if ($errors->has('desde')) aria-invalid="true" @endif>
                        <p class="campo__ayuda">Se usa en el historial y en el filtro por año.</p>
                    </div>
                @endif
            </div>
        </fieldset>

        {{-- 2. Datos generales --}}
        <fieldset class="tarjeta seccion-formulario">
            <legend class="seccion-formulario__titulo">Datos generales</legend>

            <div class="campo">
                <label for="razon_social" class="campo__etiqueta">Razón social <x-obligatorio /></label>
                <input id="razon_social" name="razon_social" type="text" maxlength="200" required autocomplete="organization" data-texto-seguro
                       class="campo__entrada" value="{{ old('razon_social', $institucion?->nombre) }}" placeholder="Ej. Facultad de Contaduría y Administración"
                       @if ($errors->has('razon_social')) aria-invalid="true" @endif>
                @unless ($esNueva)
                    <p class="campo__ayuda">Aquí se corrigen errores de escritura. Si la institución cambió de nombre, usa <strong>Cambio de razón social</strong> para guardar el historial.</p>
                @endunless
            </div>

            <div class="formulario__rejilla">
                <div class="campo">
                    <span class="campo__etiqueta" id="etiqueta-sector">Sector <x-obligatorio /></span>
                    <div class="opciones-en-linea" role="radiogroup" aria-labelledby="etiqueta-sector">
                        @foreach ($sectores as $valor => $texto)
                            <label class="opcion-en-linea">
                                <input type="radio" name="sector" value="{{ $valor }}" required @checked($sector === $valor)>
                                {{ $texto }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="campo campo--ancho">
                    <label for="sitio_web" class="campo__etiqueta">Sitio web <span class="campo__opcional">(opcional)</span></label>
                    <input id="sitio_web" name="sitio_web" type="url" maxlength="200" inputmode="url" autocomplete="url"
                           class="campo__entrada" value="{{ old('sitio_web', $institucion?->sitio_web) }}" placeholder="https://www.ejemplo.edu.mx"
                           data-mensaje-formato="Escribe la dirección completa, por ejemplo https://www.unam.mx."
                           @if ($errors->has('sitio_web')) aria-invalid="true" @endif>
                </div>
            </div>
        </fieldset>

        {{-- 3. Domicilio (opcional para una matriz) --}}
        <x-campos-domicilio :domicilio="$institucion?->domicilio" :zonas="$zonas" :zona="$institucion?->zona_id" :obligatorio="! $esMatriz" />

        <div class="formulario__acciones">
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="correcto" />
                {{ $esNueva ? 'Registrar institución' : 'Guardar cambios' }}
            </button>
            <a href="{{ route('instituciones.index') }}" class="boton boton--secundario">Cancelar</a>
        </div>
    </form>
@endsection

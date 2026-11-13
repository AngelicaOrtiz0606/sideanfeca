{{--
    Registro de una institución, en pantalla propia y por secciones. Se avanza
    paso a paso: primero el tipo; al elegirlo aparece la participación; al
    elegirla aparece lo demás. (Después se edita por secciones en su consulta.)
    - Las opciones que no aplican se ocultan solas (institucion-formulario.js):
      la universidad solo para facultad o campus; el número solo para afiliada;
      matriz solo para universidad; el domicilio es opcional para una matriz.
--}}
@extends('layouts.admin')

@php
    $tipo = old('tipo');
    $participacion = old('participacion');
    $sector = old('sector');
    $esMatriz = $participacion === 'Matriz';
    $descripcionesTipo = ['Universidad' => 'Puede ser matriz o tener facultades y campus.', 'Facultad' => 'Pertenece a una universidad.', 'Campus' => 'Pertenece a una universidad.'];
    $descripcionesParticipacion = ['Matriz' => 'Solo universidades. Sin número.', 'Afiliada' => 'Con número de afiliación de 7 dígitos.', 'Observadora' => 'Sin número de afiliación.'];
@endphp

@section('titulo', 'Nueva institución')

@section('contenido')
    <a href="{{ route('instituciones.index') }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        Instituciones
    </a>

    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Nueva institución</h1>
        </div>
    </header>

    <form method="POST" action="{{ route('instituciones.store') }}"
          class="formulario formulario--pantalla formulario--institucion" novalidate data-validar data-institucion>
        @csrf

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
                <x-buscador-universidad :universidades="$universidades" :elegida="old('universidad')"
                                        :desactivado="! in_array($tipo, ['Facultad', 'Campus'], true)" />
                @if ($universidades->isEmpty())
                    <p class="campo__ayuda">Todavía no hay universidades registradas. Registra primero la universidad.</p>
                @endif
            </div>

            <div class="campo" data-paso-participacion @unless ($tipo) hidden @endunless>
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

            <div class="formulario__rejilla" data-paso-resto @unless ($tipo && $participacion) hidden @endunless>
                <div class="campo campo--mitad">
                    <label for="desde" class="campo__etiqueta">Participa desde <x-obligatorio /></label>
                    <input id="desde" name="desde" type="date" required min="1900-01-01" max="{{ now()->toDateString() }}"
                           class="campo__entrada" value="{{ old('desde', now()->toDateString()) }}"
                           @if ($errors->has('desde')) aria-invalid="true" @endif>
                    <p class="campo__ayuda">Se usa en el historial y en el filtro por año.</p>
                </div>

                <x-numero-afiliacion id="numero" :existentes="$numerosUsados" fecha="#desde" zona="#zona"
                                     class="campo--mitad" data-solo-afiliada :hidden="$participacion !== 'Afiliada'" />
            </div>
            <p class="formulario__guia" data-guia @if ($tipo && $participacion) hidden @endif>
                {{ $tipo ? 'Ahora elige su participación para continuar.' : 'Elige el tipo de institución para continuar.' }}
            </p>
        </fieldset>

        <div class="formulario__resto" data-paso-resto @unless ($tipo && $participacion) hidden @endunless>
        {{-- 2. Datos generales --}}
        <fieldset class="tarjeta seccion-formulario">
            <legend class="seccion-formulario__titulo">Datos generales</legend>

            <div class="campo">
                <label for="razon_social" class="campo__etiqueta">Razón social <x-obligatorio /></label>
                <input id="razon_social" name="razon_social" type="text" maxlength="200" required autocomplete="organization" data-texto-seguro
                       class="campo__entrada" value="{{ old('razon_social') }}" placeholder="Ej. Facultad de Contaduría y Administración"
                       @if ($errors->has('razon_social')) aria-invalid="true" @endif>
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
                           class="campo__entrada" value="{{ old('sitio_web') }}" placeholder="https://www.ejemplo.edu.mx"
                           data-mensaje-formato="Escribe la dirección completa, por ejemplo https://www.unam.mx."
                           @if ($errors->has('sitio_web')) aria-invalid="true" @endif>
                </div>
            </div>
        </fieldset>

        {{-- 3. Domicilio (opcional para una matriz) --}}
        <x-campos-domicilio :zonas="$zonas" :obligatorio="! $esMatriz" />
        </div>

        <div class="formulario__acciones">
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="correcto" />
                Registrar institución
            </button>
            <a href="{{ route('instituciones.index') }}" class="boton boton--secundario">Cancelar</a>
        </div>
    </form>
@endsection

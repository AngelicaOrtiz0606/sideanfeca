{{--
    Registro y edición de una institución, en pantalla propia y por secciones.
    - Al registrar se avanza paso a paso: primero el tipo; al elegirlo aparece
      la participación; al elegirla aparece lo demás. El número de afiliación
      se ve al final, junto a los botones (depende de la zona y la fecha).
    - Al editar se ve todo, ya lleno. La participación no se cambia aquí: una
      observadora pasa a afiliada con su botón (guarda historial). La razón
      social se corrige en su campo; un cambio de nombre se registra con su
      botón (guarda historial).
    - Las opciones que no aplican se ocultan solas (institucion-formulario.js).
--}}
@extends('layouts.admin')

@php
    $esNueva = $institucion === null;
    // Al editar, lo escrito se recupera solo si el error fue de este formulario (no de una ventana)
    $conOld = $esNueva || ($errors->any() && ! old('_dialogo'));
    $valor = fn (string $campo, $actual = null) => $conOld ? old($campo, $actual) : $actual;
    $tipo = $valor('tipo', $institucion?->tipo);
    $participacion = $esNueva ? old('participacion') : $institucion->participacion;
    $sector = $valor('sector', $institucion?->sector);
    $esMatriz = $participacion === 'Matriz';
    $fecha = fn (?string $dia) => $dia === null ? null : \Illuminate\Support\Carbon::parse($dia)->locale('es')->translatedFormat('j \d\e F \d\e Y');
    // Qué significa cada tipo y cada participación (no lo que hace el sistema)
    $descripcionesTipo = [
        'Universidad' => 'Puede ser matriz, si agrupa a sus facultades y campus; o participar por sí misma, como afiliada (con su número) u observadora.',
        'Facultad' => 'Escuela o facultad que forma parte de una universidad.',
        'Campus' => 'Sede de una universidad en otra ubicación.',
    ];
    $descripcionesParticipacion = [
        'Matriz' => 'Universidad que agrupa a sus facultades y campus afiliados. No es miembro por sí misma ni tiene número de afiliación.',
        'Afiliada' => 'Miembro de ANFECA, con su propio número de afiliación.',
        'Observadora' => 'Participa en ANFECA como observadora, sin ser miembro y sin número de afiliación.',
    ];
    $ventanaConError = $errors->any() ? old('_dialogo') : null;
@endphp

@section('titulo', $esNueva ? 'Nueva institución' : 'Editar '.$institucion->nombre)

@section('contenido')
    <a href="{{ $esNueva ? route('instituciones.index') : route('instituciones.show', $institucion->id) }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        {{ $esNueva ? 'Instituciones' : $institucion->nombre }}
    </a>

    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">{{ $esNueva ? 'Nueva institución' : 'Editar institución' }}</h1>
            <p class="pagina__texto">Los campos con <span class="campo__obligatorio" aria-hidden="true">*</span> son obligatorios.</p>
        </div>
    </header>

    @unless ($esNueva)
        <x-aviso-estado />
    @endunless

    <form method="POST" action="{{ $esNueva ? route('instituciones.store') : route('instituciones.update', $institucion->id) }}"
          class="formulario formulario--pantalla formulario--institucion" novalidate data-validar data-institucion>
        @csrf
        @unless ($esNueva)
            @method('PUT')
            <input type="hidden" name="_id" value="{{ $institucion->id }}">
        @endunless

        @if ($conOld)
            <x-errores-formulario />
        @endif

        {{-- 1. Tipo y participación --}}
        <x-seccion-formulario id="seccion-tipo" numero="1" titulo="Tipo y participación"
                              :descripcion="match (true) {
                                  $esNueva => 'Primero el tipo; luego su participación en ANFECA.',
                                  $posibles !== [] => 'Si la participación se capturó mal, corrígela aquí. Si cambió (de observadora a afiliada), usa «Pasar a afiliada»: así queda en su historial.',
                                  default => 'Si la participación se capturó mal, corrígela aquí.',
                              }">

            <div class="campo">
                <span class="campo__etiqueta" id="etiqueta-tipo">Tipo @unless ($motivoTipo ?? null)<x-obligatorio />@endunless</span>
                @if ($motivoTipo ?? null)
                    <p class="campo__salida">{{ $institucion->tipo }}</p>
                    <p class="campo__ayuda">{{ $motivoTipo }}</p>
                    <input type="radio" name="tipo" value="{{ $institucion->tipo }}" checked hidden data-tipo>
                @else
                    <div class="opciones-nivel__lista" role="radiogroup" aria-labelledby="etiqueta-tipo">
                        @foreach ($tipos as $opcion)
                            <label class="opcion-nivel">
                                <input type="radio" name="tipo" value="{{ $opcion }}" required @checked($tipo === $opcion) data-tipo>
                                <span class="opcion-nivel__nombre">{{ $opcion }}</span>
                                <span class="opcion-nivel__ayuda">{{ $descripcionesTipo[$opcion] }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="campo" data-solo-con-universidad @unless (in_array($tipo, ['Facultad', 'Campus'], true)) hidden @endunless>
                <label for="universidad" class="campo__etiqueta">Universidad a la que pertenece <x-obligatorio /></label>
                <x-buscador-universidad :universidades="$universidades" :elegida="$valor('universidad', $institucion?->universidad_id)"
                                        :desactivado="! in_array($tipo, ['Facultad', 'Campus'], true)" />
                @if ($universidades->isEmpty())
                    <p class="campo__ayuda">Todavía no hay universidades registradas. Registra primero la universidad.</p>
                @endif
            </div>

            @if ($esNueva)
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

                <div class="campo" data-paso-resto @unless ($tipo && $participacion) hidden @endunless>
                    <label for="desde" class="campo__etiqueta">Participa desde <x-obligatorio /></label>
                    <input id="desde" name="desde" type="date" required min="1900-01-01" max="{{ now()->toDateString() }}"
                           class="campo__entrada campo__entrada--fecha" value="{{ old('desde', now()->toDateString()) }}"
                           @if ($errors->has('desde')) aria-invalid="true" @endif>
                    <p class="campo__ayuda">Se usa en el historial, en el filtro por año y en el número de afiliación.</p>
                </div>

                <p class="formulario__guia" data-guia @if ($tipo && $participacion) hidden @endif>
                    {{ $tipo ? 'Ahora elige su participación para continuar.' : 'Elige el tipo de institución para continuar.' }}
                </p>
            @else
                @php $participacionElegida = $valor('participacion', $institucion->participacion); @endphp
                <div class="campo">
                    <span class="campo__etiqueta" id="etiqueta-participacion">Participación <x-obligatorio /></span>
                    <div class="opciones-nivel__lista" role="radiogroup" aria-labelledby="etiqueta-participacion">
                        @foreach ($participaciones as $opcion)
                            <label class="opcion-nivel">
                                <input type="radio" name="participacion" value="{{ $opcion }}" required @checked($participacionElegida === $opcion) data-participacion>
                                <span class="opcion-nivel__nombre">{{ $opcion }}</span>
                                <span class="opcion-nivel__ayuda">{{ $descripcionesParticipacion[$opcion] }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="campo__ayuda" data-aviso-matriz hidden>Solo una universidad puede ser matriz.</p>
                    <div class="dato-con-accion">
                        <p class="dato-con-accion__texto">
                            Hoy: <span class="participacion participacion--{{ strtolower($institucion->participacion) }}">{{ $institucion->participacion }}</span>
                            @if ($inicios['participacion'])
                                <span>desde el {{ $fecha($inicios['participacion']) }}</span>
                            @endif
                        </p>
                        @if ($posibles !== [])
                            <button type="button" @class(['boton', 'boton--secundario', 'boton--bloqueado' => $motivoParticipacion])
                                    data-abrir-dialogo="{{ $motivoParticipacion ? 'dialogo-sin-cambio' : 'dialogo-participacion' }}">
                                <x-icono nombre="historial" />
                                Pasar a afiliada
                            </button>
                        @endif
                    </div>

                    {{-- Cambiar la participación aquí es corregir un error de captura (sin historial): se confirma --}}
                    <div class="aviso aviso--atencion aviso-correccion" data-correccion data-actual="{{ $institucion->participacion }}"
                         @if ($participacionElegida === $institucion->participacion) hidden @endif>
                        <p data-correccion-texto>
                            @if ($institucion->participacion === 'Afiliada')
                                Una afiliada no deja de serlo: cámbiala solo si se capturó mal.
                            @else
                                Si la institución fue aprobada como afiliada, no la cambies aquí: usa «Pasar a afiliada» para que quede en su historial.
                            @endif
                        </p>
                        <label class="opcion-en-linea opcion-en-linea--casilla">
                            <input type="checkbox" name="correccion_participacion" value="1" required
                                   @checked($valor('correccion_participacion', false)) @disabled($participacionElegida === $institucion->participacion)>
                            Confirmo que se capturó mal: es una corrección y no queda en el historial <x-obligatorio />
                        </label>
                    </div>
                </div>

                <div class="campo campo--mitad" data-solo-afiliada @unless ($participacionElegida === 'Afiliada') hidden @endunless>
                    <label for="numero" class="campo__etiqueta">Número de afiliación @if ($institucion->participacion === 'Afiliada')<x-obligatorio />@endif</label>
                    <input id="numero" name="numero" type="text" inputmode="numeric" maxlength="7" pattern="[0-9]{7}" @if ($institucion->participacion === 'Afiliada') required @endif
                           class="campo__entrada campo__entrada--numerica" value="{{ $valor('numero', $institucion->numero) }}"
                           data-existentes="{{ json_encode($numerosUsados) }}"
                           data-mensaje-repetido="El número ya pertenece a :existente."
                           data-mensaje-formato="El número de afiliación tiene exactamente 7 dígitos."
                           @if ($conOld && $errors->has('numero')) aria-invalid="true" @endif>
                    <p class="campo__ayuda">
                        @if ($institucion->participacion === 'Afiliada')
                            Corrígelo solo si se capturó mal: es su identificador permanente.
                        @else
                            Si la corriges a afiliada, escribe su número o déjalo vacío para asignarle el siguiente.
                        @endif
                    </p>
                </div>
            @endif
        </x-seccion-formulario>

        <div class="formulario__resto" data-paso-resto @if ($esNueva && ! ($tipo && $participacion)) hidden @endif>
        {{-- 2. Datos generales --}}
        <x-seccion-formulario id="seccion-generales" numero="2" titulo="Datos generales" descripcion="Su nombre oficial, su sector y su sitio web.">

            <div class="campo">
                <label for="razon_social" class="campo__etiqueta">Razón social <x-obligatorio /></label>
                @if ($esNueva)
                    <input id="razon_social" name="razon_social" type="text" maxlength="200" required autocomplete="organization" data-texto-seguro
                           class="campo__entrada" value="{{ old('razon_social') }}" placeholder="Ej. Facultad de Contaduría y Administración"
                           @if ($errors->has('razon_social')) aria-invalid="true" @endif>
                @else
                    <div class="campo__con-accion">
                        <input id="razon_social" name="razon_social" type="text" maxlength="200" required autocomplete="organization" data-texto-seguro
                               class="campo__entrada" value="{{ $valor('razon_social', $institucion->nombre) }}"
                               @if ($conOld && $errors->has('razon_social')) aria-invalid="true" @endif>
                        <button type="button" class="boton boton--secundario" data-abrir-dialogo="dialogo-razon-social">
                            <x-icono nombre="historial" />
                            Registrar cambio de nombre
                        </button>
                    </div>
                    <p class="campo__ayuda">Aquí se corrigen errores de escritura. Si la institución cambió de nombre, usa «Registrar cambio de nombre»: guarda el historial.</p>
                @endif
            </div>

            <div class="formulario__rejilla">
                <fieldset class="campo">
                    <legend class="campo__etiqueta">Sector <x-obligatorio /></legend>
                    <div class="segmentado">
                        @foreach ($sectores as $clave => $texto)
                            <label class="segmentado__opcion">
                                <input type="radio" name="sector" value="{{ $clave }}" required @checked($sector === $clave)>
                                {{ $texto }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="campo campo--ancho">
                    <label for="sitio_web" class="campo__etiqueta">Sitio web <span class="campo__opcional">(opcional)</span></label>
                    <input id="sitio_web" name="sitio_web" type="url" maxlength="200" inputmode="url" autocomplete="url"
                           class="campo__entrada" value="{{ $valor('sitio_web', $institucion?->sitio_web) }}" placeholder="https://www.ejemplo.edu.mx"
                           data-mensaje-formato="Escribe la dirección completa, por ejemplo https://www.unam.mx."
                           @if ($conOld && $errors->has('sitio_web')) aria-invalid="true" @endif>
                </div>
            </div>
        </x-seccion-formulario>

        {{-- 3. Domicilio (opcional para una matriz) --}}
        <x-campos-domicilio :domicilio="$institucion?->domicilio" :zonas="$zonas" :zona="$institucion?->zona_id" :obligatorio="! $esMatriz" numero="3" />
        </div>

        @if ($esNueva)
            {{-- Cierre: al registrar, el número de afiliación se ve junto a los botones --}}
            <div class="tarjeta cierre-registro">
                <x-numero-afiliacion id="numero" :existentes="$numerosUsados" fecha="#desde" zona="#zona"
                                     class="cierre-registro__numero" data-solo-afiliada :hidden="$participacion !== 'Afiliada'" />

                <div class="cierre-registro__acciones">
                    <a href="{{ route('instituciones.index') }}" class="boton boton--secundario">Cancelar</a>
                    <button type="submit" class="boton boton--primario">
                        <x-icono nombre="correcto" />
                        Registrar institución
                    </button>
                </div>
            </div>
        @else
            <div class="tarjeta cierre-registro">
                <div class="cierre-registro__acciones">
                    <a href="{{ route('instituciones.show', $institucion->id) }}" class="boton boton--secundario">Cancelar</a>
                    <button type="submit" class="boton boton--primario">
                        <x-icono nombre="correcto" />
                        Guardar cambios
                    </button>
                </div>
            </div>
        @endif
    </form>

    @unless ($esNueva)
        {{-- Cambio de nombre (con historial) --}}
        <dialog id="dialogo-razon-social" class="dialogo" aria-labelledby="dialogo-razon-social-titulo" data-reiniciar
                @if ($ventanaConError === 'razon-social') data-abrir-al-cargar @endif>
            <form method="POST" action="{{ route('instituciones.razon-social', $institucion->id) }}" class="formulario" novalidate data-validar>
                @csrf
                @method('PATCH')
                <input type="hidden" name="_dialogo" value="razon-social">

                <h2 id="dialogo-razon-social-titulo" class="dialogo__titulo">Cambio de nombre</h2>
                <p class="dialogo__texto">«{{ $institucion->nombre }}» queda en el historial hasta el día anterior.</p>

                @if ($ventanaConError === 'razon-social')
                    <x-errores-formulario />
                @endif

                <div class="campo">
                    <label for="nueva-razon-social" class="campo__etiqueta">Nueva razón social <x-obligatorio /></label>
                    <input id="nueva-razon-social" name="razon_social" type="text" required maxlength="200" autocomplete="off" data-texto-seguro
                           class="campo__entrada" value="{{ $ventanaConError === 'razon-social' ? old('razon_social') : '' }}"
                           @if ($ventanaConError === 'razon-social' && $errors->has('razon_social')) aria-invalid="true" @endif>
                </div>

                <div class="campo">
                    <label for="razon-social-desde" class="campo__etiqueta">Desde <x-obligatorio /></label>
                    <input id="razon-social-desde" name="desde" type="date" required min="{{ $inicios['razon_social'] }}" max="{{ now()->toDateString() }}"
                           class="campo__entrada" value="{{ $ventanaConError === 'razon-social' ? old('desde') : now()->toDateString() }}"
                           @if ($ventanaConError === 'razon-social' && $errors->has('desde')) aria-invalid="true" @endif>
                </div>

                <div class="dialogo__acciones">
                    <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
                    <button type="submit" class="boton boton--primario">
                        <x-icono nombre="correcto" />
                        Guardar
                    </button>
                </div>
            </form>
        </dialog>

        {{-- Pasar a afiliada (solo una observadora; no se puede revertir) --}}
        @if ($posibles !== [] && ! $motivoParticipacion)
            <dialog id="dialogo-participacion" class="dialogo" aria-labelledby="dialogo-participacion-titulo" data-reiniciar
                    @if ($ventanaConError === 'participacion') data-abrir-al-cargar @endif>
                <form method="POST" action="{{ route('instituciones.participacion', $institucion->id) }}" class="formulario" novalidate data-validar>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="_dialogo" value="participacion">
                    <input type="hidden" name="_id" value="{{ $institucion->id }}">
                    <input type="hidden" name="participacion" value="Afiliada">

                    <h2 id="dialogo-participacion-titulo" class="dialogo__titulo">Pasar a afiliada</h2>
                    <p class="dialogo__texto">Su periodo como observadora termina el día anterior. Una afiliada ya no puede volver a ser observadora.</p>

                    @if ($ventanaConError === 'participacion')
                        <x-errores-formulario />
                    @endif

                    <div class="campo">
                        <label for="participacion-desde" class="campo__etiqueta">Afiliada desde <x-obligatorio /></label>
                        <input id="participacion-desde" name="desde" type="date" required min="{{ $inicios['participacion'] }}" max="{{ now()->toDateString() }}"
                               class="campo__entrada" value="{{ $ventanaConError === 'participacion' ? old('desde') : now()->toDateString() }}"
                               @if ($ventanaConError === 'participacion' && $errors->has('desde')) aria-invalid="true" @endif>
                    </div>

                    <x-numero-afiliacion id="nuevo-numero" :existentes="$numerosUsados" fecha="#participacion-desde" :zona-numero="$institucion->zona_numero" />

                    <div class="dialogo__acciones">
                        <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
                        <button type="submit" class="boton boton--primario">
                            <x-icono nombre="correcto" />
                            Guardar
                        </button>
                    </div>
                </form>
            </dialog>
        @elseif ($motivoParticipacion && $posibles !== [])
            <dialog id="dialogo-sin-cambio" class="dialogo" aria-labelledby="dialogo-sin-cambio-titulo">
                <h2 id="dialogo-sin-cambio-titulo" class="dialogo__titulo">No puede pasar a afiliada</h2>
                <p class="dialogo__texto dialogo__texto--separado">{{ $motivoParticipacion }}</p>
                <div class="dialogo__acciones">
                    <button type="button" class="boton boton--primario" data-cerrar-dialogo>Entendido</button>
                </div>
            </dialog>
        @endif
    @endunless
@endsection

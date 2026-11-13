{{--
    Consulta de una institución, por pestañas. Cada dato se edita donde se ve:
    - General: tres tarjetas (identificación, ubicación, sitio web); cada una
      tiene su botón Editar, que abre una ventana solo con sus campos.
    - Participación: su historial. Una observadora puede pasar a afiliada
      (nunca al revés; una matriz no cambia).
    - Razón social: su historial. La vigente se corrige ahí (sin historial) y
      un cambio de nombre se registra con historial.
    - Sedes (solo universidades): sus facultades y campus.
    Las pestañas son enlaces (?pestana=…): funcionan sin JavaScript y, al
    volver de un cambio, se abre la pestaña donde se hizo (pestanas.js las
    cambia sin recargar).
--}}
@extends('layouts.admin')

@section('titulo', $institucion->nombre)

@php
    $fecha = fn (?string $dia) => $dia === null ? null : \Illuminate\Support\Carbon::parse($dia)->locale('es')->translatedFormat('j \d\e F \d\e Y');
    $hoy = now()->toDateString();
    $domicilio = $institucion->domicilio;
    $esMatriz = $institucion->participacion === 'Matriz';
    $seccionConError = $errors->any() ? old('_seccion') : null;
    $ventanaConError = $errors->any() ? old('_dialogo') : null;
@endphp

@section('contenido')
    <a href="{{ route('instituciones.index') }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        Instituciones
    </a>

    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">{{ $institucion->nombre }}</h1>
            <div class="pagina__detalles">
                <span class="participacion participacion--{{ strtolower($institucion->participacion) }}">{{ $institucion->participacion }}</span>
                <span @class(['estado', 'estado--activo' => $institucion->activa])>{{ $institucion->activa ? 'Activa' : 'Desactivada' }}</span>
            </div>
        </div>
    </header>

    <x-aviso-estado />

    <div class="pestanas" data-pestanas>
        <nav class="pestanas__lista" role="tablist" aria-label="Información de la institución">
            @foreach ($pestanas as $clave => $titulo)
                <a href="{{ route('instituciones.show', ['institucion' => $institucion->id, 'pestana' => $clave]) }}"
                   id="pestana-{{ $clave }}" class="pestanas__pestana" role="tab"
                   aria-controls="panel-{{ $clave }}" aria-selected="{{ $pestana === $clave ? 'true' : 'false' }}"
                   @if ($pestana !== $clave) tabindex="-1" @endif data-pestana="{{ $clave }}">
                    {{ $titulo }}
                    @if ($clave === 'sedes')
                        <span class="pestanas__cifra">{{ $sedes->count() }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        {{-- General --}}
        <section id="panel-general" class="pestanas__panel" role="tabpanel" aria-labelledby="pestana-general" @if ($pestana !== 'general') hidden @endif>
            <div class="ficha">
                <section class="tarjeta" aria-labelledby="ficha-identificacion">
                    <div class="ficha__encabezado">
                        <h2 id="ficha-identificacion" class="ficha__titulo">Identificación</h2>
                        <button type="button" class="accion accion--editar accion--a-la-derecha" data-abrir-dialogo="dialogo-identificacion">
                            <x-icono nombre="lapiz" />
                            <span class="accion__texto">Editar identificación</span>
                        </button>
                    </div>
                    <dl class="ficha__datos">
                        <div>
                            <dt>Tipo</dt>
                            <dd>{{ $institucion->tipo }}</dd>
                        </div>
                        @if ($institucion->universidad)
                            <div>
                                <dt>Universidad</dt>
                                <dd><a href="{{ route('instituciones.show', $institucion->universidad_id) }}" class="ficha__enlace">{{ $institucion->universidad }}</a></dd>
                            </div>
                        @endif
                        <div>
                            <dt>Sector</dt>
                            <dd>{{ $sectores[$institucion->sector] ?? $institucion->sector }}</dd>
                        </div>
                        @if ($institucion->numero)
                            <div>
                                <dt>Número de afiliación</dt>
                                <dd><span class="numero-afiliacion">{{ $institucion->numero }}</span></dd>
                            </div>
                        @endif
                    </dl>
                </section>

                <section class="tarjeta" aria-labelledby="ficha-ubicacion">
                    <div class="ficha__encabezado">
                        <h2 id="ficha-ubicacion" class="ficha__titulo">Ubicación</h2>
                        <button type="button" class="accion accion--editar accion--a-la-derecha" data-abrir-dialogo="dialogo-ubicacion">
                            <x-icono nombre="lapiz" />
                            <span class="accion__texto">Editar ubicación</span>
                        </button>
                    </div>
                    @if ($domicilio)
                        <address class="ficha__domicilio">
                            {{ $domicilio->calle }} {{ $domicilio->num_ext }}@if ($domicilio->num_int), int. {{ $domicilio->num_int }}@endif<br>
                            Col. {{ $domicilio->colonia }}, C.P. {{ $domicilio->codigo_postal }}<br>
                            {{ $domicilio->municipio }}, {{ $domicilio->entidad }}
                        </address>
                    @else
                        <p class="ficha__vacio">Sin domicilio. Una matriz no lo necesita.</p>
                    @endif
                    @if ($institucion->zona_numero)
                        <dl class="ficha__datos">
                            <div>
                                <dt>Zona regional</dt>
                                <dd><span class="numero-zona">{{ $institucion->zona_numero }}</span> {{ $institucion->zona_nombre }}</dd>
                            </div>
                        </dl>
                    @endif
                </section>

                <section class="tarjeta" aria-labelledby="ficha-web">
                    <div class="ficha__encabezado">
                        <h2 id="ficha-web" class="ficha__titulo">Sitio web</h2>
                        <button type="button" class="accion accion--editar accion--a-la-derecha" data-abrir-dialogo="dialogo-web">
                            <x-icono nombre="lapiz" />
                            <span class="accion__texto">Editar sitio web</span>
                        </button>
                    </div>
                    @if ($institucion->sitio_web)
                        <a href="{{ $institucion->sitio_web }}" class="ficha__enlace ficha__enlace--web" target="_blank" rel="noopener noreferrer">{{ $institucion->sitio_web }}</a>
                    @else
                        <p class="ficha__vacio">Sin sitio web.</p>
                    @endif
                </section>
            </div>
        </section>

        {{-- Participación --}}
        <section id="panel-participacion" class="pestanas__panel" role="tabpanel" aria-labelledby="pestana-participacion" @if ($pestana !== 'participacion') hidden @endif>
            <div class="tarjeta historial">
                <ol class="linea-tiempo">
                    @foreach ($participaciones as $periodo)
                        <li @class(['linea-tiempo__item', 'linea-tiempo__item--actual' => $periodo->hasta === null])>
                            <span class="participacion participacion--{{ strtolower($periodo->tipo) }}">{{ $periodo->tipo }}</span>
                            <span class="linea-tiempo__fechas">
                                {{ $fecha($periodo->desde) }} – {{ $periodo->hasta ? $fecha($periodo->hasta) : 'hoy' }}
                            </span>
                        </li>
                    @endforeach
                </ol>
                @if ($posibles !== [])
                    <div class="historial__acciones">
                        <button type="button" @class(['boton', 'boton--secundario', 'boton--bloqueado' => $motivoParticipacion])
                                data-abrir-dialogo="{{ $motivoParticipacion ? 'dialogo-sin-cambio' : 'dialogo-participacion' }}">
                            <x-icono nombre="historial" />
                            Pasar a afiliada
                        </button>
                    </div>
                @endif
            </div>
        </section>

        {{-- Razón social --}}
        <section id="panel-razon-social" class="pestanas__panel" role="tabpanel" aria-labelledby="pestana-razon-social" @if ($pestana !== 'razon-social') hidden @endif>
            <div class="tarjeta historial">
                <ol class="linea-tiempo">
                    @foreach ($nombres as $nombre)
                        <li @class(['linea-tiempo__item', 'linea-tiempo__item--actual' => $nombre->hasta === null])>
                            <span class="linea-tiempo__titulo">
                                <span class="linea-tiempo__nombre">{{ $nombre->nombre }}</span>
                                @if ($nombre->hasta === null)
                                    <button type="button" class="accion accion--editar accion--a-la-derecha" data-abrir-dialogo="dialogo-corregir-razon">
                                        <x-icono nombre="lapiz" />
                                        <span class="accion__texto">Corregir escritura</span>
                                    </button>
                                @endif
                            </span>
                            <span class="linea-tiempo__fechas">
                                {{ $fecha($nombre->desde) }} – {{ $nombre->hasta ? $fecha($nombre->hasta) : 'hoy' }}
                            </span>
                        </li>
                    @endforeach
                </ol>
                <div class="historial__acciones">
                    <button type="button" class="boton boton--secundario" data-abrir-dialogo="dialogo-razon-social">
                        <x-icono nombre="historial" />
                        Registrar cambio de nombre
                    </button>
                </div>
            </div>
        </section>

        {{-- Sedes --}}
        @if (array_key_exists('sedes', $pestanas))
            <section id="panel-sedes" class="pestanas__panel" role="tabpanel" aria-labelledby="pestana-sedes" @if ($pestana !== 'sedes') hidden @endif>
                <div class="tarjeta tarjeta--tabla">
                    @if ($sedes->isEmpty())
                        <p class="vacio vacio--en-tabla">No tiene facultades ni campus registrados.</p>
                    @else
                        <table class="tabla">
                            <thead>
                                <tr>
                                    <th scope="col">Número</th>
                                    <th scope="col">Facultad o campus</th>
                                    <th scope="col">Participación</th>
                                    <th scope="col">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sedes as $sede)
                                    <tr @class(['tabla__fila--inactiva' => ! $sede->activa])>
                                        <td data-etiqueta="Número">
                                            @if ($sede->numero)
                                                <span class="numero-afiliacion">{{ $sede->numero }}</span>
                                            @else
                                                <span class="tabla__vacio" aria-label="Sin número">—</span>
                                            @endif
                                        </td>
                                        <th scope="row" data-etiqueta="Facultad o campus">
                                            <a href="{{ route('instituciones.show', $sede->id) }}" class="tabla__principal tabla__enlace">{{ $sede->nombre }}</a>
                                            <span class="tabla__detalle">{{ $sede->tipo }}</span>
                                        </th>
                                        <td data-etiqueta="Participación">
                                            <span class="participacion participacion--{{ strtolower($sede->participacion) }}">{{ $sede->participacion }}</span>
                                        </td>
                                        <td data-etiqueta="Estado">
                                            <span @class(['estado', 'estado--activo' => $sede->activa])>{{ $sede->activa ? 'Activa' : 'Desactivada' }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </section>
        @endif
    </div>

    {{-- ===== Ventanas ===== --}}

    {{-- Identificación: tipo, universidad, sector y número --}}
    <dialog id="dialogo-identificacion" class="dialogo dialogo--mediano" aria-labelledby="dialogo-identificacion-titulo" data-reiniciar
            @if ($seccionConError === 'identificacion') data-abrir-al-cargar @endif>
        <form method="POST" action="{{ route('instituciones.update', $institucion->id) }}" class="formulario" novalidate data-validar data-institucion>
            @csrf
            @method('PUT')
            <input type="hidden" name="_seccion" value="identificacion">
            <input type="hidden" name="_id" value="{{ $institucion->id }}">
            <input type="hidden" value="{{ $institucion->participacion }}" data-participacion-fija>

            <h2 id="dialogo-identificacion-titulo" class="dialogo__titulo">Identificación</h2>

            @if ($seccionConError === 'identificacion')
                <x-errores-formulario />
            @endif

            @php
                $tipoActual = $seccionConError === 'identificacion' ? old('tipo') : $institucion->tipo;
                $sectorActual = $seccionConError === 'identificacion' ? old('sector') : $institucion->sector;
            @endphp
            <div class="campo">
                <span class="campo__etiqueta" id="etiqueta-tipo">Tipo @unless ($motivoTipo)<x-obligatorio />@endunless</span>
                @if ($motivoTipo)
                    <p class="campo__salida">{{ $institucion->tipo }}</p>
                    <p class="campo__ayuda">{{ $motivoTipo }}</p>
                    <input type="radio" name="tipo" value="{{ $institucion->tipo }}" checked hidden data-tipo>
                @else
                    <div class="opciones-en-linea" role="radiogroup" aria-labelledby="etiqueta-tipo">
                        @foreach ($tipos as $opcion)
                            <label class="opcion-en-linea">
                                <input type="radio" name="tipo" value="{{ $opcion }}" required @checked($tipoActual === $opcion) data-tipo>
                                {{ $opcion }}
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="campo" data-solo-con-universidad @unless (in_array($tipoActual, ['Facultad', 'Campus'], true)) hidden @endunless>
                <label for="universidad" class="campo__etiqueta">Universidad a la que pertenece <x-obligatorio /></label>
                <x-buscador-universidad :universidades="$universidades"
                    :elegida="$seccionConError === 'identificacion' ? old('universidad') : $institucion->universidad_id"
                    :desactivado="! in_array($tipoActual, ['Facultad', 'Campus'], true)" />
            </div>

            <div class="campo">
                <span class="campo__etiqueta" id="etiqueta-sector">Sector <x-obligatorio /></span>
                <div class="opciones-en-linea" role="radiogroup" aria-labelledby="etiqueta-sector">
                    @foreach ($sectores as $valor => $texto)
                        <label class="opcion-en-linea">
                            <input type="radio" name="sector" value="{{ $valor }}" required @checked($sectorActual === $valor)>
                            {{ $texto }}
                        </label>
                    @endforeach
                </div>
            </div>

            @if ($institucion->participacion === 'Afiliada')
                <div class="campo">
                    <label for="numero-identificacion" class="campo__etiqueta">Número de afiliación <x-obligatorio /></label>
                    <input id="numero-identificacion" name="numero" type="text" inputmode="numeric" maxlength="7" pattern="[0-9]{7}" required
                           class="campo__entrada campo__entrada--numerica"
                           value="{{ $seccionConError === 'identificacion' ? old('numero') : $institucion->numero }}"
                           data-existentes="{{ json_encode($numerosUsados) }}"
                           data-mensaje-repetido="El número ya pertenece a :existente."
                           data-mensaje-formato="El número de afiliación tiene exactamente 7 dígitos."
                           @if ($errors->has('numero') && $seccionConError === 'identificacion') aria-invalid="true" @endif>
                </div>
            @endif

            <div class="dialogo__acciones">
                <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
                <button type="submit" class="boton boton--primario">
                    <x-icono nombre="correcto" />
                    Guardar
                </button>
            </div>
        </form>
    </dialog>

    {{-- Ubicación: domicilio y zona --}}
    <dialog id="dialogo-ubicacion" class="dialogo dialogo--amplio" aria-labelledby="dialogo-ubicacion-titulo"
            @if ($seccionConError === 'ubicacion') data-abrir-al-cargar @endif>
        <form method="POST" action="{{ route('instituciones.update', $institucion->id) }}" class="formulario" novalidate data-validar>
            @csrf
            @method('PUT')
            <input type="hidden" name="_seccion" value="ubicacion">

            <h2 id="dialogo-ubicacion-titulo" class="dialogo__titulo">Ubicación</h2>
            @if ($esMatriz)
                <p class="dialogo__texto">Opcional para una matriz. Déjalo vacío si no tiene.</p>
            @endif

            @if ($seccionConError === 'ubicacion')
                <x-errores-formulario />
            @endif

            <x-campos-domicilio :domicilio="$domicilio" :zonas="$zonas" :zona="$institucion->zona_id" :obligatorio="! $esMatriz" en-ventana />

            <div class="dialogo__acciones">
                <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
                <button type="submit" class="boton boton--primario">
                    <x-icono nombre="correcto" />
                    Guardar
                </button>
            </div>
        </form>
    </dialog>

    {{-- Sitio web --}}
    <dialog id="dialogo-web" class="dialogo" aria-labelledby="dialogo-web-titulo" data-reiniciar
            @if ($seccionConError === 'web') data-abrir-al-cargar @endif>
        <form method="POST" action="{{ route('instituciones.update', $institucion->id) }}" class="formulario" novalidate data-validar>
            @csrf
            @method('PUT')
            <input type="hidden" name="_seccion" value="web">

            <h2 id="dialogo-web-titulo" class="dialogo__titulo">Sitio web</h2>

            @if ($seccionConError === 'web')
                <x-errores-formulario />
            @endif

            <div class="campo">
                <label for="sitio-web" class="campo__etiqueta">Dirección <span class="campo__opcional">(opcional)</span></label>
                <input id="sitio-web" name="sitio_web" type="url" maxlength="200" inputmode="url" autocomplete="url"
                       class="campo__entrada" value="{{ $seccionConError === 'web' ? old('sitio_web') : $institucion->sitio_web }}" placeholder="https://www.ejemplo.edu.mx"
                       data-mensaje-formato="Escribe la dirección completa, por ejemplo https://www.unam.mx."
                       @if ($seccionConError === 'web') aria-invalid="true" @endif>
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

    {{-- Corregir la razón social vigente (sin historial) --}}
    <dialog id="dialogo-corregir-razon" class="dialogo" aria-labelledby="dialogo-corregir-razon-titulo" data-reiniciar
            @if ($seccionConError === 'razon') data-abrir-al-cargar @endif>
        <form method="POST" action="{{ route('instituciones.update', $institucion->id) }}" class="formulario" novalidate data-validar>
            @csrf
            @method('PUT')
            <input type="hidden" name="_seccion" value="razon">

            <h2 id="dialogo-corregir-razon-titulo" class="dialogo__titulo">Corregir escritura</h2>
            <p class="dialogo__texto">Para errores de captura. No queda en el historial.</p>

            @if ($seccionConError === 'razon')
                <x-errores-formulario />
            @endif

            <div class="campo">
                <label for="corregir-razon" class="campo__etiqueta">Razón social <x-obligatorio /></label>
                <input id="corregir-razon" name="razon_social" type="text" required maxlength="200" autocomplete="off" data-texto-seguro
                       class="campo__entrada" value="{{ $seccionConError === 'razon' ? old('razon_social') : $institucion->nombre }}"
                       @if ($seccionConError === 'razon') aria-invalid="true" @endif>
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

    {{-- Cambio de nombre (con historial) --}}
    <dialog id="dialogo-razon-social" class="dialogo" aria-labelledby="dialogo-razon-social-titulo" data-reiniciar
            @if ($ventanaConError === 'razon-social') data-abrir-al-cargar @endif>
        <form method="POST" action="{{ route('instituciones.razon-social', $institucion->id) }}" class="formulario" novalidate data-validar>
            @csrf
            @method('PATCH')
            <input type="hidden" name="_dialogo" value="razon-social">

            <h2 id="dialogo-razon-social-titulo" class="dialogo__titulo">Cambio de nombre</h2>
            <p class="dialogo__texto">El nombre actual queda en el historial hasta el día anterior.</p>

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
                <input id="razon-social-desde" name="desde" type="date" required min="{{ $inicios['razon_social'] }}" max="{{ $hoy }}"
                       class="campo__entrada" value="{{ $ventanaConError === 'razon-social' ? old('desde') : $hoy }}"
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
                    <label for="nuevo-numero" class="campo__etiqueta">Número de afiliación <x-obligatorio /></label>
                    <input id="nuevo-numero" name="numero" type="text" inputmode="numeric" maxlength="7" pattern="[0-9]{7}" required
                           class="campo__entrada campo__entrada--numerica" value="{{ $ventanaConError === 'participacion' ? old('numero') : '' }}"
                           data-existentes="{{ json_encode($numerosUsados) }}"
                           data-mensaje-repetido="El número ya pertenece a :existente."
                           data-mensaje-formato="El número de afiliación tiene exactamente 7 dígitos."
                           @if ($ventanaConError === 'participacion' && $errors->has('numero')) aria-invalid="true" @endif>
                </div>

                <div class="campo">
                    <label for="participacion-desde" class="campo__etiqueta">Afiliada desde <x-obligatorio /></label>
                    <input id="participacion-desde" name="desde" type="date" required min="{{ $inicios['participacion'] }}" max="{{ $hoy }}"
                           class="campo__entrada" value="{{ $ventanaConError === 'participacion' ? old('desde') : $hoy }}"
                           @if ($ventanaConError === 'participacion' && $errors->has('desde')) aria-invalid="true" @endif>
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
    @elseif ($motivoParticipacion && $posibles !== [])
        {{-- Explica por qué hoy no puede (botón gris) --}}
        <dialog id="dialogo-sin-cambio" class="dialogo" aria-labelledby="dialogo-sin-cambio-titulo">
            <h2 id="dialogo-sin-cambio-titulo" class="dialogo__titulo">No puede pasar a afiliada</h2>
            <p class="dialogo__texto dialogo__texto--separado">{{ $motivoParticipacion }}</p>
            <div class="dialogo__acciones">
                <button type="button" class="boton boton--primario" data-cerrar-dialogo>Entendido</button>
            </div>
        </dialog>
    @endif
@endsection

{{--
    Formulario de una persona, en pantalla propia, por secciones numeradas:
    1. Datos personales (la CURP avisa en amarillo si su H/M no coincide con el género).
    2. Pertenece a: una institución (buscador), una organización o ninguna.
    3. Grados académicos: varios o ninguno.
    4. Contactos: un correo y un teléfono obligatorios, que siempre se muestran
       en el sitio; aparte, otros contactos opcionales (filas que se agregan y
       se quitan; según el tipo aparecen la lada y la extensión, y cada uno
       dice si es visible en el sitio).
    Al final, Guardar y Cancelar. Hoy se usa para editar; registrar llega con
    el primer cargo. Comportamiento en persona-formulario.js.
--}}
@extends('layouts.admin')

@php
    $pertenenciaActual = $persona->institucion_id ? 'institucion' : ($persona->organizacion_id ? 'organizacion' : 'ninguna');
    $pertenencia = old('pertenencia', $pertenenciaActual);
    $genero = old('genero', $persona->genero);
    $tiene = collect(old('grados', $persona->grados->pluck('id')->all()))->map(fn ($g) => (int) $g)->all();
    // Los otros contactos: los que se escribieron (si hubo un error) o los guardados
    $filas = old('contactos') !== null
        ? collect(old('contactos'))->values()->map(fn ($c) => (object) ['tipo_id' => (int) ($c['tipo'] ?? 0), 'lada' => $c['lada'] ?? '', 'valor' => $c['valor'] ?? '', 'extension' => $c['extension'] ?? '', 'visible' => ! empty($c['visible'])])
        : $contactos['otros'];
    $telefono = $contactos['telefono'];
    // Una fila nueva empieza como celular, que es lo que más se agrega
    $tipoNuevo = $tiposContacto->firstWhere('nombre', 'Celular')?->id ?? 0;
@endphp

@section('titulo', 'Editar '.$nombre)

@section('contenido')
    <a href="{{ route('personas.show', $persona->id) }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        {{ $nombre }}
    </a>

    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Editar persona</h1>
            <p class="pagina__texto">Los campos con <span class="campo__obligatorio" aria-hidden="true">*</span> son obligatorios.</p>
        </div>
    </header>

    <form method="POST" action="{{ route('personas.update', $persona->id) }}" class="formulario" novalidate data-validar data-persona>
        @csrf
        @method('PUT')

                <x-errores-formulario />

                {{-- 1. Datos personales --}}
                <x-seccion-formulario id="seccion-datos" numero="1" titulo="Datos personales" descripcion="Así aparecerá su nombre en los directorios.">
                    <div class="formulario__rejilla">
                        <div class="campo">
                            <label for="persona-nombre" class="campo__etiqueta">Nombre <x-obligatorio /></label>
                            <input id="persona-nombre" name="nombre" type="text" maxlength="60" required autocomplete="given-name" data-texto-seguro
                                   class="campo__entrada" value="{{ old('nombre', $persona->nombre) }}" @if ($errors->has('nombre')) aria-invalid="true" @endif>
                        </div>
                        <div class="campo">
                            <label for="persona-primer" class="campo__etiqueta">Primer apellido <x-obligatorio /></label>
                            <input id="persona-primer" name="primer_apellido" type="text" maxlength="45" required autocomplete="family-name" data-texto-seguro
                                   class="campo__entrada" value="{{ old('primer_apellido', $persona->primer_apellido) }}" @if ($errors->has('primer_apellido')) aria-invalid="true" @endif>
                        </div>
                        <div class="campo">
                            <label for="persona-segundo" class="campo__etiqueta">Segundo apellido <span class="campo__opcional">(opcional)</span></label>
                            <input id="persona-segundo" name="segundo_apellido" type="text" maxlength="45" autocomplete="off" data-texto-seguro
                                   class="campo__entrada" value="{{ old('segundo_apellido', $persona->segundo_apellido) }}">
                        </div>
                    </div>

                    <div class="formulario__rejilla">
                        <fieldset class="campo campo--mitad">
                            <legend class="campo__etiqueta">Género <x-obligatorio /></legend>
                            <div class="segmentado">
                                @foreach ($generos as $clave => $texto)
                                    <label class="segmentado__opcion">
                                        <input type="radio" name="genero" value="{{ $clave }}" required @checked($genero === $clave) data-genero>
                                        {{ $texto }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <div class="campo campo--mitad">
                            <label for="persona-curp" class="campo__etiqueta">CURP <span class="campo__opcional">(opcional)</span></label>
                            <input id="persona-curp" name="curp" type="text" maxlength="18" autocomplete="off" spellcheck="false"
                                   class="campo__entrada campo__entrada--numerica campo__entrada--mayusculas" value="{{ old('curp', $persona->curp) }}"
                                   pattern="[A-Za-z]{4}[0-9]{6}[HMhm][A-Za-z]{5}[A-Za-z0-9][0-9]" data-curp aria-describedby="persona-curp-aviso"
                                   data-mensaje-formato="La CURP tiene 18 caracteres: 4 letras, 6 números, H o M, 5 letras y 2 caracteres finales."
                                   @if ($errors->has('curp')) aria-invalid="true" @endif>
                            <p class="campo__retro" id="persona-curp-aviso" data-tipo="aviso" data-curp-aviso aria-live="polite" hidden></p>
                        </div>
                    </div>
                </x-seccion-formulario>

                {{-- 2. Pertenencia --}}
                <x-seccion-formulario id="seccion-pertenencia" numero="2" titulo="Pertenece a" descripcion="Para recibir un cargo debe pertenecer a una institución u organización.">
                    <fieldset class="campo">
                        <legend class="solo-lectores">¿A qué pertenece?</legend>
                        <div class="segmentado">
                            @foreach (['institucion' => 'Una institución', 'organizacion' => 'Una organización', 'ninguna' => 'Ninguna'] as $clave => $texto)
                                <label class="segmentado__opcion">
                                    <input type="radio" name="pertenencia" value="{{ $clave }}" required @checked($pertenencia === $clave) data-pertenencia-tipo>
                                    {{ $texto }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="campo" data-solo-pertenencia="institucion" @unless ($pertenencia === 'institucion') hidden @endunless>
                        <label for="persona-institucion" class="campo__etiqueta">Institución <x-obligatorio /></label>
                        <x-buscador-universidad :universidades="$instituciones" :elegida="old('institucion', $persona->institucion_id)"
                                                :desactivado="$pertenencia !== 'institucion'"
                                                nombre="institucion" id="persona-institucion" plural="Instituciones"
                                                vacio="Ninguna institución activa coincide." mensaje="Elige una institución de la lista." />
                    </div>

                    <div class="campo" data-solo-pertenencia="organizacion" @unless ($pertenencia === 'organizacion') hidden @endunless>
                        <label for="persona-organizacion" class="campo__etiqueta">Organización <x-obligatorio /></label>
                        <select id="persona-organizacion" name="organizacion" class="campo__entrada" required @disabled($pertenencia !== 'organizacion')
                                @if ($errors->has('organizacion')) aria-invalid="true" @endif>
                            <option value="">Elige la organización</option>
                            @foreach ($organizaciones as $organizacion)
                                <option value="{{ $organizacion->id }}" @selected((int) old('organizacion', $persona->organizacion_id) === $organizacion->id)>{{ $organizacion->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-seccion-formulario>

                {{-- 3. Grados académicos --}}
                <x-seccion-formulario id="seccion-grados" numero="3" titulo="Grados académicos" descripcion="Marca todos los que tiene. La abreviatura va antes de su nombre en los directorios." opcional>
                    <fieldset class="campo">
                        <legend class="solo-lectores">Grados académicos</legend>
                        <div class="fichas">
                            @foreach ($grados as $grado)
                                <label class="ficha-opcion">
                                    <input type="checkbox" name="grados[]" value="{{ $grado->id }}" @checked(in_array($grado->id, $tiene, true))>
                                    <span class="ficha-opcion__marca" aria-hidden="true"><x-icono nombre="palomita" /></span>
                                    {{ $grado->nombre }}
                                    <span class="ficha-opcion__detalle" data-abreviatura data-m="{{ $grado->abr_m }}" data-f="{{ $grado->abr_f }}">{{ $genero === 'F' ? $grado->abr_f : $grado->abr_m }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </x-seccion-formulario>

                {{-- 4. Contactos --}}
                <x-seccion-formulario id="seccion-contactos" numero="4" titulo="Contactos" descripcion="El correo y el teléfono son obligatorios y siempre se muestran en el sitio de ANFECA."
                                      data-contactos data-maximo="{{ \App\Http\Requests\Personas\PersonaRequest::MAXIMO_CONTACTOS }}">
                    <div class="tabla-contactos">
                        <div class="tabla-contactos__encabezado" aria-hidden="true">
                            <span>Tipo</span><span>Lada</span><span>Número o correo</span><span>Ext.</span><span>En el sitio</span><span></span>
                        </div>

                        {{-- Fijos: el correo y el teléfono (obligatorios y siempre visibles) --}}
                        <div class="fila-contacto fila-contacto--correo fila-contacto--fija" role="group" aria-label="Correo (obligatorio)">
                            <div class="fila-contacto__tipo fila-contacto__fijo" aria-hidden="true">
                                <x-icono nombre="correo" /> Correo <x-obligatorio />
                            </div>
                            <div class="campo fila-contacto__valor">
                                <label for="persona-correo" class="campo__etiqueta">Correo</label>
                                <input id="persona-correo" name="correo" type="email" maxlength="120" required autocomplete="email" inputmode="email"
                                       class="campo__entrada" value="{{ old('correo', $contactos['correo']?->valor) }}" placeholder="nombre@dominio.mx"
                                       data-mensaje-formato="Escribe un correo válido, por ejemplo nombre@dominio.mx."
                                       @if ($errors->has('correo')) aria-invalid="true" @endif>
                            </div>
                            <span class="interruptor interruptor--fijo"><x-icono nombre="candado" /> Siempre</span>
                        </div>

                        <div class="fila-contacto fila-contacto--fija" role="group" aria-label="Teléfono (obligatorio)">
                            <div class="fila-contacto__tipo fila-contacto__fijo" aria-hidden="true">
                                <x-icono nombre="telefono" /> Teléfono <x-obligatorio />
                            </div>
                            <div class="campo" data-solo-lada>
                                <label for="persona-telefono-lada" class="campo__etiqueta">Lada <span class="campo__opcional">(opcional)</span></label>
                                <input id="persona-telefono-lada" name="telefono[lada]" type="text" inputmode="numeric" maxlength="3" pattern="[0-9]{2,3}" autocomplete="off"
                                       class="campo__entrada campo__entrada--numerica" value="{{ old('telefono.lada', $telefono?->lada) }}" placeholder="55" data-solo-digitos
                                       data-mensaje-formato="La lada lleva 2 o 3 dígitos." @if ($errors->has('telefono.lada')) aria-invalid="true" @endif>
                            </div>
                            <div class="campo fila-contacto__valor">
                                <label for="persona-telefono" class="campo__etiqueta">Número</label>
                                <input id="persona-telefono" name="telefono[valor]" type="text" inputmode="numeric" maxlength="10" pattern="[0-9]{7,10}" required autocomplete="off"
                                       class="campo__entrada" value="{{ old('telefono.valor', $telefono?->valor) }}" data-solo-digitos
                                       data-mensaje-formato="El teléfono lleva de 7 a 10 dígitos (sin la lada)." @if ($errors->has('telefono.valor')) aria-invalid="true" @endif>
                            </div>
                            <div class="campo" data-solo-extension>
                                <label for="persona-telefono-extension" class="campo__etiqueta">Extensión <span class="campo__opcional">(opcional)</span></label>
                                <input id="persona-telefono-extension" name="telefono[extension]" type="text" inputmode="numeric" maxlength="5" pattern="[0-9]{1,5}" autocomplete="off"
                                       class="campo__entrada campo__entrada--numerica" value="{{ old('telefono.extension', $telefono?->extension) }}" data-solo-digitos
                                       data-mensaje-formato="La extensión lleva hasta 5 dígitos." @if ($errors->has('telefono.extension')) aria-invalid="true" @endif>
                            </div>
                            <span class="interruptor interruptor--fijo"><x-icono nombre="candado" /> Siempre</span>
                        </div>

                        {{-- Otros (opcionales): se agregan y se quitan --}}
                        <div data-contactos-lista>
                            @foreach ($filas as $i => $contacto)
                                @include('personas._fila-contacto', ['i' => $i, 'contacto' => $contacto])
                            @endforeach
                        </div>
                    </div>

                    <button type="button" class="boton boton--secundario tabla-contactos__agregar" data-contacto-agregar>
                        <x-icono nombre="mas" />
                        Agregar contacto
                    </button>

                    <template data-contacto-plantilla>
                        @include('personas._fila-contacto', ['i' => '__i__', 'contacto' => (object) ['tipo_id' => $tipoNuevo, 'lada' => '', 'valor' => '', 'extension' => '', 'visible' => true]])
                    </template>
                </x-seccion-formulario>

        <div class="tarjeta cierre-registro">
            <div class="cierre-registro__acciones">
                <a href="{{ route('personas.show', $persona->id) }}" class="boton boton--secundario">Cancelar</a>
                <button type="submit" class="boton boton--primario">
                    <x-icono nombre="correcto" />
                    Guardar cambios
                </button>
            </div>
        </div>
    </form>
@endsection

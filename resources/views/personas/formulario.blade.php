{{--
    Formulario de una persona, en pantalla propia, por secciones numeradas:
    1. Datos personales (la CURP avisa en amarillo si su H/M no coincide con el género).
    2. Pertenece a: una institución (buscador), una organización o ninguna.
    3. Cargos (solo al registrar): uno o varios, como los contactos; cada uno
       con su nivel, su ámbito y desde cuándo. Si el puesto no existe, se crea.
    4. Grados académicos: varios o ninguno.
    5. Contactos: un correo y un teléfono obligatorios, que siempre se muestran
       en el sitio; aparte, otros contactos opcionales (filas que se agregan y
       se quitan; según el tipo aparecen la lada y la extensión, y cada uno
       dice si es visible en el sitio).
    Al editar no hay sección de cargo (los cargos se llevan en la ficha).
    Al registrar, mientras se escribe el nombre o la CURP avisa si ya existe;
    si alguien solo tenía cuenta, llega con sus datos ($persona->id).
    Al final, Guardar y Cancelar. Comportamiento en persona-formulario.js.
--}}
@extends('layouts.admin')

@php
    // Al registrar siempre pertenece a algo (lo pide el cargo): no se ofrece "Ninguna"
    $pertenenciaActual = $persona->institucion_id ? 'institucion' : ($persona->organizacion_id ? 'organizacion' : ($esNueva ? 'institucion' : 'ninguna'));
    $opcionesPertenencia = ['institucion' => 'Una institución', 'organizacion' => 'Una organización'] + ($esNueva ? [] : ['ninguna' => 'Ninguna']);
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
    // Al registrar: el número de cada sección después de "Pertenece a" se recorre por el cargo
    $siguiente = $esNueva ? 4 : 3;
    $regresar = $esNueva ? route('personas.index') : route('personas.show', $persona->id);
    // Al registrar: sus cargos (los que se escribieron si hubo un error; si no, uno vacío)
    $hoy = now()->toDateString();
    $filaCargo = fn (array $c) => (object) ['nivel' => $c['nivel'] ?? null, 'cargo' => $c['cargo'] ?? null, 'zona' => $c['zona'] ?? null, 'coordinacion' => $c['coordinacion'] ?? null, 'fecha_inicio' => $c['fecha_inicio'] ?? $hoy];
    $filasCargo = collect(old('cargos') ?: [[]])->values()->map(fn ($c) => $filaCargo(is_array($c) ? $c : []));
@endphp

@section('titulo', $esNueva ? 'Nueva persona' : 'Editar '.$nombre)

@section('contenido')
    <a href="{{ $regresar }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        {{ $esNueva ? 'Personas' : $nombre }}
    </a>

    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">{{ $esNueva ? 'Nueva persona' : 'Editar persona' }}</h1>
            <p class="pagina__texto">
                @if ($esNueva && $persona->id)
                    {{ $nombre }} ya tiene cuenta en el sistema: revisa sus datos y registra su cargo.
                @endif
                Los campos con <span class="campo__obligatorio" aria-hidden="true">*</span> son obligatorios.
            </p>
        </div>
    </header>

    <form method="POST" action="{{ $esNueva ? route('personas.store') : route('personas.update', $persona->id) }}" class="formulario" novalidate data-validar data-persona>
        @csrf
        @if ($esNueva)
            @if ($persona->id)
                <input type="hidden" name="persona_existente" value="{{ $persona->id }}">
            @endif
        @else
            @method('PUT')
        @endif

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

                    @if ($esNueva && ! $persona->id)
                        {{-- Mientras se escribe: ¿ya está registrada? (se llena desde persona-formulario.js) --}}
                        <div class="coincidencias" data-coincidencias="{{ route('personas.coincidencias') }}" aria-live="polite" hidden>
                            <p class="coincidencias__titulo"><x-icono nombre="alerta" /> ¿Es alguna de estas personas? Ya están registradas.</p>
                            <ul class="coincidencias__lista" data-coincidencias-lista></ul>
                        </div>
                    @endif
                </x-seccion-formulario>

                {{-- 2. Pertenencia --}}
                <x-seccion-formulario id="seccion-pertenencia" numero="2" titulo="Pertenece a" descripcion="Para recibir un cargo debe pertenecer a una institución u organización.">
                    <fieldset class="campo">
                        <legend class="solo-lectores">¿A qué pertenece?</legend>
                        <div class="segmentado">
                            @foreach ($opcionesPertenencia as $clave => $texto)
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

                @if ($esNueva)
                    {{-- 3. Cargos (uno o varios) --}}
                    <x-seccion-formulario id="seccion-cargo" numero="3" titulo="Cargos" descripcion="Los cargos que ocupa en ANFECA. Si un puesto aún no existe, se crea al guardar."
                                          data-cargos data-maximo="{{ \App\Http\Requests\Personas\PersonaRequest::MAXIMO_CARGOS }}">
                        <div class="filas-cargo" data-cargos-lista>
                            @foreach ($filasCargo as $i => $fila)
                                @include('personas._fila-cargo', ['i' => $i, 'fila' => $fila])
                            @endforeach
                        </div>

                        <button type="button" class="boton boton--secundario tabla-contactos__agregar" data-cargo-agregar>
                            <x-icono nombre="mas" />
                            Agregar otro cargo
                        </button>

                        <template data-cargo-plantilla>
                            @include('personas._fila-cargo', ['i' => '__i__', 'fila' => $filaCargo([])])
                        </template>
                    </x-seccion-formulario>
                @endif

                {{-- Grados académicos --}}
                <x-seccion-formulario id="seccion-grados" :numero="$siguiente" titulo="Grados académicos" descripcion="Agrega los que tiene. La abreviatura va antes de su nombre en los directorios." opcional>
                    <div class="formulario__rejilla">
                        <div class="campo campo--mitad">
                            <label for="persona-grado" class="campo__etiqueta">Agregar un grado</label>
                            <select id="persona-grado" class="campo__entrada" data-grado-agregar>
                                <option value="">Elige el grado</option>
                                @foreach ($grados as $grado)
                                    <option value="{{ $grado->id }}" data-nombre="{{ $grado->nombre }}" data-m="{{ $grado->abr_m }}" data-f="{{ $grado->abr_f }}"
                                            @disabled(in_array($grado->id, $tiene, true))>{{ $grado->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Los que tiene (cada uno se quita con su ×) --}}
                    <ul class="grados-elegidos" aria-label="Grados que tiene" data-grados-lista>
                        @foreach ($grados->filter(fn ($g) => in_array($g->id, $tiene, true)) as $grado)
                            @include('personas._grado-elegido', ['id' => $grado->id, 'nombre' => $grado->nombre, 'abrM' => $grado->abr_m, 'abrF' => $grado->abr_f])
                        @endforeach
                    </ul>
                    <p class="grados-elegidos__vacio" data-grados-vacio @if (count($tiene) > 0) hidden @endif>Todavía no tiene grados.</p>

                    <template data-grado-plantilla>
                        @include('personas._grado-elegido', ['id' => '__id__', 'nombre' => '__nombre__', 'abrM' => '__m__', 'abrF' => '__f__'])
                    </template>
                </x-seccion-formulario>

                {{-- Contactos --}}
                <x-seccion-formulario id="seccion-contactos" :numero="$siguiente + 1" titulo="Contactos" descripcion="El correo y el teléfono son obligatorios y siempre se muestran en el sitio de ANFECA."
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
                <a href="{{ $regresar }}" class="boton boton--secundario">Cancelar</a>
                <button type="submit" class="boton boton--primario">
                    <x-icono nombre="correcto" />
                    {{ $esNueva ? 'Registrar persona' : 'Guardar cambios' }}
                </button>
            </div>
        </div>
    </form>
@endsection

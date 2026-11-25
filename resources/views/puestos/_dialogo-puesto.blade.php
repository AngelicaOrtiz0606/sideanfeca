{{--
    Ventana para registrar o editar un puesto. Primero el nivel, luego el cargo
    (solo los de ese nivel); según el nivel aparece lo que aplica (puesto-formulario.js):
    - Institucional: la institución (buscador) y si es su titular.
    - Regional: la zona y, si aplica, la coordinación.
    - Nacional: la coordinación o la organización (por ejemplo, CACECA), si aplica.
    Si el puesto ya tiene designaciones, el cargo y el ámbito quedan fijos.
    Si la institución ya tiene otro puesto titular, se puede pasar la titularidad a este.
--}}
@php
    $abrir = old('_dialogo') === 'puesto' && $errors->any() && ! $errors->has('motivo');
    $idEnEdicion = $abrir ? (int) old('_id') : 0;
    $cargoElegido = $abrir ? (int) old('cargo') : 0;
@endphp

<dialog id="dialogo-puesto" class="dialogo dialogo--mediano" aria-labelledby="dialogo-puesto-titulo" data-reiniciar
        @if ($abrir) data-abrir-al-cargar @endif>
    <form method="POST" action="{{ $idEnEdicion ? route('puestos.update', $idEnEdicion) : route('puestos.store') }}"
          class="formulario" novalidate data-validar data-puesto>
        @csrf
        <input type="hidden" name="_method" value="{{ $idEnEdicion ? 'PUT' : 'POST' }}">
        <input type="hidden" name="_dialogo" value="puesto">
        <input type="hidden" name="_id" value="{{ $idEnEdicion ?: '' }}">

        <div class="dialogo__encabezado">
            <h2 id="dialogo-puesto-titulo" class="dialogo__titulo" data-dialogo-titulo>{{ $idEnEdicion ? 'Editar puesto' : 'Nuevo puesto' }}</h2>
            <button type="button" class="boton-icono boton-icono--pequeno" data-cerrar-dialogo>
                <x-icono nombre="cerrar" />
                <span class="solo-lectores">Cerrar</span>
            </button>
        </div>

        <x-nota-obligatorios />

        @if ($abrir)
            <x-errores-formulario />
        @endif

        <p class="aviso aviso--atencion" data-ambito-nota hidden></p>

        {{-- Primero el nivel: la lista de cargos muestra solo los de ese nivel --}}
        <fieldset class="opciones-nivel" data-ambito data-puesto-niveles>
            <legend class="campo__etiqueta">Nivel <x-obligatorio /></legend>
            <div class="opciones-nivel__lista">
                @foreach ($niveles as $nivel)
                    <label class="opcion-nivel">
                        <input type="radio" name="_nivel" value="{{ $nivel }}" required
                               @checked($abrir && $cargos->firstWhere('id', $cargoElegido)?->nivel === $nivel)>
                        <span class="opcion-nivel__nombre">{{ $nivel }}</span>
                        <span class="opcion-nivel__ayuda">{{ ['Nacional' => 'Sin zona ni institución; puede llevar coordinación u organización.', 'Regional' => 'De una zona; puede llevar coordinación.', 'Institucional' => 'De una institución.'][$nivel] }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="campo" data-ambito>
            <label for="puesto-cargo" class="campo__etiqueta">Cargo <x-obligatorio /></label>
            <select id="puesto-cargo" name="cargo" class="campo__entrada" required data-puesto-cargo
                    @if ($abrir && $errors->has('cargo')) aria-invalid="true" @endif>
                <option value="">Elige el cargo</option>
                @foreach ($cargos->groupBy('nivel') as $nivel => $delNivel)
                    <optgroup label="{{ $nivel }}">
                        @foreach ($delNivel as $cargo)
                            {{-- Los inactivos no se ofrecen, pero se conservan para editar un puesto que ya los usa --}}
                            <option value="{{ $cargo->id }}" data-nivel="{{ $cargo->nivel }}" @selected($cargoElegido === $cargo->id) @unless ($cargo->activo) hidden @endunless>
                                {{ $cargo->nombre }}@unless ($cargo->activo) (inactivo)@endunless
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <p class="campo__ayuda" data-puesto-guia>Primero elige el nivel.</p>
        </div>

        {{-- Institucional --}}
        <div class="campo" data-ambito data-solo-nivel="Institucional" hidden>
            <label for="puesto-institucion" class="campo__etiqueta">Institución <x-obligatorio /></label>
            <x-buscador-universidad :universidades="$instituciones" :elegida="$abrir ? old('institucion') : null" :desactivado="true"
                                    nombre="institucion" id="puesto-institucion" plural="Instituciones"
                                    vacio="Ninguna institución activa coincide." mensaje="Elige una institución de la lista." />
        </div>

        <label class="opcion-en-linea opcion-en-linea--casilla" data-solo-nivel="Institucional" hidden>
            <input type="checkbox" name="titular" value="1" @checked($abrir && old('titular')) disabled>
            Es el titular de la institución <span class="campo__opcional">(aparece en el directorio de Instituciones)</span>
        </label>

        {{-- Si la institución ya tiene otro puesto titular, el servidor lo dice y aquí se confirma el cambio --}}
        @if ($abrir && ($errors->has('cambiar_titular') || old('cambiar_titular')))
            <div class="aviso aviso--atencion aviso-titular" data-solo-nivel="Institucional" hidden>
                <p>{{ $errors->first('cambiar_titular') ?: 'Este puesto pasa a ser el titular de la institución.' }}</p>
                <label class="opcion-en-linea opcion-en-linea--casilla">
                    <input type="checkbox" name="cambiar_titular" value="1" @checked(old('cambiar_titular')) disabled>
                    Sí, cambiar la titularidad a este puesto
                </label>
                <p class="campo__ayuda">Quien ocupa el puesto titular anterior conserva su cargo.</p>
            </div>
        @endif

        {{-- Regional --}}
        <div class="campo" data-ambito data-solo-nivel="Regional" hidden>
            <label for="puesto-zona" class="campo__etiqueta">Zona regional <x-obligatorio /></label>
            <select id="puesto-zona" name="zona" class="campo__entrada" required disabled
                    @if ($abrir && $errors->has('zona')) aria-invalid="true" @endif>
                <option value="">Elige la zona</option>
                @foreach ($zonas as $zona)
                    <option value="{{ $zona->id }}" @selected($abrir && (int) old('zona') === $zona->id)>Zona {{ $zona->numero }} · {{ $zona->nombre }}</option>
                @endforeach
            </select>
        </div>

        {{-- Regional y Nacional --}}
        <div class="campo" data-ambito data-solo-nivel="Regional Nacional" hidden>
            <label for="puesto-coordinacion" class="campo__etiqueta">Coordinación nacional <span class="campo__opcional">(opcional)</span></label>
            <select id="puesto-coordinacion" name="coordinacion" class="campo__entrada" disabled
                    @if ($abrir && $errors->has('coordinacion')) aria-invalid="true" @endif>
                <option value="">Sin coordinación</option>
                @foreach ($coordinaciones as $coordinacion)
                    <option value="{{ $coordinacion->id }}" @selected($abrir && (int) old('coordinacion') === $coordinacion->id) @unless ($coordinacion->activo) hidden @endunless>
                        {{ $coordinacion->nombre }}@unless ($coordinacion->activo) (inactiva)@endunless
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Nacional: de una organización (quien lo ocupe debe pertenecer a ella) --}}
        <div class="campo" data-ambito data-solo-nivel="Nacional" hidden>
            <label for="puesto-organizacion" class="campo__etiqueta">Organización <span class="campo__opcional">(opcional)</span></label>
            <select id="puesto-organizacion" name="organizacion" class="campo__entrada" disabled aria-describedby="puesto-organizacion-ayuda"
                    @if ($abrir && $errors->has('organizacion')) aria-invalid="true" @endif>
                <option value="">Sin organización</option>
                @foreach ($organizaciones as $organizacion)
                    <option value="{{ $organizacion->id }}" @selected($abrir && (int) old('organizacion') === $organizacion->id) @unless ($organizacion->activo) hidden @endunless>
                        {{ $organizacion->nombre }}@unless ($organizacion->activo) (inactiva)@endunless
                    </option>
                @endforeach
            </select>
            <p class="campo__ayuda" id="puesto-organizacion-ayuda">Por ejemplo, el Director General de CACECA. Solo personas de esa organización podrán ocuparlo. Lleva coordinación u organización, no las dos.</p>
        </div>

        <div class="dialogo__acciones">
            <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="correcto" />
                <span data-dialogo-boton>{{ $idEnEdicion ? 'Guardar cambios' : 'Registrar puesto' }}</span>
            </button>
        </div>
    </form>
</dialog>

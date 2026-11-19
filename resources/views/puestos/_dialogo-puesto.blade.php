{{--
    Ventana para registrar o editar un puesto. Primero el cargo; según su nivel
    aparece lo que aplica (puesto-formulario.js):
    - Institucional: la institución (buscador) y si es su titular.
    - Regional: la zona y, si aplica, la coordinación.
    - Nacional: la coordinación, si aplica.
    Si el puesto ya tiene designaciones, el cargo y el ámbito quedan fijos.
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
            <p class="campo__ayuda" data-puesto-guia>El nivel del cargo decide qué más se pide.</p>
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

        <div class="dialogo__acciones">
            <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="correcto" />
                <span data-dialogo-boton>{{ $idEnEdicion ? 'Guardar cambios' : 'Registrar puesto' }}</span>
            </button>
        </div>
    </form>
</dialog>

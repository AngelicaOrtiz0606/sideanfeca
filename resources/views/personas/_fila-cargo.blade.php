{{--
    Un cargo en Nueva persona: primero el nivel (la lista de cargos muestra
    solo los de ese nivel), luego el cargo, desde cuándo y, según el nivel,
    la zona y la coordinación. Se agregan y se quitan como los contactos.
    La plantilla usa el índice __i__, que persona-formulario.js cambia.
    Parámetros: $i (índice), $fila (nivel, cargo, zona, coordinacion, fecha_inicio),
    $cargos, $zonas, $coordinaciones.
--}}
@php
    $errorEn = fn (string $campo) => $errors->has("cargos.{$i}.{$campo}");
    $numero = is_int($i) ? $i + 1 : '';
    $nivelFila = $fila->nivel ?: $cargos->firstWhere('id', (int) $fila->cargo)?->nivel;
@endphp
<div class="fila-cargo" data-fila-cargo role="group" aria-label="Cargo {{ $numero }}">
    <div class="fila-cargo__cabecera">
        <span class="fila-cargo__titulo" data-fila-cargo-titulo>Cargo {{ $numero }}</span>
        <button type="button" class="accion accion--borrar fila-cargo__quitar" data-cargo-quitar>
            <x-icono nombre="papelera" />
            <span class="accion__texto">Quitar cargo {{ $numero }}</span>
        </button>
    </div>

    <fieldset class="campo">
        <legend class="campo__etiqueta">Nivel <x-obligatorio /></legend>
        <div class="segmentado">
            @foreach (['Nacional' => 'Nacional', 'Regional' => 'Regional', 'Institucional' => 'De una institución'] as $nivel => $texto)
                <label class="segmentado__opcion">
                    <input type="radio" name="cargos[{{ $i }}][nivel]" value="{{ $nivel }}" required @checked($nivelFila === $nivel) data-nivel-cargo>
                    {{ $texto }}
                </label>
            @endforeach
        </div>
    </fieldset>

    <div class="formulario__rejilla formulario__rejilla--cargo">
        <div class="campo campo--mitad">
            <label for="cargo-{{ $i }}-cargo" class="campo__etiqueta">Cargo <x-obligatorio /></label>
            <select id="cargo-{{ $i }}-cargo" name="cargos[{{ $i }}][cargo]" class="campo__entrada" required data-cargo-elegido
                    @if ($errorEn('cargo')) aria-invalid="true" @endif>
                <option value="" data-vacio>Primero elige el nivel</option>
                @foreach ($cargos as $cargo)
                    <option value="{{ $cargo->id }}" data-nivel="{{ $cargo->nivel }}" @selected((int) $fila->cargo === $cargo->id)>{{ $cargo->nombre === $cargo->nombre_f ? $cargo->nombre : $cargo->nombre.' / '.$cargo->nombre_f }}</option>
                @endforeach
            </select>
        </div>
        <div class="campo campo--mitad">
            <label for="cargo-{{ $i }}-desde" class="campo__etiqueta">Desde <x-obligatorio /></label>
            <input id="cargo-{{ $i }}-desde" name="cargos[{{ $i }}][fecha_inicio]" type="date" required class="campo__entrada"
                   min="1950-01-01" max="{{ now()->toDateString() }}" value="{{ $fila->fecha_inicio }}"
                   @if ($errorEn('fecha_inicio')) aria-invalid="true" @endif>
        </div>

        <div class="campo campo--mitad" data-solo-nivel="Regional" hidden>
            <label for="cargo-{{ $i }}-zona" class="campo__etiqueta">Zona regional <x-obligatorio /></label>
            <select id="cargo-{{ $i }}-zona" name="cargos[{{ $i }}][zona]" class="campo__entrada" required disabled
                    @if ($errorEn('zona')) aria-invalid="true" @endif>
                <option value="">Elige la zona</option>
                @foreach ($zonas as $zona)
                    <option value="{{ $zona->id }}" @selected((int) $fila->zona === $zona->id)>Zona {{ $zona->numero }} · {{ $zona->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="campo campo--mitad" data-solo-nivel="Nacional Regional" hidden>
            <label for="cargo-{{ $i }}-coordinacion" class="campo__etiqueta">Coordinación nacional <span class="campo__opcional">(opcional)</span></label>
            <select id="cargo-{{ $i }}-coordinacion" name="cargos[{{ $i }}][coordinacion]" class="campo__entrada" disabled
                    @if ($errorEn('coordinacion')) aria-invalid="true" @endif>
                <option value="">Ninguna</option>
                @foreach ($coordinaciones as $coordinacion)
                    <option value="{{ $coordinacion->id }}" @selected((int) $fila->coordinacion === $coordinacion->id)>{{ $coordinacion->nombre }}</option>
                @endforeach
            </select>
        </div>
        <p class="campo--completo campo__ayuda" data-solo-nivel="Institucional" hidden>
            Es un cargo de la institución a la que pertenece (sección 2).
        </p>
    </div>
</div>

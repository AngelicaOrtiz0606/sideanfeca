{{--
    Ventana para registrar o editar un cargo: nombre en masculino y femenino, y
    su nivel. Si el cargo ya tiene puestos (data-puestos del botón), el nivel
    queda fijo: las reglas de sus puestos dependen de él (cargo-nivel.js).
    Si el servidor rechaza los datos, se vuelve a abrir con lo que se escribió.
--}}
@php
    $abrir = old('_dialogo') === 'cargo' && $errors->any();
    $enEdicion = $abrir && old('_id') ? $cargos->firstWhere('carg_id_cargo', (int) old('_id')) : null;
    $nivelFijo = $enEdicion && $enEdicion->puestos > 0;
    $masculinos = $cargos->map(fn ($c) => [$c->carg_id_cargo, $c->carg_nombre_m, $c->carg_nombre_m])->values();
    $femeninos = $cargos->map(fn ($c) => [$c->carg_id_cargo, $c->carg_nombre_f, $c->carg_nombre_f])->values();
    $descripciones = [
        'Nacional' => 'Sin zona ni institución. Puede llevar coordinación.',
        'Regional' => 'Lleva zona regional. Puede llevar coordinación.',
        'Institucional' => 'De una institución.',
    ];
@endphp

<dialog id="dialogo-cargo" class="dialogo dialogo--mediano" aria-labelledby="dialogo-cargo-titulo" @if ($abrir) data-abrir-al-cargar @endif>
    <form method="POST" action="{{ $enEdicion ? route('cargos.update', $enEdicion->carg_id_cargo) : route('cargos.store') }}"
          class="formulario" novalidate data-validar>
        @csrf
        <input type="hidden" name="_method" value="{{ $enEdicion ? 'PUT' : 'POST' }}">
        <input type="hidden" name="_dialogo" value="cargo">
        <input type="hidden" name="_id" value="{{ $enEdicion?->carg_id_cargo }}">

        <div class="dialogo__encabezado">
            <h2 id="dialogo-cargo-titulo" class="dialogo__titulo" data-dialogo-titulo>{{ $enEdicion ? 'Editar cargo' : 'Nuevo cargo' }}</h2>
            <button type="button" class="boton-icono boton-icono--pequeno" data-cerrar-dialogo>
                <x-icono nombre="cerrar" />
                <span class="solo-lectores">Cerrar</span>
            </button>
        </div>

        <x-nota-obligatorios />

        @if ($abrir)
            <x-errores-formulario />
        @endif

        <div class="formulario__fila formulario__fila--pares">
            <div class="campo">
                <label for="cargo-masculino" class="campo__etiqueta">Nombre en masculino <x-obligatorio /></label>
                <input id="cargo-masculino" data-texto-seguro name="masculino" type="text" maxlength="100" required autocomplete="off" placeholder="Ej. Presidente"
                       class="campo__entrada" value="{{ old('masculino') }}" @if ($abrir && $errors->has('masculino')) aria-invalid="true" @endif
                       data-existentes="{{ json_encode($masculinos) }}" data-mensaje-repetido="Ya existe el cargo «:existente».">
            </div>
            <div class="campo">
                <label for="cargo-femenino" class="campo__etiqueta">Nombre en femenino <x-obligatorio /></label>
                <input id="cargo-femenino" data-texto-seguro name="femenino" type="text" maxlength="100" required autocomplete="off" placeholder="Ej. Presidenta"
                       class="campo__entrada" value="{{ old('femenino') }}" @if ($abrir && $errors->has('femenino')) aria-invalid="true" @endif
                       data-existentes="{{ json_encode($femeninos) }}" data-mensaje-repetido="Ya existe el cargo «:existente».">
            </div>
        </div>

        <fieldset class="opciones-nivel" data-nivel-cargo>
            <legend class="campo__etiqueta">Nivel <x-obligatorio /></legend>
            <p class="campo__ayuda" data-nivel-fijo @unless ($nivelFijo) hidden @endunless>No se puede cambiar porque el cargo ya tiene puestos.</p>
            {{-- Si el nivel está fijo, este campo oculto lo envía (los botones desactivados no se envían) --}}
            <input type="hidden" name="nivel" value="{{ $nivelFijo ? $enEdicion->carg_nivel : '' }}" data-nivel-oculto @disabled(! $nivelFijo)>

            <div class="opciones-nivel__lista">
                @foreach ($niveles as $nivel)
                    <label class="opcion-nivel">
                        <input type="radio" name="nivel" value="{{ $nivel }}" required
                               @checked(old('nivel') === $nivel) @disabled($nivelFijo)>
                        <span class="opcion-nivel__nombre">{{ $nivel }}</span>
                        <span class="opcion-nivel__ayuda">{{ $descripciones[$nivel] }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="dialogo__acciones">
            <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="correcto" />
                <span data-dialogo-boton>{{ $enEdicion ? 'Guardar cambios' : 'Registrar cargo' }}</span>
            </button>
        </div>
    </form>
</dialog>

{{--
    Ventana para registrar o editar una organización: nombre, sitio web y
    domicilio (con el código postal se llenan la entidad, el municipio y las
    colonias). Si el servidor rechaza los datos, se vuelve a abrir con lo que
    se escribió.
--}}
@php
    $abrir = old('_dialogo') === 'organizacion' && $errors->any() && ! $errors->has('motivo');
    $idEnEdicion = $abrir ? (int) old('_id') : 0;
    $nombresExistentes = $organizaciones->map(fn ($o) => [$o->id, $o->nombre, $o->nombre])->values();
@endphp

<dialog id="dialogo-organizacion" class="dialogo dialogo--amplio" aria-labelledby="dialogo-organizacion-titulo" data-reiniciar
        @if ($abrir) data-abrir-al-cargar @endif>
    <form method="POST" action="{{ $idEnEdicion ? route('organizaciones.update', $idEnEdicion) : route('organizaciones.store') }}"
          class="formulario" novalidate data-validar>
        @csrf
        <input type="hidden" name="_method" value="{{ $idEnEdicion ? 'PUT' : 'POST' }}">
        <input type="hidden" name="_dialogo" value="organizacion">
        <input type="hidden" name="_id" value="{{ $idEnEdicion ?: '' }}">

        <div class="dialogo__encabezado">
            <h2 id="dialogo-organizacion-titulo" class="dialogo__titulo" data-dialogo-titulo>{{ $idEnEdicion ? 'Editar organización' : 'Nueva organización' }}</h2>
            <button type="button" class="boton-icono boton-icono--pequeno" data-cerrar-dialogo>
                <x-icono nombre="cerrar" />
                <span class="solo-lectores">Cerrar</span>
            </button>
        </div>

        <x-nota-obligatorios />

        @if ($abrir)
            <x-errores-formulario />
        @endif

        <div class="formulario__rejilla">
            <div class="campo campo--mitad">
                <label for="organizacion-nombre" class="campo__etiqueta">Nombre <x-obligatorio /></label>
                <input id="organizacion-nombre" name="nombre" type="text" maxlength="150" required autocomplete="organization" data-texto-seguro
                       placeholder="Ej. CACECA" class="campo__entrada" value="{{ old('nombre') }}"
                       data-existentes="{{ json_encode($nombresExistentes) }}" data-mensaje-repetido="Ya existe la organización «:existente»."
                       @if ($abrir && $errors->has('nombre')) aria-invalid="true" @endif>
            </div>

            <div class="campo campo--mitad">
                <label for="organizacion-web" class="campo__etiqueta">Sitio web <span class="campo__opcional">(opcional)</span></label>
                <input id="organizacion-web" name="sitio_web" type="url" maxlength="200" inputmode="url" autocomplete="url"
                       placeholder="https://www.ejemplo.org" class="campo__entrada" value="{{ old('sitio_web') }}"
                       data-mensaje-formato="Escribe la dirección completa, por ejemplo https://www.caceca.org."
                       @if ($abrir && $errors->has('sitio_web')) aria-invalid="true" @endif>
            </div>
        </div>

        <x-campos-domicilio en-ventana con-titulo />

        <div class="dialogo__acciones">
            <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="correcto" />
                <span data-dialogo-boton>{{ $idEnEdicion ? 'Guardar cambios' : 'Registrar organización' }}</span>
            </button>
        </div>
    </form>
</dialog>

{{--
    Ventana para registrar o editar una coordinación: nombre y descripción.
    La preparan los botones "Nueva coordinación" y "Editar". Si el servidor
    rechaza los datos, se vuelve a abrir con lo que se escribió.
    Parámetro: $coordinaciones (todas: para avisar si el nombre ya existe mientras se escribe).
--}}
@php
    $abrir = old('_dialogo') === 'coordinacion' && $errors->any();
    $idEnEdicion = $abrir ? (int) old('_id') : 0;
    $nombresExistentes = $coordinaciones->map(fn ($c) => [$c->cona_id_coordinacion_nacional, $c->cona_nombre, $c->cona_nombre])->values();
@endphp

<dialog id="dialogo-coordinacion" class="dialogo" aria-labelledby="dialogo-coordinacion-titulo" @if ($abrir) data-abrir-al-cargar @endif>
    <form method="POST" action="{{ $idEnEdicion ? route('coordinaciones.update', $idEnEdicion) : route('coordinaciones.store') }}"
          class="formulario" novalidate data-validar>
        @csrf
        <input type="hidden" name="_method" value="{{ $idEnEdicion ? 'PUT' : 'POST' }}">
        <input type="hidden" name="_dialogo" value="coordinacion">
        <input type="hidden" name="_id" value="{{ $idEnEdicion ?: '' }}">

        <div class="dialogo__encabezado">
            <h2 id="dialogo-coordinacion-titulo" class="dialogo__titulo" data-dialogo-titulo>{{ $idEnEdicion ? 'Editar coordinación' : 'Nueva coordinación' }}</h2>
            <button type="button" class="boton-icono boton-icono--pequeno" data-cerrar-dialogo>
                <x-icono nombre="cerrar" />
                <span class="solo-lectores">Cerrar</span>
            </button>
        </div>

        <p class="dialogo__texto" data-dialogo-texto>{{ $idEnEdicion ? '' : 'Quedará al final del orden.' }}</p>

        <x-nota-obligatorios />

        @if ($abrir)
            <x-errores-formulario />
        @endif

        <div class="campo">
            <label for="coordinacion-nombre" class="campo__etiqueta">Nombre <x-obligatorio /></label>
            <input id="coordinacion-nombre" name="nombre" type="text" maxlength="70" required autocomplete="off"
                   class="campo__entrada" value="{{ old('nombre') }}" @if ($abrir && $errors->has('nombre')) aria-invalid="true" @endif
                   data-existentes="{{ json_encode($nombresExistentes) }}" data-mensaje-repetido="Ya existe la coordinación «:existente».">
        </div>

        <div class="campo">
            <label for="coordinacion-descripcion" class="campo__etiqueta">Descripción <span class="campo__opcional">(opcional)</span></label>
            <input id="coordinacion-descripcion" name="descripcion" type="text" maxlength="100" autocomplete="off"
                   class="campo__entrada" value="{{ old('descripcion') }}" @if ($abrir && $errors->has('descripcion')) aria-invalid="true" @endif>
        </div>

        <div class="dialogo__acciones">
            <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="correcto" />
                <span data-dialogo-boton>{{ $idEnEdicion ? 'Guardar cambios' : 'Registrar coordinación' }}</span>
            </button>
        </div>
    </form>
</dialog>

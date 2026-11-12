{{--
    Ventana para registrar o editar un grado académico: nombre y abreviaturas.
    Si el servidor rechaza los datos, se vuelve a abrir con lo que se escribió.
--}}
@php
    $abrir = old('_dialogo') === 'grado' && $errors->any();
    $idEnEdicion = $abrir ? (int) old('_id') : 0;
    $nombresExistentes = $grados->map(fn ($g) => [$g->niac_id_nivel_academico, $g->niac_nombre, $g->niac_nombre])->values();
@endphp

<dialog id="dialogo-grado" class="dialogo" aria-labelledby="dialogo-grado-titulo" @if ($abrir) data-abrir-al-cargar @endif>
    <form method="POST" action="{{ $idEnEdicion ? route('grados.update', $idEnEdicion) : route('grados.store') }}"
          class="formulario" novalidate data-validar>
        @csrf
        <input type="hidden" name="_method" value="{{ $idEnEdicion ? 'PUT' : 'POST' }}">
        <input type="hidden" name="_dialogo" value="grado">
        <input type="hidden" name="_id" value="{{ $idEnEdicion ?: '' }}">

        <div class="dialogo__encabezado">
            <h2 id="dialogo-grado-titulo" class="dialogo__titulo" data-dialogo-titulo>{{ $idEnEdicion ? 'Editar grado académico' : 'Nuevo grado académico' }}</h2>
            <button type="button" class="boton-icono boton-icono--pequeno" data-cerrar-dialogo>
                <x-icono nombre="cerrar" />
                <span class="solo-lectores">Cerrar</span>
            </button>
        </div>

        <x-nota-obligatorios />

        @if ($abrir)
            <x-errores-formulario />
        @endif

        <div class="campo">
            <label for="grado-nombre" class="campo__etiqueta">Nombre <x-obligatorio /></label>
            <input id="grado-nombre" data-texto-seguro name="nombre" type="text" maxlength="50" required autocomplete="off" placeholder="Ej. Doctorado"
                   class="campo__entrada" value="{{ old('nombre') }}" @if ($abrir && $errors->has('nombre')) aria-invalid="true" @endif
                   data-existentes="{{ json_encode($nombresExistentes) }}" data-mensaje-repetido="Ya existe el grado «:existente».">
        </div>

        <div class="formulario__fila formulario__fila--pares">
            <div class="campo">
                <label for="grado-masculina" class="campo__etiqueta">Abreviatura en masculino <x-obligatorio /></label>
                <input id="grado-masculina" data-texto-seguro name="masculina" type="text" maxlength="10" required autocomplete="off" placeholder="Ej. Dr."
                       class="campo__entrada" value="{{ old('masculina') }}" @if ($abrir && $errors->has('masculina')) aria-invalid="true" @endif>
            </div>
            <div class="campo">
                <label for="grado-femenina" class="campo__etiqueta">Abreviatura en femenino <x-obligatorio /></label>
                <input id="grado-femenina" data-texto-seguro name="femenina" type="text" maxlength="10" required autocomplete="off" placeholder="Ej. Dra."
                       class="campo__entrada" value="{{ old('femenina') }}" @if ($abrir && $errors->has('femenina')) aria-invalid="true" @endif>
            </div>
        </div>

        <div class="dialogo__acciones">
            <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="correcto" />
                <span data-dialogo-boton>{{ $idEnEdicion ? 'Guardar cambios' : 'Registrar grado' }}</span>
            </button>
        </div>
    </form>
</dialog>

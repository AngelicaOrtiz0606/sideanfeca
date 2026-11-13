{{--
    Desactivar ("Deja de participar el…") o activar ("Vuelve a participar desde…")
    una institución. La fecha cierra o abre su periodo de participación, para
    que el historial y el filtro por año queden exactos.
    La preparan los botones Desactivar y Activar de cada fila (dialogos.js).
    Parámetro: $instituciones (para reabrir tras un error con la fecha).
--}}
@php
    $abrir = $errors->has('fecha') && old('_id');
    $enError = $abrir ? $instituciones->firstWhere('id', (int) old('_id')) : null;
    $activar = old('activa') === '1';
@endphp

<dialog id="dialogo-estado-institucion" class="dialogo" aria-labelledby="dialogo-estado-institucion-titulo" @if ($abrir) data-abrir-al-cargar @endif>
    <form method="POST" action="{{ $enError ? route('instituciones.estado', $enError->id) : '' }}" class="formulario" novalidate data-validar>
        @csrf
        @method('PATCH')
        <input type="hidden" name="activa" value="{{ $abrir ? old('activa') : '0' }}">
        <input type="hidden" name="_id" value="{{ old('_id') }}">

        <h2 id="dialogo-estado-institucion-titulo" class="dialogo__titulo" data-dialogo-titulo>
            {{ $enError ? ($activar ? '¿Activar ' : '¿Desactivar ').$enError->nombre.'?' : '¿Desactivar?' }}
        </h2>
        <p class="dialogo__texto" data-dialogo-texto>
            {{ $activar ? 'Se abre un periodo nuevo de participación desde esa fecha.' : 'No se borra: conserva su número de afiliación, su historial y sus personas. Su periodo de participación se cierra en esa fecha.' }}
        </p>

        @if ($abrir)
            <x-errores-formulario />
        @endif

        <div class="campo">
            <label for="fecha-estado" class="campo__etiqueta"><span data-dialogo-etiqueta>{{ $activar ? 'Vuelve a participar desde' : 'Deja de participar el' }}</span> <x-obligatorio /></label>
            <input id="fecha-estado" name="fecha" type="date" required max="{{ now()->toDateString() }}"
                   class="campo__entrada" value="{{ old('fecha', now()->toDateString()) }}" @if ($abrir) aria-invalid="true" @endif>
        </div>

        <div class="dialogo__acciones">
            <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="correcto" />
                <span data-dialogo-boton>{{ $activar ? 'Activar' : 'Desactivar' }}</span>
            </button>
        </div>
    </form>
</dialog>

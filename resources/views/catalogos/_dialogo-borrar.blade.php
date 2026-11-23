{{--
    Ventana para borrar un registro de catálogo (la abre el botón Borrar de cada fila).
    - Si se puede borrar: pide el motivo (la bitácora lo exige).
    - Si no se puede: explica por qué (data-bloqueo del botón) y no deja borrar.
    Parámetros: $motivos, $accion (dirección para borrar, si el servidor la reabre
    tras un error), $titulo, $abrir (true si hubo error al borrar) y, si hay dos
    en la misma página, $id (por omisión "dialogo-borrar").
--}}
@php
    $id ??= 'dialogo-borrar';
@endphp
<dialog id="{{ $id }}" class="dialogo" aria-labelledby="{{ $id }}-titulo" @if ($abrir) data-abrir-al-cargar @endif>
    <div data-si-bloqueo hidden>
        <h2 class="dialogo__titulo">No se puede borrar</h2>
        <p class="dialogo__texto dialogo__texto--separado" data-texto-bloqueo></p>
        <div class="dialogo__acciones">
            <button type="button" class="boton boton--primario" data-cerrar-dialogo>Entendido</button>
        </div>
    </div>

    <form method="POST" action="{{ $accion }}" class="formulario" novalidate data-validar data-sin-bloqueo>
        @csrf
        @method('DELETE')
        <input type="hidden" name="_id" value="{{ old('_id') }}">

        <h2 id="{{ $id }}-titulo" class="dialogo__titulo" data-dialogo-titulo>{{ $titulo }}</h2>
        {{-- El botón Borrar puede cambiar este texto con data-texto (por ejemplo, si también se borran sus puestos vacíos) --}}
        <p class="dialogo__texto" data-dialogo-texto>No se puede deshacer. Queda en la bitácora con su motivo.</p>

        @if ($abrir)
            <x-errores-formulario />
        @endif

        <div class="campo">
            <label for="{{ $id }}-motivo" class="campo__etiqueta">Motivo <x-obligatorio /></label>
            <select id="{{ $id }}-motivo" name="motivo" class="campo__entrada" required @if ($abrir && $errors->has('motivo')) aria-invalid="true" @endif>
                <option value="">Elige un motivo</option>
                @foreach ($motivos as $motivo)
                    <option value="{{ $motivo }}" @selected(old('motivo') === $motivo)>{{ $motivo }}</option>
                @endforeach
            </select>
        </div>

        <div class="dialogo__acciones">
            <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton boton--peligro">
                <x-icono nombre="papelera" />
                Borrar
            </button>
        </div>
    </form>
</dialog>

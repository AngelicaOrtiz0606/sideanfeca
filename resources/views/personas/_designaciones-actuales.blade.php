{{--
    Al editar a una persona: sus designaciones guardadas, como tabla.
    - Desde y Hasta se corrigen aquí (por ejemplo, un error de captura).
      Hasta vacío = sigue en el puesto; si ya tenía fecha de fin, se corrige
      pero no se quita.
    - La papelera marca la designación para borrarla (solo si se capturó por
      error): pide el motivo y se puede deshacer antes de guardar.
    Al guardar, el servidor usa las funciones de la base para terminar y
    borrar (desactivar_designacion y eliminar_designacion_erronea).
    Parámetros: $designaciones y $motivos.
--}}
<div class="tabla-designaciones" role="group" aria-label="Designaciones guardadas">
    <div class="tabla-designaciones__encabezado" aria-hidden="true">
        <span>Puesto</span><span>Desde</span><span>Hasta</span><span></span>
    </div>

    @foreach ($designaciones as $designacion)
        @php
            $base = "existentes.{$designacion->id}";
            $borrar = (bool) old("{$base}.borrar", false);
            $nombre = $designacion->nombre;
        @endphp
        <div @class(['designacion-actual', 'designacion-actual--borrar' => $borrar]) data-designacion-actual role="group" aria-label="{{ $nombre }}">
            <div class="designacion-actual__puesto">{{ $nombre }}</div>

            <div class="campo">
                <label for="existente-{{ $designacion->id }}-desde" class="campo__etiqueta">Desde <x-obligatorio /></label>
                <input id="existente-{{ $designacion->id }}-desde" name="existentes[{{ $designacion->id }}][desde]" type="date" class="campo__entrada"
                       min="1950-01-01" max="{{ now()->toDateString() }}" required value="{{ old("{$base}.desde", $designacion->desde) }}" data-designacion-fecha
                       @if ($errors->has("{$base}.desde")) aria-invalid="true" @endif>
            </div>

            <div class="campo">
                <label for="existente-{{ $designacion->id }}-hasta" class="campo__etiqueta">Hasta @unless ($designacion->hasta) <span class="campo__opcional">(vacío: sigue)</span> @endunless</label>
                <input id="existente-{{ $designacion->id }}-hasta" name="existentes[{{ $designacion->id }}][hasta]" type="date" class="campo__entrada"
                       min="1950-01-01" value="{{ old("{$base}.hasta", $designacion->hasta) }}" @required($designacion->hasta) data-designacion-fecha
                       @if ($errors->has("{$base}.hasta")) aria-invalid="true" @endif>
            </div>

            <input type="hidden" name="existentes[{{ $designacion->id }}][borrar]" value="{{ $borrar ? '1' : '0' }}" data-designacion-borrar>
            <button type="button" class="accion accion--borrar accion--a-la-derecha designacion-actual__papelera" aria-pressed="{{ $borrar ? 'true' : 'false' }}" data-designacion-papelera>
                <span data-si-no-borrar @if ($borrar) hidden @endif><x-icono nombre="papelera" /></span>
                <span data-si-borrar @unless ($borrar) hidden @endunless><x-icono nombre="restaurar" /></span>
                <span class="accion__texto" data-designacion-papelera-texto>{{ $borrar ? 'Deshacer: no borrar' : 'Borrar' }} <span class="solo-lectores">{{ $nombre }}</span></span>
            </button>

            <div class="designacion-actual__motivo" data-designacion-motivo @unless ($borrar) hidden @endunless>
                <p class="designacion-actual__aviso">Se borrará al guardar (solo si se capturó por error).</p>
                <div class="campo">
                    <label for="existente-{{ $designacion->id }}-motivo" class="campo__etiqueta">Motivo <x-obligatorio /></label>
                    <select id="existente-{{ $designacion->id }}-motivo" name="existentes[{{ $designacion->id }}][motivo]" class="campo__entrada" required @disabled(! $borrar)
                            @if ($errors->has("{$base}.motivo")) aria-invalid="true" @endif>
                        <option value="">Elige el motivo</option>
                        @foreach ($motivos as $motivo)
                            <option value="{{ $motivo }}" @selected(old("{$base}.motivo") === $motivo)>{{ $motivo }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    @endforeach
</div>

<p class="tabla-designaciones__nuevas">Para agregar otra, usa «Agregar designación».</p>

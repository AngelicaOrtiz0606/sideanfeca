{{--
    Una fila de contacto del formulario de persona. En computadora las
    etiquetas van en el encabezado de la tabla (en cada fila solo las leen los
    lectores de pantalla); en teléfono se ven en cada fila.
    La plantilla (para "Agregar contacto") usa el índice __i__, que persona-formulario.js cambia.
    Parámetros: $i (índice), $contacto (tipo_id, lada, valor, extension, visible), $tiposContacto.
--}}
@php
    $errorEn = fn (string $campo) => $errors->has("contactos.{$i}.{$campo}");
    $numero = is_int($i) ? $i + 1 : '';
@endphp
<div class="fila-contacto" data-contacto role="group" aria-label="Contacto {{ $numero }}">
    <div class="campo fila-contacto__tipo">
        <label for="contacto-{{ $i }}-tipo" class="campo__etiqueta">Tipo</label>
        <select id="contacto-{{ $i }}-tipo" name="contactos[{{ $i }}][tipo]" class="campo__entrada" required data-contacto-tipo
                @if ($errorEn('tipo')) aria-invalid="true" @endif>
            @foreach ($tiposContacto as $tipo)
                <option value="{{ $tipo->id }}" data-lada="{{ $tipo->lada ? 'si' : 'no' }}" data-extension="{{ $tipo->extension ? 'si' : 'no' }}"
                        data-correo="{{ $tipo->nombre === 'Correo' ? 'si' : 'no' }}" @selected((int) $contacto->tipo_id === $tipo->id)>{{ $tipo->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="campo" data-solo-lada>
        <label for="contacto-{{ $i }}-lada" class="campo__etiqueta">Lada <span class="campo__opcional">(opcional)</span></label>
        <input id="contacto-{{ $i }}-lada" name="contactos[{{ $i }}][lada]" type="text" inputmode="numeric" maxlength="3" pattern="[0-9]{2,3}" autocomplete="off"
               class="campo__entrada campo__entrada--numerica" value="{{ $contacto->lada }}" placeholder="55" data-mensaje-formato="La lada lleva 2 o 3 dígitos."
               @if ($errorEn('lada')) aria-invalid="true" @endif>
    </div>
    <div class="campo fila-contacto__valor">
        <label for="contacto-{{ $i }}-valor" class="campo__etiqueta" data-contacto-etiqueta>Número</label>
        <input id="contacto-{{ $i }}-valor" name="contactos[{{ $i }}][valor]" type="text" maxlength="120" required autocomplete="off"
               class="campo__entrada" value="{{ $contacto->valor }}" data-contacto-valor
               @if ($errorEn('valor')) aria-invalid="true" @endif>
    </div>
    <div class="campo" data-solo-extension>
        <label for="contacto-{{ $i }}-extension" class="campo__etiqueta">Extensión <span class="campo__opcional">(opcional)</span></label>
        <input id="contacto-{{ $i }}-extension" name="contactos[{{ $i }}][extension]" type="text" inputmode="numeric" maxlength="5" pattern="[0-9]{1,5}" autocomplete="off"
               class="campo__entrada campo__entrada--numerica" value="{{ $contacto->extension }}" data-mensaje-formato="La extensión lleva hasta 5 dígitos."
               @if ($errorEn('extension')) aria-invalid="true" @endif>
    </div>
    <label class="interruptor">
        <input type="hidden" name="contactos[{{ $i }}][visible]" value="0">
        <input type="checkbox" name="contactos[{{ $i }}][visible]" value="1" role="switch" @checked($contacto->visible)>
        Visible
    </label>
    <button type="button" class="accion accion--borrar accion--a-la-derecha fila-contacto__quitar" data-contacto-quitar>
        <x-icono nombre="papelera" />
        <span class="accion__texto">Quitar contacto {{ $numero }}</span>
    </button>
</div>

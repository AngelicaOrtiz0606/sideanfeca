{{--
    Domicilio con el catálogo de SEPOMEX: al escribir los 5 números del código
    postal se llenan la entidad y el municipio, y aparece la lista de colonias.
    La zona se sugiere desde la entidad y se puede cambiar
    (resources/js/modulos/domicilio.js).

    Props:
      domicilio   datos actuales (DomicilioService::datos) o null
      zonas       zonas para elegir (null = sin campo de zona, por ejemplo organizaciones)
      zona        zona elegida
      obligatorio si el domicilio es obligatorio (una matriz no lo necesita)
      enVentana   dentro de una ventana: sin tarjeta propia (la ventana ya tiene título)
--}}
@props(['domicilio' => null, 'zonas' => null, 'zona' => null, 'obligatorio' => true, 'enVentana' => false])

@php
    $cp = old('codigo_postal', $domicilio?->codigo_postal);
    $coloniaElegida = (int) old('colonia', $domicilio?->colonia_id);
    $zonaElegida = (int) old('zona', $zona);
@endphp

<fieldset @class(['tarjeta seccion-formulario' => ! $enVentana, 'seccion-ventana' => $enVentana]) data-domicilio data-url-codigo-postal="{{ route('codigos-postales.show', ['codigo' => '00000']) }}"
          data-obligatorio="{{ $obligatorio ? 'si' : 'no' }}">
    <legend @class(['seccion-formulario__titulo' => ! $enVentana, 'solo-lectores' => $enVentana])>Domicilio <span class="seccion-formulario__nota" data-domicilio-opcional @if ($obligatorio) hidden @endif>(opcional para una matriz)</span></legend>

    <div class="formulario__rejilla">
        <div class="campo">
            <label for="codigo_postal" class="campo__etiqueta">Código postal <x-obligatorio /></label>
            <input id="codigo_postal" name="codigo_postal" type="text" inputmode="numeric" maxlength="5" pattern="[0-9]{5}" autocomplete="postal-code"
                   class="campo__entrada campo__entrada--numerica" value="{{ $cp }}" placeholder="Ej. 04510" data-codigo-postal
                   data-mensaje-formato="El código postal tiene 5 números." @required($obligatorio)>
            <p class="campo__ayuda">Llena la entidad, el municipio y las colonias.</p>
        </div>

        <div class="campo">
            <span class="campo__etiqueta">Entidad</span>
            <output class="campo__salida" data-entidad>{{ $domicilio?->entidad ?? '—' }}</output>
        </div>

        <div class="campo">
            <span class="campo__etiqueta">Municipio o alcaldía</span>
            <output class="campo__salida" data-municipio>{{ $domicilio?->municipio ?? '—' }}</output>
        </div>

        <div class="campo campo--mitad">
            <label for="colonia" class="campo__etiqueta">Colonia <x-obligatorio /></label>
            <select id="colonia" name="colonia" class="campo__entrada" data-colonias @required($obligatorio)
                    @if ($errors->has('colonia')) aria-invalid="true" @endif>
                @if ($domicilio)
                    <option value="{{ $domicilio->colonia_id }}" selected>{{ $domicilio->colonia }}</option>
                @else
                    <option value="">Primero escribe el código postal</option>
                @endif
            </select>
            <p class="campo__retro" data-tipo="error" data-retro-cp hidden></p>
        </div>

        @if ($zonas !== null)
            <div class="campo campo--mitad">
                <label for="zona" class="campo__etiqueta">Zona regional <x-obligatorio /></label>
                <select id="zona" name="zona" class="campo__entrada" data-zona @required($obligatorio)
                        @if ($errors->has('zona')) aria-invalid="true" @endif>
                    <option value="">Elige la zona</option>
                    @foreach ($zonas as $z)
                        <option value="{{ $z->id }}" data-numero="{{ $z->numero }}" @selected($zonaElegida === $z->id)>Zona {{ $z->numero }} · {{ $z->nombre }}</option>
                    @endforeach
                </select>
                <p class="campo__ayuda" data-zona-sugerida>Se sugiere sola según la entidad; la puedes cambiar.</p>
            </div>
        @endif

        <div class="campo campo--mitad">
            <label for="calle" class="campo__etiqueta">Calle <x-obligatorio /></label>
            <input id="calle" name="calle" type="text" maxlength="60" autocomplete="address-line1" data-texto-seguro
                   class="campo__entrada" value="{{ old('calle', $domicilio?->calle) }}" @required($obligatorio)
                   @if ($errors->has('calle')) aria-invalid="true" @endif>
        </div>

        <div class="campo campo--cuarto">
            <label for="num_ext" class="campo__etiqueta">Número exterior <x-obligatorio /></label>
            <input id="num_ext" name="num_ext" type="text" maxlength="10" data-texto-seguro
                   class="campo__entrada" value="{{ old('num_ext', $domicilio?->num_ext) }}" @required($obligatorio)
                   @if ($errors->has('num_ext')) aria-invalid="true" @endif>
        </div>

        <div class="campo campo--cuarto">
            <label for="num_int" class="campo__etiqueta">Número interior <span class="campo__opcional">(opcional)</span></label>
            <input id="num_int" name="num_int" type="text" maxlength="10" data-texto-seguro
                   class="campo__entrada" value="{{ old('num_int', $domicilio?->num_int) }}">
        </div>

    </div>
</fieldset>

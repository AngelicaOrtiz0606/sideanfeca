{{--
    Número de afiliación de una afiliada nueva (al registrar o al pasar a afiliada).

    Por omisión el sistema lo asigna al guardar: año (2 dígitos) + zona
    (2 dígitos) + consecutivo de ese año y zona (3 dígitos), por ejemplo
    2607003. Aquí solo se muestra la vista previa (numero-sugerido.js); el
    servidor vuelve a calcularlo con candado al guardar, así dos personas al
    mismo tiempo nunca reciben el mismo número.

    "Escribir el número" sirve para capturar números que ya existían (por
    ejemplo, afiliadas antiguas): solo se revisa que tenga 7 dígitos y no esté
    repetido; si no empieza con el año y la zona, se avisa en amarillo.

    Props:
      id          id del campo de texto
      existentes  [id, número, nombre] de los números ya usados
      fecha       selector del campo de la fecha (por ejemplo "#desde")
      zona        selector de la lista de zonas (cada opción con data-numero)
      zonaNumero  o el número de la zona fija
--}}
@props(['id', 'existentes', 'fecha', 'zona' => null, 'zonaNumero' => null])

@php
    $manual = old('numero_modo') === 'manual';
    $conError = $errors->has('numero');
@endphp

<div {{ $attributes->merge(['class' => 'campo']) }} data-numero-afiliacion
     data-fecha="{{ $fecha }}" @if ($zona) data-zona="{{ $zona }}" @endif @if ($zonaNumero) data-zona-numero="{{ $zonaNumero }}" @endif>
    <label for="{{ $id }}" class="campo__etiqueta">Número de afiliación <span data-solo-manual @unless ($manual) hidden @endunless><x-obligatorio /></span></label>
    <input type="hidden" name="numero_modo" value="{{ $manual ? 'manual' : 'automatico' }}" data-numero-modo>

    <div class="numero-automatico" data-numero-automatico @if ($manual) hidden @endif>
        <output class="numero-afiliacion numero-automatico__valor" data-numero-vista aria-live="polite">—</output>
        <p class="campo__ayuda" data-numero-ayuda>Se asigna al guardar, con el año y la zona regional.</p>
        <button type="button" class="boton-texto" data-numero-escribir>Escribir el número</button>
    </div>

    <div class="numero-manual" data-numero-manual @unless ($manual) hidden @endunless>
        <input id="{{ $id }}" name="numero" type="text" inputmode="numeric" maxlength="7" pattern="[0-9]{7}" required
               class="campo__entrada campo__entrada--numerica" value="{{ $manual ? old('numero') : '' }}"
               @unless ($manual) disabled @endunless data-control-propio
               data-existentes="{{ json_encode($existentes) }}"
               data-mensaje-repetido="El número ya pertenece a :existente."
               data-mensaje-formato="El número de afiliación tiene exactamente 7 dígitos."
               @if ($conError) aria-invalid="true" @endif>
        <p class="campo__retro" data-tipo="aviso" data-numero-aviso aria-live="polite" hidden></p>
        <button type="button" class="boton-texto" data-numero-usar-automatico>Usar el número automático</button>
    </div>
</div>

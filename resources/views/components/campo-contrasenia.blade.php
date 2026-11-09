{{--
    Campo de contraseña con botón "Mostrar / Ocultar" y aviso de mayúsculas activadas.

    Uso:
        <x-campo-contrasenia id="contrasenia" name="contrasenia" etiqueta="Contraseña"
                             autocomplete="current-password">
            <x-slot:enlace><a href="...">¿Olvidaste tu contraseña?</a></x-slot:enlace>
        </x-campo-contrasenia>
--}}
@props([
    'id',
    'name',
    'etiqueta',
    'autocomplete' => 'current-password',
    'ayuda' => null,
    'enlace' => null,
    // 'nueva': exige la regla del sistema (8 caracteres, letras y números) en el navegador
    'regla' => null,
    // id del campo que debe coincidir (confirmación de contraseña)
    'igualA' => null,
])

@php
    $describe = collect([
        $ayuda ? "{$id}-ayuda" : null,
        "{$id}-mayusculas",
        $errors->has($name) ? "{$id}-error" : null,
    ])->filter()->implode(' ');
@endphp

<div class="campo">
    <div class="campo__encabezado">
        <label for="{{ $id }}" class="campo__etiqueta">{{ $etiqueta }}</label>
        {{ $enlace }}
    </div>

    <div class="campo__con-boton">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="password"
            class="campo__entrada"
            autocomplete="{{ $autocomplete }}"
            maxlength="{{ $regla === 'nueva' ? 72 : 255 }}"
            required
            data-contrasenia
            data-aviso="{{ $id }}-mayusculas"
            @if ($regla) data-regla="{{ $regla }}" @endif
            @if ($igualA) data-igual-a="{{ $igualA }}" @endif
            aria-describedby="{{ $describe }}"
            @error($name) aria-invalid="true" @enderror
        >
        <button type="button" class="campo__boton" aria-controls="{{ $id }}" aria-pressed="false" data-mostrar-contrasenia>
            <x-icono nombre="ojo" data-icono-mostrar />
            <x-icono nombre="ojo-tachado" data-icono-ocultar hidden />
            <span data-texto-boton>Mostrar</span>
        </button>
    </div>

    @if ($ayuda)
        <p id="{{ $id }}-ayuda" class="campo__ayuda">{{ $ayuda }}</p>
    @endif

    <p id="{{ $id }}-mayusculas" class="campo__aviso" aria-live="polite"></p>

    @error($name)
        <p id="{{ $id }}-error" class="campo__error" role="alert">
            <x-icono nombre="alerta" />
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>

{{--
    Campo de correo electrónico. Mientras se escribe, se delinea en rojo si el
    formato no es válido (resources/js/app.js).

    Uso: <x-campo-correo autocomplete="username" autofocus />
--}}
@props([
    'id' => 'correo',
    'name' => 'correo',
    'etiqueta' => 'Correo electrónico',
    'valor' => null,
    'autocomplete' => 'email',
    'autofocus' => false,
])

@php
    $describe = $errors->has($name) ? "{$id}-error" : null;
@endphp

<div class="campo">
    <label for="{{ $id }}" class="campo__etiqueta">{{ $etiqueta }}</label>
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="email"
        value="{{ $valor ?? old($name) }}"
        class="campo__entrada"
        autocomplete="{{ $autocomplete }}"
        inputmode="email"
        autocapitalize="none"
        spellcheck="false"
        maxlength="254"
        {{-- Misma regla que la base de datos (CKC_USUA_CORREO): algo@algo.algo --}}
        pattern="[^@\s]+@[^@\s]+\.[^@\s]+"
        required
        @if ($autofocus) autofocus @endif
        @if ($describe) aria-describedby="{{ $describe }}" @endif
        @error($name) aria-invalid="true" @enderror
    >
    @error($name)
        <p id="{{ $id }}-error" class="campo__error" role="alert">
            <x-icono nombre="alerta" />
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>

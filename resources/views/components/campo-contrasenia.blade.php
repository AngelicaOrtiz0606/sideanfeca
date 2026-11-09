{{--
    Campo de contraseña con botón "Mostrar / Ocultar", aviso de mayúsculas
    activadas y retroalimentación mientras se escribe.

    Uso:
        <x-campo-contrasenia id="contrasenia" name="contrasenia" etiqueta="Contraseña"
                             autocomplete="current-password">
            <x-slot:enlace><a href="...">¿Olvidaste tu contraseña?</a></x-slot:enlace>
        </x-campo-contrasenia>

    regla="nueva" muestra la lista de requisitos (8 caracteres, mayúscula, número
    y carácter especial) y la va marcando. igual-a="otro_id" avisa si coincide
    con otro campo.
--}}
@props([
    'id',
    'name',
    'etiqueta',
    'autocomplete' => 'current-password',
    'enlace' => null,
    'regla' => null,
    'igualA' => null,
])

@php
    $requisitos = [
        'largo' => '8 caracteres o más',
        'mayuscula' => 'Una mayúscula',
        'numero' => 'Un número',
        'especial' => 'Un carácter especial',
    ];

    $describe = collect([
        $regla === 'nueva' ? "{$id}-requisitos" : null,
        $igualA ? "{$id}-retro" : null,
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

    @if ($regla === 'nueva')
        <div class="requisitos" id="{{ $id }}-requisitos">
            <p class="requisitos__titulo">Tu contraseña debe tener:</p>
            <ul class="requisitos__lista" data-requisitos="{{ $id }}">
            @foreach ($requisitos as $clave => $texto)
                <li class="requisitos__item" data-requisito="{{ $clave }}" data-cumple="false">
                    <x-icono nombre="circulo" data-icono-pendiente />
                    <x-icono nombre="correcto" data-icono-cumple />
                    <span>{{ $texto }}</span>
                    <span class="solo-lectores" data-estado-requisito>: pendiente</span>
                </li>
            @endforeach
            </ul>
        </div>
    @endif

    @if ($igualA)
        <p id="{{ $id }}-retro" class="campo__retro" aria-live="polite"></p>
    @endif
    <p id="{{ $id }}-mayusculas" class="campo__aviso" aria-live="polite"></p>

    @error($name)
        <p id="{{ $id }}-error" class="campo__error" role="alert">
            <x-icono nombre="alerta" />
            <span>{{ $message }}</span>
        </p>
    @enderror
</div>

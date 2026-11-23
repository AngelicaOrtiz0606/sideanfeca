{{--
    Nuevo usuario / Editar usuario (solo superadministrador). Más corto que el
    de Personas: 1. Persona (nombre, apellidos, género y CURP, obligatoria aquí
    y que nunca aparece en el sitio) y 2. Cuenta (correo, rol y contraseña).
    Al crear, mientras se escribe el nombre o la CURP avisa si la persona ya
    está registrada, para crearle la cuenta a ella en lugar de duplicarla.
--}}
@extends('layouts.admin')

@php
    $esNueva = $cuenta === null;
    $datos = $cuenta ?? $persona;
    $valor = fn (string $campo, string $columna) => old($campo, data_get($datos, $columna));
    $genero = old('genero', data_get($datos, 'genero'));
    $rolElegido = (int) old('rol', $cuenta?->rol_id ?? $roles->firstWhere('nombre', \App\Models\Rol::ADMINISTRADOR)?->id);
    $esPropia = ! $esNueva && $cuenta->id === auth()->id();
@endphp

@section('titulo', $esNueva ? 'Nuevo usuario' : 'Editar usuario')

@section('contenido')
    <a href="{{ route('usuarios.index') }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        Usuarios
    </a>

    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">{{ $esNueva ? 'Nuevo usuario' : 'Editar usuario' }}</h1>
            <p class="pagina__texto">
                @if ($persona)
                    {{ trim("{$persona->nombre} {$persona->primer_apellido} {$persona->segundo_apellido}") }} ya está registrada: revisa sus datos y crea su cuenta.
                @elseif (! $esNueva)
                    Cuenta de {{ $cuenta->nombre }}.
                @endif
                Los campos con <span class="campo__obligatorio" aria-hidden="true">*</span> son obligatorios.
            </p>
        </div>
    </header>

    <form method="POST" action="{{ $esNueva ? route('usuarios.store') : route('usuarios.update', $cuenta->id) }}" class="formulario" novalidate data-validar data-usuario>
        @csrf
        @unless ($esNueva)
            @method('PUT')
        @endunless
        @if ($persona)
            <input type="hidden" name="persona" value="{{ $persona->id }}">
        @endif

        <x-errores-formulario />

        {{-- 1. Persona --}}
        <x-seccion-formulario id="seccion-persona" numero="1" titulo="Persona" descripcion="Quién usa la cuenta. La CURP solo la ve el sistema: nunca aparece en el sitio ni en los directorios.">
            <div class="formulario__rejilla">
                <div class="campo">
                    <label for="usuario-nombre" class="campo__etiqueta">Nombre <x-obligatorio /></label>
                    <input id="usuario-nombre" name="nombre" type="text" maxlength="60" required autocomplete="off" data-texto-seguro
                           class="campo__entrada" value="{{ old('nombre', data_get($datos, $esNueva ? 'nombre' : 'nombre_pila')) }}" @if ($errors->has('nombre')) aria-invalid="true" @endif>
                </div>
                <div class="campo">
                    <label for="usuario-primer" class="campo__etiqueta">Primer apellido <x-obligatorio /></label>
                    <input id="usuario-primer" name="primer_apellido" type="text" maxlength="45" required autocomplete="off" data-texto-seguro
                           class="campo__entrada" value="{{ $valor('primer_apellido', 'primer_apellido') }}" @if ($errors->has('primer_apellido')) aria-invalid="true" @endif>
                </div>
                <div class="campo">
                    <label for="usuario-segundo" class="campo__etiqueta">Segundo apellido <span class="campo__opcional">(opcional)</span></label>
                    <input id="usuario-segundo" name="segundo_apellido" type="text" maxlength="45" autocomplete="off" data-texto-seguro
                           class="campo__entrada" value="{{ $valor('segundo_apellido', 'segundo_apellido') }}">
                </div>
            </div>

            <div class="formulario__rejilla">
                <fieldset class="campo campo--mitad">
                    <legend class="campo__etiqueta">Género <x-obligatorio /></legend>
                    <div class="segmentado">
                        @foreach (['F' => 'Femenino', 'M' => 'Masculino'] as $clave => $texto)
                            <label class="segmentado__opcion">
                                <input type="radio" name="genero" value="{{ $clave }}" required @checked($genero === $clave) data-genero>
                                {{ $texto }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="campo campo--mitad">
                    <label for="usuario-curp" class="campo__etiqueta">CURP <x-obligatorio /></label>
                    <input id="usuario-curp" name="curp" type="text" maxlength="18" required autocomplete="off" spellcheck="false"
                           class="campo__entrada campo__entrada--numerica campo__entrada--mayusculas" value="{{ $valor('curp', 'curp') }}"
                           pattern="[A-Za-z]{4}[0-9]{6}[HMhm][A-Za-z]{5}[A-Za-z0-9][0-9]" data-curp aria-describedby="usuario-curp-aviso"
                           data-mensaje-formato="La CURP tiene 18 caracteres: 4 letras, 6 números, H o M, 5 letras y 2 caracteres finales."
                           @if ($errors->has('curp')) aria-invalid="true" @endif>
                    <p class="campo__retro" id="usuario-curp-aviso" data-tipo="aviso" data-curp-aviso aria-live="polite" hidden></p>
                </div>
            </div>

            @if ($esNueva && ! $persona)
                {{-- Mientras se escribe: ¿ya está registrada? (se llena desde usuario-formulario.js) --}}
                <div class="coincidencias" data-coincidencias="{{ route('usuarios.coincidencias') }}" aria-live="polite" hidden>
                    <p class="coincidencias__titulo"><x-icono nombre="alerta" /> ¿Es alguna de estas personas? Ya están registradas.</p>
                    <ul class="coincidencias__lista" data-coincidencias-lista></ul>
                </div>
            @endif
        </x-seccion-formulario>

        {{-- 2. Cuenta --}}
        <x-seccion-formulario id="seccion-cuenta" numero="2" titulo="Cuenta"
                              :descripcion="$esNueva ? 'Con estos datos entra al sistema. Dale una contraseña temporal; al entrar la cambia en «Cambiar contraseña».' : 'Su contraseña no se ve ni se cambia aquí: solo su dueño la cambia en «Cambiar contraseña». Si la olvidó, la recupera con «¿Olvidaste tu contraseña?» al entrar.'">
            <div class="formulario__rejilla">
                <div class="campo campo--mitad">
                    <label for="usuario-correo" class="campo__etiqueta">Correo para entrar <x-obligatorio /></label>
                    <input id="usuario-correo" name="correo" type="email" maxlength="254" required autocomplete="off" spellcheck="false"
                           class="campo__entrada" value="{{ old('correo', $cuenta?->correo) }}" @if ($errors->has('correo')) aria-invalid="true" @endif>
                </div>
                <fieldset class="campo campo--mitad">
                    <legend class="campo__etiqueta">Rol <x-obligatorio /></legend>
                    <div class="segmentado">
                        @foreach ($roles as $rol)
                            <label class="segmentado__opcion">
                                <input type="radio" name="rol" value="{{ $rol->id }}" required @checked($rolElegido === $rol->id) @disabled($esPropia && $rolElegido !== $rol->id)>
                                {{ $rol->nombre }}
                            </label>
                        @endforeach
                    </div>
                    <p class="campo__ayuda">
                        @if ($esPropia)
                            No puedes cambiar tu propio rol.
                        @else
                            El administrador gestiona los datos del día a día. El superadministrador además ve la bitácora, las personas ocultas y las cuentas.
                        @endif
                    </p>
                </fieldset>
            </div>

            {{-- La contraseña solo se pone al crear la cuenta; después es solo de su dueño --}}
            @if ($esNueva)
                <div class="formulario__rejilla">
                    <div class="campo--mitad">
                        <x-campo-contrasenia id="contrasenia" name="contrasenia" etiqueta="Contraseña temporal"
                                             autocomplete="new-password" regla="nueva" :obligatorio="true" />
                    </div>
                    <div class="campo--mitad">
                        <x-campo-contrasenia id="contrasenia_confirmation" name="contrasenia_confirmation" etiqueta="Confirma la contraseña"
                                             autocomplete="new-password" igual-a="contrasenia" :obligatorio="true" />
                    </div>
                </div>
            @endif
        </x-seccion-formulario>

        <div class="tarjeta cierre-registro">
            <div class="cierre-registro__acciones">
                <a href="{{ route('usuarios.index') }}" class="boton boton--secundario">Cancelar</a>
                <button type="submit" class="boton boton--primario">
                    <x-icono nombre="correcto" />
                    {{ $esNueva ? 'Crear cuenta' : 'Guardar cambios' }}
                </button>
            </div>
        </div>
    </form>
@endsection

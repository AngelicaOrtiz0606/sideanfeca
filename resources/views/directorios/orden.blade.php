{{--
    Directorios de puestos (M7): se elige el directorio (Consejo Nacional
    Directivo, Consejos Regionales o Coordinaciones Nacionales), se agregan o
    quitan sus puestos y se cambia su orden (componente Vue OrdenPuestos).
    Cada puesto dice quién lo ocupa hoy, o "Vacante". El directorio de
    Instituciones no está aquí: se arma con las instituciones y sus titulares.
--}}
@extends('layouts.admin')

@section('titulo', 'Directorios')

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Directorios</h1>
            <p class="pagina__texto">Qué puestos aparecen en cada directorio y en qué orden. El de Instituciones se arma solo, con las instituciones y sus titulares.</p>
        </div>
    </header>

    <x-aviso-estado />

    <nav class="pestanas__lista" aria-label="Directorios">
        @foreach ($tipos as $tipo)
            <a href="{{ route('directorios.orden', ['directorio' => $tipo->id]) }}" class="pestanas__pestana"
               @if ($elegido?->id === $tipo->id) aria-current="page" @endif>
                {{ $tipo->nombre }}
            </a>
        @endforeach
    </nav>

    @if ($elegido)
        <section class="tarjeta orden-directorio" aria-labelledby="directorio-titulo">
            <div class="orden-directorio__encabezado">
                <div>
                    <h2 id="directorio-titulo" class="orden-directorio__titulo">{{ $elegido->nombre }}</h2>
                    <p class="orden-directorio__texto">
                        {{ $puestos->count() === 1 ? '1 puesto' : "{$puestos->count()} puestos" }}.
                        Arrastra cada puesto a su lugar o usa las flechas; luego guarda el orden.
                    </p>
                </div>

                @if ($disponibles->isNotEmpty())
                    <form method="POST" action="{{ route('directorios.agregar', $elegido->id) }}" class="orden-directorio__agregar" novalidate data-validar>
                        @csrf
                        <label for="agregar-puesto" class="solo-lectores">Puesto para agregar</label>
                        <select id="agregar-puesto" name="puesto" class="campo__entrada" required @if ($errors->has('puesto')) aria-invalid="true" @endif>
                            <option value="">Elige un puesto para agregar</option>
                            @foreach ($disponibles as $disponible)
                                <option value="{{ $disponible->id }}">{{ $disponible->nombre }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="boton boton--secundario">
                            <x-icono nombre="mas" />
                            Agregar
                        </button>
                    </form>
                @endif
            </div>

            <x-errores-formulario />

            @if ($puestos->isEmpty())
                <p class="vacio">Este directorio todavía no tiene puestos. Agrégalos con la lista de arriba.</p>
            @else
                <div data-vue="orden-puestos" data-props="{{ json_encode([
                    'puestos' => $puestos->values(),
                    'accion' => route('directorios.guardar-orden', $elegido->id),
                    'token' => csrf_token(),
                ], JSON_UNESCAPED_UNICODE) }}">
                    <p class="campo__ayuda">Cargando los puestos…</p>
                </div>

                {{-- Un formulario por puesto para quitarlo (lo envía su papelera, fuera del formulario del orden) --}}
                @foreach ($puestos as $puesto)
                    <form id="quitar-{{ $puesto->id }}" method="POST" action="{{ route('directorios.quitar', [$elegido->id, $puesto->id]) }}" hidden>
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach
            @endif
        </section>
    @endif
@endsection

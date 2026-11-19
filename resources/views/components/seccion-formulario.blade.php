{{--
    Una sección de un formulario largo: tarjeta con número, título (h2, para
    navegar con lectores de pantalla) y una línea que explica qué va ahí.

    Uso: <x-seccion-formulario id="seccion-datos" numero="1" titulo="Datos personales"
                               descripcion="Como aparecerá en los directorios.">…</x-seccion-formulario>
--}}
@props(['id', 'numero', 'titulo', 'descripcion' => null, 'opcional' => false])

<section {{ $attributes->merge(['class' => 'tarjeta bloque']) }} id="{{ $id }}" aria-labelledby="{{ $id }}-titulo" data-seccion>
    <header class="bloque__cabecera">
        <span class="bloque__numero" aria-hidden="true">{{ $numero }}</span>
        <div class="bloque__leyenda">
            <h2 id="{{ $id }}-titulo" class="bloque__nombre">
                {{ $titulo }}
                @if ($opcional)
                    <span class="campo__opcional">(opcional)</span>
                @endif
            </h2>
            @if ($descripcion)
                <p class="bloque__descripcion">{{ $descripcion }}</p>
            @endif
        </div>
    </header>

    {{ $slot }}
</section>

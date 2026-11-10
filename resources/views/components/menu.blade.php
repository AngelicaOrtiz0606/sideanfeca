{{-- Menú lateral. Clase: App\View\Components\Menu --}}
<div class="velo-menu" data-menu-velo hidden></div>

<nav id="menu-lateral" class="menu-lateral" aria-label="Menú principal" data-abierto="false">
    <div class="menu-lateral__cabecera">
        <span class="menu-lateral__nombre">Menú</span>
        <button type="button" class="boton-icono" data-menu-cerrar>
            <x-icono nombre="cerrar" />
            <span class="solo-lectores">Cerrar menú</span>
        </button>
    </div>

    @foreach ($secciones as $seccion)
        <div class="menu-lateral__seccion">
            @if ($seccion['titulo'] !== null)
                <h2 class="menu-lateral__titulo" id="menu-seccion-{{ $loop->index }}">{{ $seccion['titulo'] }}</h2>
            @endif

            <ul class="menu-lateral__lista" @if ($seccion['titulo'] !== null) aria-labelledby="menu-seccion-{{ $loop->index }}" @endif>
                @foreach ($seccion['enlaces'] as $enlace)
                    <li>
                        @if ($enlace['url'] !== null)
                            <a
                                href="{{ $enlace['url'] }}"
                                class="menu-lateral__enlace"
                                @if ($enlace['actual']) aria-current="page" @endif
                            >
                                <x-icono :nombre="$enlace['icono']" />
                                <span>{{ $enlace['etiqueta'] }}</span>
                            </a>
                        @else
                            <span class="menu-lateral__enlace menu-lateral__enlace--pendiente" aria-disabled="true">
                                <x-icono :nombre="$enlace['icono']" />
                                <span>{{ $enlace['etiqueta'] }}<span class="solo-lectores"> (próximamente)</span></span>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach

    @if ($hayPendientes)
        <p class="menu-lateral__nota">Las opciones en gris se activan conforme se terminan sus módulos.</p>
    @endif
</nav>

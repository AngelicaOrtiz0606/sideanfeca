{{-- Encabezado institucional. Clase: App\View\Components\Header --}}
<header class="encabezado">
    <div class="encabezado__contenido">
        <div class="encabezado__marca">
            @if ($conMenu)
                <button
                    type="button"
                    class="boton-icono encabezado__boton-menu"
                    aria-controls="menu-lateral"
                    aria-expanded="false"
                    data-menu-abrir
                >
                    <x-icono nombre="menu" />
                    <span class="solo-lectores">Abrir menú</span>
                </button>
            @endif

            <a href="{{ auth()->check() ? route('inicio') : route('login') }}" class="encabezado__inicio">
                <img src="{{ asset('img/logo_anfeca.png') }}" alt="" class="encabezado__logo" width="34" height="44">
                <span class="encabezado__divisor" aria-hidden="true"></span>
                <span class="encabezado__textos">
                    <span class="encabezado__titulo">SIDEANFECA</span>
                    <span class="encabezado__subtitulo">Sistema de Directorios de ANFECA</span>
                </span>
            </a>
        </div>

        @if ($nombre !== null)
            <div class="encabezado__cuenta">
                <div class="encabezado__persona">
                    <span class="encabezado__nombre">{{ $nombre }}</span>
                    <span class="encabezado__perfil">{{ $perfil }}</span>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="boton-salir">
                        <x-icono nombre="salir" />
                        <span>Salir</span>
                    </button>
                </form>
            </div>
        @endif
    </div>
</header>

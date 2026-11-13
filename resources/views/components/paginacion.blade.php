{{--
    Paginación sencilla debajo de una tabla: "Mostrando 1–25 de 140" y los
    enlaces Anterior / páginas / Siguiente (conservan los filtros).
    Uso: <x-paginacion :paginas="$instituciones" />
--}}
@props(['paginas'])

@if ($paginas->total() > 0)
    <nav class="paginacion" aria-label="Páginas">
        <p class="paginacion__resumen">
            Mostrando {{ $paginas->firstItem() }}–{{ $paginas->lastItem() }} de {{ $paginas->total() }}
        </p>

        @if ($paginas->hasPages())
            <ul class="paginacion__lista">
                <li>
                    @if ($paginas->onFirstPage())
                        <span class="paginacion__enlace" aria-disabled="true">Anterior</span>
                    @else
                        <a class="paginacion__enlace" href="{{ $paginas->previousPageUrl() }}" rel="prev">Anterior</a>
                    @endif
                </li>
                @foreach ($paginas->getUrlRange(max(1, $paginas->currentPage() - 2), min($paginas->lastPage(), $paginas->currentPage() + 2)) as $numero => $url)
                    <li>
                        @if ($numero === $paginas->currentPage())
                            <span class="paginacion__enlace paginacion__enlace--actual" aria-current="page">{{ $numero }}</span>
                        @else
                            <a class="paginacion__enlace" href="{{ $url }}">{{ $numero }}<span class="solo-lectores"> (página)</span></a>
                        @endif
                    </li>
                @endforeach
                <li>
                    @if ($paginas->hasMorePages())
                        <a class="paginacion__enlace" href="{{ $paginas->nextPageUrl() }}" rel="next">Siguiente</a>
                    @else
                        <span class="paginacion__enlace" aria-disabled="true">Siguiente</span>
                    @endif
                </li>
            </ul>
        @endif
    </nav>
@endif

{{--
    Buscador y filtros de una lista paginada en el servidor (catálogos):
    - el buscador se aplica solo al dejar de escribir (filtros-servidor.js);
    - los botones de grupo (por ejemplo, el nivel) son enlaces con su cantidad;
    - "Borrar filtros" aparece cuando hay alguno activo.
    Todo va en la dirección (?texto=…&nivel=…), así la página conserva los filtros.

    Props: ruta (nombre de la ruta de la lista), filtros (['texto' => …, 'nivel' => …]),
           id y placeholder del buscador, grupos (['Nacional' => 8, …], opcional),
           total (cantidad sin filtro de grupo), etiquetaGrupos (para lectores de pantalla).
--}}
@props(['ruta', 'filtros', 'id', 'placeholder', 'grupos' => [], 'total' => 0, 'etiquetaGrupos' => 'Filtrar por nivel'])

@php
    $texto = $filtros['texto'] ?? null;
    $nivel = $filtros['nivel'] ?? null;
    $conTexto = array_filter(['texto' => $texto]);
@endphp

<form method="GET" action="{{ route($ruta) }}" class="filtros filtros--servidor" role="search" data-filtros-servidor>
    <label for="{{ $id }}" class="solo-lectores">{{ $placeholder }}</label>
    <input id="{{ $id }}" name="texto" type="search" class="campo__entrada filtros__buscar" placeholder="{{ $placeholder }}"
           value="{{ $texto }}" maxlength="100" data-filtro-escrito @if ($texto !== null) autofocus @endif>
    @if ($nivel)
        <input type="hidden" name="nivel" value="{{ $nivel }}">
    @endif

    @if ($grupos !== [])
        <div class="filtros__grupo" role="group" aria-label="{{ $etiquetaGrupos }}">
            <a href="{{ route($ruta, $conTexto) }}" class="filtro" aria-current="{{ $nivel ? 'false' : 'true' }}">Todos <span class="filtro__cifra">{{ $total }}</span></a>
            @foreach ($grupos as $grupo => $cantidad)
                <a href="{{ route($ruta, $conTexto + ['nivel' => $grupo]) }}" class="filtro" aria-current="{{ $nivel === $grupo ? 'true' : 'false' }}">
                    {{ $grupo }} <span class="filtro__cifra">{{ $cantidad }}</span>
                </a>
            @endforeach
        </div>
    @endif

    @if ($texto !== null || $nivel !== null)
        <a href="{{ route($ruta) }}" class="boton boton--discreto filtros__borrar">
            <x-icono nombre="cerrar" />
            Borrar filtros
        </a>
    @endif
</form>

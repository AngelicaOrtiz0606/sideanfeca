{{--
    Ventana para registrar o editar una zona: número, nombre y las entidades
    federativas que pasan a ella. La preparan los botones "Nueva zona" y
    "Editar" (resources/js/modulos/dialogos.js y cambio-de-zona.js).
    Si el servidor rechaza los datos, se vuelve a abrir con lo que se escribió.

    Parámetros: $entidades (las 32, con su zona) y $zonas (todas: para avisar si
    un nombre o número ya existe mientras se escribe, y para reabrir tras un error).
--}}
@php
    $abrir = old('_dialogo') === 'zona' && $errors->any();
    $idEnEdicion = $abrir ? (int) old('_id') : 0;
    $enEdicion = $idEnEdicion ? $zonas->firstWhere('zore_id_zona_regional', $idEnEdicion) : null;
    $elegidas = collect(old('entidades', []))->map(fn ($id) => (int) $id);
    $nombresExistentes = $zonas->map(fn ($z) => [$z->zore_id_zona_regional, $z->zore_nombre, $z->zore_nombre])->values();
    $numerosExistentes = $zonas->map(fn ($z) => [$z->zore_id_zona_regional, (string) $z->zore_numero, "Zona {$z->zore_numero} · {$z->zore_nombre}"])->values();
@endphp

<dialog id="dialogo-zona" class="dialogo dialogo--amplio" aria-labelledby="dialogo-zona-titulo" @if ($abrir) data-abrir-al-cargar @endif>
    <form method="POST" action="{{ $enEdicion ? route('zonas.update', $idEnEdicion) : route('zonas.store') }}"
          class="formulario" novalidate data-validar>
        @csrf
        <input type="hidden" name="_method" value="{{ $enEdicion ? 'PUT' : 'POST' }}">
        <input type="hidden" name="_dialogo" value="zona">
        <input type="hidden" name="_id" value="{{ $idEnEdicion ?: '' }}">

        <div class="dialogo__encabezado">
            <h2 id="dialogo-zona-titulo" class="dialogo__titulo" data-dialogo-titulo>{{ $enEdicion ? 'Editar zona' : 'Nueva zona' }}</h2>
            <button type="button" class="boton-icono boton-icono--pequeno" data-cerrar-dialogo>
                <x-icono nombre="cerrar" />
                <span class="solo-lectores">Cerrar</span>
            </button>
        </div>

        <x-nota-obligatorios />

        @if ($abrir)
            <x-errores-formulario />
        @endif

        <div class="formulario__fila">
            <div class="campo">
                <label for="zona-numero" class="campo__etiqueta">Número <x-obligatorio /></label>
                <input id="zona-numero" name="numero" type="number" inputmode="numeric" min="1" max="7" step="1" required
                       class="campo__entrada" value="{{ old('numero') }}" @if ($abrir && $errors->has('numero')) aria-invalid="true" @endif
                       data-existentes="{{ json_encode($numerosExistentes) }}" data-mensaje-repetido="El número ya pertenece a la :existente.">
            </div>

            <div class="campo">
                <label for="zona-nombre" class="campo__etiqueta">Nombre <x-obligatorio /></label>
                <input id="zona-nombre" name="nombre" type="text" maxlength="60" required autocomplete="off"
                       class="campo__entrada" value="{{ old('nombre') }}" @if ($abrir && $errors->has('nombre')) aria-invalid="true" @endif
                       data-existentes="{{ json_encode($nombresExistentes) }}" data-mensaje-repetido="Ya existe la zona «:existente».">
            </div>
        </div>

        <fieldset class="selector-entidades">
            <legend class="campo__etiqueta">Entidades federativas</legend>
            <p class="campo__ayuda">Marca las que pasan a esta zona. Para sacar una entidad de aquí, márcala en la zona a la que va.</p>

            <ul class="selector-entidades__lista">
                @foreach ($entidades as $entidad)
                    @php
                        $enEstaZona = $enEdicion && $entidad->enfe_id_zona_regional === $idEnEdicion;
                        $id = 'entidad-'.$entidad->enfe_id_entidad_federativa;
                    @endphp
                    <li>
                        <label for="{{ $id }}" @class(['opcion-entidad', 'opcion-entidad--fija' => $enEstaZona])>
                            <input id="{{ $id }}" type="checkbox" name="entidades[]" value="{{ $entidad->enfe_id_entidad_federativa }}"
                                   data-entidad="{{ $entidad->enfe_nombre }}"
                                   data-zona-id="{{ $entidad->enfe_id_zona_regional }}"
                                   data-zona-actual="{{ $entidad->zona?->etiqueta() }}"
                                   data-zona-numero="{{ $entidad->zona?->zore_numero }}"
                                   @checked($enEstaZona || $elegidas->contains($entidad->enfe_id_entidad_federativa))
                                   @disabled($enEstaZona)>
                            <span class="opcion-entidad__nombre">{{ $entidad->enfe_nombre }}</span>
                            <span class="opcion-entidad__zona" data-zona-leyenda>{{ $enEstaZona ? 'En esta zona' : 'Zona '.$entidad->zona?->zore_numero }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        </fieldset>

        <div class="dialogo__acciones">
            <button type="button" class="boton boton--secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton boton--primario">
                <x-icono nombre="correcto" />
                <span data-dialogo-boton>{{ $enEdicion ? 'Guardar cambios' : 'Registrar zona' }}</span>
            </button>
        </div>
    </form>
</dialog>

{{-- Confirmación al marcar una entidad que hoy pertenece a otra zona --}}
<dialog id="dialogo-cambio-zona" class="dialogo" aria-labelledby="dialogo-cambio-zona-titulo">
    <form method="dialog" class="formulario">
        <h2 id="dialogo-cambio-zona-titulo" class="dialogo__titulo">Esta entidad ya tiene zona</h2>
        <p class="dialogo__texto" data-texto-cambio-zona></p>

        <div class="dialogo__acciones">
            <button type="submit" value="no" class="boton boton--secundario">Cancelar</button>
            <button type="submit" value="si" class="boton boton--primario">Sí, pasarla a esta zona</button>
        </div>
    </form>
</dialog>

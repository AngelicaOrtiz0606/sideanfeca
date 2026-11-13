{{--
    Instituciones: lista con filtros (en el servidor, porque pueden ser cientos).
    "Nueva institución" y "Editar" llevan a su pantalla. Desactivar y Activar
    piden la fecha (cierran o abren su periodo de participación). Borrar solo
    si no tiene personas, sedes ni puestos.
--}}
@extends('layouts.admin')

@section('titulo', 'Instituciones')

@php
    $hayFiltros = collect($filtros)->filter()->isNotEmpty();
    $etiquetasParticipacion = ['Matriz' => 'Matrices', 'Afiliada' => 'Afiliadas', 'Observadora' => 'Observadoras'];
@endphp

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Instituciones</h1>
            <p class="pagina__texto">Matrices, afiliadas y observadoras, con sus facultades y campus.</p>
        </div>

        <div class="pagina__acciones">
            <a href="{{ route('instituciones.create') }}" class="boton boton--primario">
                <x-icono nombre="mas" />
                Nueva institución
            </a>
        </div>
    </header>

    <x-aviso-estado />

    <section class="tarjeta tarjeta--tabla" aria-label="Lista de instituciones">
        <form method="GET" action="{{ route('instituciones.index') }}" class="filtros filtros--servidor" role="search" data-filtros-servidor>
            <label for="buscar-institucion" class="solo-lectores">Buscar por nombre o número</label>
            <input id="buscar-institucion" name="texto" type="search" class="campo__entrada filtros__buscar"
                   placeholder="Buscar por nombre o número" value="{{ $filtros['texto'] }}" maxlength="100">

            @php
                $activos = collect($filtros)->except('texto')->filter()->count();
            @endphp
            <button type="button" class="filtros__resumen" aria-expanded="{{ $activos > 0 ? 'true' : 'false' }}" aria-controls="filtros-opciones" data-filtros-boton>
                <x-icono nombre="ordenar" />
                Filtros @if ($activos > 0)<span class="filtro__cifra">({{ $activos }})</span>@endif
            </button>
            <div class="filtros__opciones" id="filtros-opciones" data-filtros-opciones @if ($activos === 0) data-cerrado @endif>
            <label class="filtros__selector">
                <span class="filtros__etiqueta">Participación</span>
                <select name="participacion" class="campo__entrada">
                    <option value="">Todas</option>
                    @foreach ($participaciones as $participacion)
                        <option value="{{ $participacion }}" @selected($filtros['participacion'] === $participacion)>{{ $etiquetasParticipacion[$participacion] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="filtros__selector">
                <span class="filtros__etiqueta">Zona</span>
                <select name="zona" class="campo__entrada">
                    <option value="">Todas</option>
                    @foreach ($zonas as $zona)
                        <option value="{{ $zona->id }}" @selected($filtros['zona'] === $zona->id)>Zona {{ $zona->numero }} · {{ $zona->nombre }}</option>
                    @endforeach
                </select>
            </label>

            <label class="filtros__selector">
                <span class="filtros__etiqueta">Estado</span>
                <select name="estado" class="campo__entrada">
                    <option value="">Todas</option>
                    <option value="activas" @selected($filtros['estado'] === 'activas')>Activas</option>
                    <option value="desactivadas" @selected($filtros['estado'] === 'desactivadas')>Desactivadas</option>
                </select>
            </label>

            <label class="filtros__selector">
                <span class="filtros__etiqueta">Participó en</span>
                <select name="anio" class="campo__entrada">
                    <option value="">Cualquier año</option>
                    @foreach ($anios as $anio)
                        <option value="{{ $anio }}" @selected($filtros['anio'] === $anio)>{{ $anio }}</option>
                    @endforeach
                </select>
            </label>

            </div>

            <button type="submit" class="boton boton--discreto filtros__aplicar">Filtrar</button>
            @if ($hayFiltros)
                <a href="{{ route('instituciones.index') }}" class="filtros__limpiar">Quitar filtros</a>
            @endif
        </form>

        @if ($instituciones->isEmpty())
            <p class="vacio vacio--en-tabla">
                {{ $hayFiltros ? 'Ninguna institución coincide con los filtros.' : 'Todavía no hay instituciones. Registra la primera con Nueva institución.' }}
            </p>
        @else
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Número</th>
                        <th scope="col">Institución</th>
                        <th scope="col">Participación</th>
                        <th scope="col">Zona</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="tabla__acciones"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($instituciones as $institucion)
                        <tr @class(['tabla__fila--inactiva' => ! $institucion->activa])>
                            <td data-etiqueta="Número">
                                @if ($institucion->numero)
                                    <span class="numero-afiliacion">{{ $institucion->numero }}</span>
                                @else
                                    <span class="tabla__vacio" aria-label="Sin número">—</span>
                                @endif
                            </td>
                            <th scope="row" data-etiqueta="Institución">
                                <span class="tabla__principal">{{ $institucion->nombre }}</span>
                                @if ($institucion->universidad)
                                    <span class="tabla__detalle">{{ $institucion->tipo }} · {{ $institucion->universidad }}</span>
                                @endif
                            </th>
                            <td data-etiqueta="Participación">
                                <span class="participacion participacion--{{ strtolower($institucion->participacion) }}">{{ $institucion->participacion }}</span>
                            </td>
                            <td data-etiqueta="Zona">
                                @if ($institucion->zona_numero)
                                    <span class="numero-zona" title="Zona {{ $institucion->zona_numero }} · {{ $institucion->zona_nombre }}">{{ $institucion->zona_numero }}</span>
                                @else
                                    <span class="tabla__vacio">—</span>
                                @endif
                            </td>
                            <td data-etiqueta="Estado">
                                <span @class(['estado', 'estado--activo' => $institucion->activa])>{{ $institucion->activa ? 'Activa' : 'Desactivada' }}</span>
                            </td>
                            <td class="tabla__acciones">
                                <div class="acciones-fila">
                                    <a href="{{ route('instituciones.edit', $institucion->id) }}" class="accion accion--editar">
                                        <x-icono nombre="lapiz" />
                                        <span class="accion__texto">Editar <span class="solo-lectores">{{ $institucion->nombre }}</span></span>
                                    </a>

                                    @php
                                        $hoy = now()->toDateString();
                                    @endphp
                                    @if ($institucion->activa)
                                        <button type="button" class="accion accion--desactivar"
                                                data-abrir-dialogo="dialogo-estado-institucion"
                                                data-accion="{{ route('instituciones.estado', $institucion->id) }}"
                                                data-titulo="¿Desactivar {{ $institucion->nombre }}?"
                                                data-texto="No se borra: conserva su número de afiliación, su historial y sus personas. Su periodo de participación se cierra en esa fecha."
                                                data-etiqueta="Deja de participar el" data-boton="Desactivar"
                                                data-valores="{{ json_encode(['activa' => '0', '_id' => $institucion->id, 'fecha' => $hoy]) }}"
                                                data-atributos="{{ json_encode(['fecha' => ['min' => $institucion->periodo_desde ?? '1900-01-01']]) }}">
                                            <x-icono nombre="pausa" />
                                            <span class="accion__texto">Desactivar <span class="solo-lectores">{{ $institucion->nombre }}</span></span>
                                        </button>
                                    @else
                                        <button type="button" class="accion accion--activar"
                                                data-abrir-dialogo="dialogo-estado-institucion"
                                                data-accion="{{ route('instituciones.estado', $institucion->id) }}"
                                                data-titulo="¿Activar {{ $institucion->nombre }}?"
                                                data-texto="Se abre un periodo nuevo de participación desde esa fecha."
                                                data-etiqueta="Vuelve a participar desde" data-boton="Activar"
                                                data-valores="{{ json_encode(['activa' => '1', '_id' => $institucion->id, 'fecha' => $hoy]) }}"
                                                data-atributos="{{ json_encode(['fecha' => ['min' => $institucion->ultimo_fin ?? '1900-01-01']]) }}">
                                            <x-icono nombre="restaurar" />
                                            <span class="accion__texto">Activar <span class="solo-lectores">{{ $institucion->nombre }}</span></span>
                                        </button>
                                    @endif

                                    <button type="button" @class(['accion', 'accion--borrar', 'accion--bloqueada' => $institucion->motivo_para_no_borrar])
                                            data-abrir-dialogo="dialogo-borrar"
                                            data-accion="{{ route('instituciones.destroy', $institucion->id) }}"
                                            data-titulo="¿Borrar {{ $institucion->nombre }}?"
                                            data-bloqueo="{{ $institucion->motivo_para_no_borrar }}"
                                            data-valores="{{ json_encode(['_id' => $institucion->id, 'motivo' => '']) }}">
                                        <x-icono nombre="papelera" />
                                        <span class="accion__texto">Borrar <span class="solo-lectores">{{ $institucion->nombre }}</span></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <x-paginacion :paginas="$instituciones" />
        @endif
    </section>

    @include('instituciones._dialogo-estado', ['instituciones' => $instituciones->getCollection()])
    @php
        $borrarConError = $errors->has('motivo') ? $instituciones->getCollection()->firstWhere('id', (int) old('_id')) : null;
    @endphp
    @include('catalogos._dialogo-borrar', [
        'motivos' => $motivos,
        'abrir' => $borrarConError !== null,
        'accion' => $borrarConError ? route('instituciones.destroy', $borrarConError->id) : '',
        'titulo' => $borrarConError ? "¿Borrar {$borrarConError->nombre}?" : 'Borrar institución',
    ])
@endsection

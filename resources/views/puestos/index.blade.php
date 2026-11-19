{{--
    Catálogo de puestos: un cargo en un ámbito concreto (una institución, una
    zona con o sin coordinación, o nacional). Todo desde la lista: "Nuevo
    puesto" y "Editar" abren una ventana; "Desactivar" pide confirmación y
    "Activar" es un clic; "Borrar" pide el motivo o explica por qué no se puede.
--}}
@extends('layouts.admin')

@section('titulo', 'Puestos')

@php
    $borrarConError = $errors->has('motivo') ? $puestos->firstWhere('id', (int) old('_id')) : null;
@endphp

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Puestos</h1>
            <p class="pagina__texto">Cada cargo en su ámbito: una institución, una zona regional o nacional. A los puestos se designan las personas.</p>
        </div>

        <div class="pagina__acciones">
            <button type="button" class="boton boton--primario"
                    data-abrir-dialogo="dialogo-puesto"
                    data-accion="{{ route('puestos.store') }}" data-metodo="POST"
                    data-titulo="Nuevo puesto" data-boton="Registrar puesto" data-ambito-fijo=""
                    data-valores="{{ json_encode(['cargo' => '', 'zona' => '', 'coordinacion' => '', 'institucion' => '', 'titular' => false, '_id' => '']) }}">
                <x-icono nombre="mas" />
                Nuevo puesto
            </button>
        </div>
    </header>

    <x-aviso-estado />

    <section class="tarjeta tarjeta--tabla" aria-label="Lista de puestos" data-filtro-tabla>
        @if ($puestos->isEmpty())
            <p class="vacio vacio--en-tabla">Todavía no hay puestos. Registra el primero con <strong>Nuevo puesto</strong>.</p>
        @else
            <div class="filtros">
                <label for="buscar-puesto" class="solo-lectores">Buscar puesto</label>
                <input id="buscar-puesto" type="search" class="campo__entrada filtros__buscar" placeholder="Buscar puesto o persona" data-filtro-texto>

                <div class="filtros__grupo" role="group" aria-label="Filtrar por nivel">
                    <button type="button" class="filtro" aria-pressed="true" data-filtro-valor="">Todos <span class="filtro__cifra">{{ $puestos->count() }}</span></button>
                    @foreach ($niveles as $nivel)
                        <button type="button" class="filtro" aria-pressed="false" data-filtro-valor="{{ $nivel }}">
                            {{ $nivel }} <span class="filtro__cifra">{{ $puestos->where('nivel', $nivel)->count() }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Puesto</th>
                        <th scope="col">Nivel</th>
                        <th scope="col">Ocupado por</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="tabla__acciones"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($puestos as $puesto)
                        @php
                            $id = $puesto->id;
                            $nombre = $puesto->cargo.' · '.$puesto->ambito;
                        @endphp
                        <tr @class(['tabla__fila--inactiva' => ! $puesto->activo])
                            data-filtro-fila data-grupo="{{ $puesto->nivel }}"
                            data-texto="{{ $puesto->cargo }} {{ $puesto->cargo_f }} {{ $puesto->ambito }} {{ $puesto->ocupante }}">
                            <th scope="row" data-etiqueta="Puesto">
                                <span class="tabla__principal">{{ $puesto->cargo }}</span>
                                {{-- Un puesto nacional sin coordinación no repite "Nacional" (ya lo dice su nivel) --}}
                                @if ($puesto->nivel !== 'Nacional' || $puesto->coordinacion)
                                    <span class="tabla__detalle">
                                        {{ $puesto->ambito }}
                                        @if ($puesto->titular)
                                            <span class="insignia-titular">Titular</span>
                                        @endif
                                    </span>
                                @endif
                            </th>
                            <td data-etiqueta="Nivel"><span class="nivel-cargo nivel-cargo--{{ strtolower($puesto->nivel) }}">{{ $puesto->nivel }}</span></td>
                            <td data-etiqueta="Ocupado por">
                                @if ($puesto->ocupante)
                                    {{ $puesto->ocupante }}
                                @else
                                    <span class="tabla__vacio">Vacante</span>
                                @endif
                            </td>
                            <td data-etiqueta="Estado">
                                <span @class(['estado', 'estado--activo' => $puesto->activo])>{{ $puesto->activo ? 'Activo' : 'Desactivado' }}</span>
                            </td>
                            <td class="tabla__acciones">
                                <div class="acciones-fila">
                                    <button type="button" class="accion accion--editar"
                                            data-abrir-dialogo="dialogo-puesto"
                                            data-accion="{{ route('puestos.update', $id) }}" data-metodo="PUT"
                                            data-titulo="Editar puesto" data-boton="Guardar cambios"
                                            data-ambito-fijo="{{ $puesto->designaciones > 0 ? 'Ya tiene designaciones, así que el cargo y el ámbito no cambian. Si el puesto cambió, desactívalo y registra otro.' : '' }}"
                                            data-valores="{{ json_encode(['cargo' => $puesto->cargo_id, 'zona' => $puesto->zona_id ?? '', 'coordinacion' => $puesto->coordinacion_id ?? '', 'institucion' => $puesto->institucion_id ?? '', 'titular' => (bool) $puesto->titular, '_id' => $id]) }}">
                                        <x-icono nombre="lapiz" />
                                        <span class="accion__texto">Editar <span class="solo-lectores">{{ $nombre }}</span></span>
                                    </button>

                                    @if ($puesto->activo)
                                        <button type="button" class="accion accion--desactivar"
                                                data-abrir-dialogo="dialogo-desactivar"
                                                data-accion="{{ route('puestos.estado', $id) }}"
                                                data-titulo="¿Desactivar el puesto {{ $nombre }}?">
                                            <x-icono nombre="pausa" />
                                            <span class="accion__texto">Desactivar <span class="solo-lectores">{{ $nombre }}</span></span>
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('puestos.estado', $id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="activa" value="1">
                                            <button type="submit" class="accion accion--activar">
                                                <x-icono nombre="restaurar" />
                                                <span class="accion__texto">Activar <span class="solo-lectores">{{ $nombre }}</span></span>
                                            </button>
                                        </form>
                                    @endif

                                    <button type="button" @class(['accion', 'accion--borrar', 'accion--bloqueada' => $puesto->motivo_para_no_borrar])
                                            data-abrir-dialogo="dialogo-borrar"
                                            data-accion="{{ route('puestos.destroy', $id) }}"
                                            data-titulo="¿Borrar el puesto {{ $nombre }}?"
                                            data-bloqueo="{{ $puesto->motivo_para_no_borrar }}"
                                            data-valores="{{ json_encode(['_id' => $id, 'motivo' => '']) }}">
                                        <x-icono nombre="papelera" />
                                        <span class="accion__texto">Borrar <span class="solo-lectores">{{ $nombre }}</span></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="vacio vacio--en-tabla" data-filtro-vacio hidden>Ningún puesto coincide con la búsqueda.</p>
        @endif
    </section>

    @include('puestos._dialogo-puesto')
    @include('catalogos._dialogo-desactivar', ['texto' => 'No se borra: conserva su historia (quiénes lo ocuparon) y lo puedes volver a activar. Mientras esté desactivado no recibe designaciones nuevas.'])
    @include('catalogos._dialogo-borrar', [
        'motivos' => $motivos,
        'abrir' => $borrarConError !== null,
        'accion' => $borrarConError ? route('puestos.destroy', $borrarConError->id) : '',
        'titulo' => $borrarConError ? "¿Borrar el puesto {$borrarConError->cargo} · {$borrarConError->ambito}?" : 'Borrar puesto',
    ])
@endsection

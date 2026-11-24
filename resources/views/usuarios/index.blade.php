{{--
    Usuarios (solo superadministrador): las cuentas, con filtros por texto,
    rol y estado. Acciones con ícono: Editar y Desactivar / Activar (las
    cuentas no se borran; desactivada, no puede entrar).
--}}
@extends('layouts.admin')

@section('titulo', 'Usuarios')

@php
    $activos = collect([$filtros['rol'], $filtros['estado']])->filter()->count();
    $hayFiltros = $filtros['texto'] !== null || $activos > 0;
@endphp

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Usuarios</h1>
            <p class="pagina__texto">Las cuentas para entrar al sistema. No se borran: se desactivan, y una cuenta desactivada no puede entrar.</p>
        </div>

        <div class="pagina__acciones">
            <a href="{{ route('usuarios.create') }}" class="boton boton--primario">
                <x-icono nombre="mas" />
                Nuevo usuario
            </a>
        </div>
    </header>

    <x-aviso-estado />

    <section class="tarjeta tarjeta--tabla" aria-label="Lista de usuarios">
        <form method="GET" action="{{ route('usuarios.index') }}" class="filtros filtros--servidor" role="search" data-filtros-servidor>
            <label for="buscar-usuario" class="solo-lectores">Buscar por nombre o correo</label>
            <input id="buscar-usuario" name="texto" type="search" class="campo__entrada filtros__buscar"
                   placeholder="Nombre o correo" value="{{ $filtros['texto'] }}" maxlength="100"
                   data-filtro-escrito @if ($filtros['texto'] !== null) autofocus @endif>

            <button type="button" class="filtros__resumen" aria-expanded="{{ $activos > 0 ? 'true' : 'false' }}" aria-controls="filtros-opciones" data-filtros-boton>
                <x-icono nombre="ordenar" />
                Filtros @if ($activos > 0)<span class="filtro__cifra">({{ $activos }})</span>@endif
            </button>
            <div class="filtros__opciones" id="filtros-opciones" data-filtros-opciones @if ($activos === 0) data-cerrado @endif>
                <label class="filtros__selector">
                    <span class="filtros__etiqueta">Rol</span>
                    <select name="rol" class="campo__entrada">
                        <option value="">Todos</option>
                        @foreach ($roles as $rol)
                            <option value="{{ $rol->id }}" @selected($filtros['rol'] === $rol->id)>{{ $rol->nombre }}</option>
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
            </div>

            @if ($hayFiltros)
                <a href="{{ route('usuarios.index') }}" class="boton boton--discreto filtros__borrar">
                    <x-icono nombre="cerrar" />
                    Borrar filtros
                </a>
            @endif
        </form>

        @if ($cuentas->isEmpty())
            <p class="vacio vacio--en-tabla">{{ $hayFiltros ? 'Ninguna cuenta coincide con los filtros.' : 'Todavía no hay cuentas.' }}</p>
        @else
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Nombre</th>
                        <th scope="col">Rol</th>
                        <th scope="col">Último acceso</th>
                        <th scope="col">Estado</th>
                        <th scope="col" class="tabla__acciones"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($cuentas as $cuenta)
                        @php $esPropia = $cuenta->id === auth()->id(); @endphp
                        <tr @class(['tabla__fila--inactiva' => ! $cuenta->activa])>
                            <th scope="row" data-etiqueta="Nombre">
                                <span class="tabla__principal">{{ $cuenta->nombre }}</span>
                                @if ($esPropia)
                                    <span class="insignia-titular">Tú</span>
                                @endif
                                <span class="tabla__detalle">{{ $cuenta->correo }}</span>
                            </th>
                            <td data-etiqueta="Rol">{{ $cuenta->rol }}</td>
                            <td data-etiqueta="Último acceso">
                                @if ($cuenta->ultimo_acceso)
                                    {{ \Illuminate\Support\Carbon::parse($cuenta->ultimo_acceso)->format('d/m/Y H:i') }}
                                @else
                                    <span class="tabla__vacio">Nunca ha entrado</span>
                                @endif
                            </td>
                            <td data-etiqueta="Estado">
                                <span @class(['estado', 'estado--activo' => $cuenta->activa])>{{ $cuenta->activa ? 'Activa' : 'Desactivada' }}</span>
                            </td>
                            <td class="tabla__acciones">
                                <div class="acciones-fila">
                                    <a href="{{ route('usuarios.edit', $cuenta->id) }}" class="accion accion--editar">
                                        <x-icono nombre="lapiz" />
                                        <span class="accion__texto">Editar <span class="solo-lectores">la cuenta de {{ $cuenta->nombre }}</span></span>
                                    </a>

                                    @if ($cuenta->activa)
                                        <button type="button" @class(['accion', 'accion--desactivar', 'accion--a-la-derecha', 'accion--bloqueada' => $esPropia])
                                                data-abrir-dialogo="dialogo-desactivar"
                                                data-accion="{{ route('usuarios.estado', $cuenta->id) }}"
                                                data-titulo="¿Desactivar la cuenta de {{ $cuenta->nombre }}?"
                                                @disabled($esPropia)>
                                            <x-icono nombre="pausa" />
                                            <span class="accion__texto">{{ $esPropia ? 'No puedes desactivar tu propia cuenta' : 'Desactivar' }} <span class="solo-lectores">la cuenta de {{ $cuenta->nombre }}</span></span>
                                        </button>
                                    @else
                                        <form method="POST" action="{{ route('usuarios.estado', $cuenta->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="activa" value="1">
                                            <button type="submit" class="accion accion--activar accion--a-la-derecha">
                                                <x-icono nombre="restaurar" />
                                                <span class="accion__texto">Activar <span class="solo-lectores">la cuenta de {{ $cuenta->nombre }}</span></span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <x-paginacion :paginas="$cuentas" />
        @endif
    </section>

    @include('catalogos._dialogo-desactivar', ['texto' => 'Ya no podrá entrar al sistema. No se borra nada: sus datos y su lugar en los directorios no cambian, y la puedes volver a activar.'])
@endsection

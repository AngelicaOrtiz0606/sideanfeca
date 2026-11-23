{{--
    Personas ocultas (solo superadministrador). Son personas con cuenta que
    un administrador borró: no aparecen en Personas ni en los directorios
    (su puesto se ve vacante), pero no se borró nada.
    Cada fila: Mostrar (vuelve a aparecer), Eliminar definitivamente (se
    borran sus datos; se conservan nombre y CURP y la cuenta se desactiva) y
    Cancelación ARCO (se borra todo lo personal).
--}}
@extends('layouts.admin')

@section('titulo', 'Personas ocultas')

@section('contenido')
    <a href="{{ route('personas.index') }}" class="enlace-regresar">
        <x-icono nombre="regresar" />
        Personas
    </a>

    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Personas ocultas</h1>
            <p class="pagina__texto">Personas con cuenta que un administrador borró. No aparecen en Personas ni en los directorios, pero sus datos se conservan.</p>
        </div>
    </header>

    <x-aviso-estado />

    <section class="tarjeta tarjeta--tabla" aria-label="Personas ocultas">
        @if ($ocultas->isEmpty())
            <p class="vacio vacio--en-tabla">No hay personas ocultas.</p>
        @else
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col">Persona</th>
                        <th scope="col">Cuenta</th>
                        <th scope="col" class="tabla__acciones"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ocultas as $oculta)
                        <tr>
                            <th scope="row" data-etiqueta="Persona"><span class="tabla__principal">{{ $oculta->nombre_completo }}</span></th>
                            <td data-etiqueta="Cuenta">
                                @if ($oculta->correo)
                                    {{ $oculta->correo }}
                                    <span class="tabla__detalle">{{ $oculta->cuenta_activa ? 'Activa' : 'Desactivada' }}</span>
                                @else
                                    <span class="tabla__vacio">Sin cuenta</span>
                                @endif
                            </td>
                            <td class="tabla__acciones">
                                <div class="acciones-fila">
                                    <form method="POST" action="{{ route('personas.mostrar', $oculta->id) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="accion accion--activar">
                                            <x-icono nombre="ojo" />
                                            <span class="accion__texto">Mostrar <span class="solo-lectores">{{ $oculta->nombre_completo }}</span></span>
                                        </button>
                                    </form>
                                    @can(\App\Enums\Privilegio::EliminarDefinitivo->value)
                                        <button type="button" class="accion accion--borrar"
                                                data-abrir-dialogo="dialogo-borrar"
                                                data-accion="{{ route('personas.definitiva', $oculta->id) }}"
                                                data-titulo="¿Eliminar definitivamente a {{ $oculta->nombre_completo }}?"
                                                data-texto="Se borran sus contactos, grados, designaciones y pertenencia. Se conservan su nombre y su CURP (por si regresa) y su cuenta se desactiva. No se puede deshacer."
                                                data-valores="{{ json_encode(['_id' => $oculta->id, 'motivo' => '']) }}">
                                            <x-icono nombre="papelera" />
                                            <span class="accion__texto">Eliminar definitivamente <span class="solo-lectores">{{ $oculta->nombre_completo }}</span></span>
                                        </button>
                                        <button type="button" class="accion accion--borrar accion--a-la-derecha"
                                                data-abrir-dialogo="dialogo-arco"
                                                data-accion="{{ route('personas.arco', $oculta->id) }}"
                                                data-texto="«{{ $oculta->nombre_completo }}»"
                                                data-valores="{{ json_encode(['_id' => $oculta->id, 'confirmacion' => '']) }}">
                                            <x-icono nombre="alerta" />
                                            <span class="accion__texto">Cancelación ARCO <span class="solo-lectores">{{ $oculta->nombre_completo }}</span></span>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @php
        $conError = fn (string $campo) => $errors->has($campo) ? $ocultas->firstWhere('id', (int) old('_id')) : null;
    @endphp
    @include('catalogos._dialogo-borrar', [
        'motivos' => $motivos,
        'abrir' => $conError('motivo') !== null,
        'accion' => $conError('motivo') ? route('personas.definitiva', $conError('motivo')->id) : '',
        'titulo' => $conError('motivo') ? "¿Eliminar definitivamente a {$conError('motivo')->nombre_completo}?" : 'Eliminar definitivamente',
    ])
    @include('personas._dialogo-arco', [
        'abrir' => $conError('confirmacion') !== null,
        'accion' => $conError('confirmacion') ? route('personas.arco', $conError('confirmacion')->id) : '',
    ])
@endsection

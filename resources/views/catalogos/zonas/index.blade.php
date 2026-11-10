{{-- Catálogo de zonas regionales: lista con sus entidades e instituciones --}}
@extends('layouts.admin')

@section('titulo', 'Zonas regionales')

@section('contenido')
    <header class="pagina__encabezado">
        <div>
            <h1 class="pagina__titulo">Zonas regionales</h1>
            <p class="pagina__texto">Las zonas de ANFECA y las entidades federativas de cada una.</p>
        </div>

        @if ($hayNumerosLibres)
            <div class="pagina__acciones">
                <a href="{{ route('zonas.create') }}" class="boton boton--primario">
                    <x-icono nombre="mas" />
                    Nueva zona
                </a>
            </div>
        @endif
    </header>

    <x-aviso-estado />

    <section class="tarjeta tarjeta--tabla" aria-label="Lista de zonas regionales">
        @if ($zonas->isEmpty())
            <p class="vacio">Todavía no hay zonas registradas.</p>
        @else
            <table class="tabla">
                <thead>
                    <tr>
                        <th scope="col" class="tabla__numero">Número</th>
                        <th scope="col">Nombre</th>
                        <th scope="col" class="tabla__cifra">Entidades</th>
                        <th scope="col" class="tabla__cifra">Instituciones</th>
                        <th scope="col" class="tabla__acciones"><span class="solo-lectores">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($zonas as $zona)
                        <tr>
                            <td class="tabla__numero" data-etiqueta="Número">
                                <span class="numero-zona">{{ $zona->zore_numero }}</span>
                            </td>
                            <th scope="row" data-etiqueta="Nombre">
                                <a href="{{ route('zonas.show', $zona->zore_id_zona_regional) }}" class="tabla__enlace">{{ $zona->zore_nombre }}</a>
                            </th>
                            <td class="tabla__cifra" data-etiqueta="Entidades">{{ $zona->entidades }}</td>
                            <td class="tabla__cifra" data-etiqueta="Instituciones">{{ $zona->instituciones }}</td>
                            <td class="tabla__acciones">
                                <a href="{{ route('zonas.edit', $zona->zore_id_zona_regional) }}" class="boton-accion">
                                    <x-icono nombre="lapiz" />
                                    Editar<span class="solo-lectores"> la zona {{ $zona->zore_nombre }}</span>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection

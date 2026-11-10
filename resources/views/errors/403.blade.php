{{--
    Página 403: la cuenta no tiene el privilegio de esta pantalla (sección 7).
    No muestra detalles técnicos ni el nombre del privilegio.
--}}
@extends(auth()->check() ? 'layouts.admin' : 'layouts.publico')

@section('titulo', 'Sin permiso')

@section('contenido')
    <section class="aviso-pagina" aria-labelledby="aviso-titulo">
        <div class="aviso-pagina__ilustracion" aria-hidden="true">
            <span class="aviso-pagina__codigo">403</span>
            <span class="aviso-pagina__sello">
                <x-icono nombre="candado" />
            </span>
        </div>

        <h1 id="aviso-titulo" class="aviso-pagina__titulo">Esta sección no está disponible para tu perfil</h1>

        <p class="aviso-pagina__texto">
            Tu cuenta no tiene permiso para entrar aquí. Si necesitas usar esta sección,
            pide a la persona responsable del sistema que revise tus permisos.
        </p>

        @auth
            <div class="aviso-pagina__acciones">
                <a href="{{ route('inicio') }}" class="boton boton--primario">
                    <x-icono nombre="casa" />
                    Volver al inicio
                </a>
            </div>
        @else
            <div class="aviso-pagina__acciones">
                <a href="{{ route('login') }}" class="boton boton--primario">
                    <x-icono nombre="entrar" />
                    Ir a iniciar sesión
                </a>
            </div>
        @endauth
    </section>
@endsection

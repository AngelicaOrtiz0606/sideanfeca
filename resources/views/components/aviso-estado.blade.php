{{--
    Mensajes guardados en la sesión al regresar de una acción:
    - "estado": salió bien (verde).
    - "aviso": no se pudo hacer y se explica por qué (ámbar).
--}}
@if (session('estado'))
    <div class="aviso aviso--exito" role="status">
        <x-icono nombre="correcto" />
        <span>{{ session('estado') }}</span>
    </div>
@endif

@if (session('aviso'))
    <div class="aviso aviso--atencion" role="alert">
        <x-icono nombre="alerta" />
        <span>{{ session('aviso') }}</span>
    </div>
@endif

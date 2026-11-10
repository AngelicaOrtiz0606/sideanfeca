{{-- Mensaje de estado guardado en la sesión con la clave "estado" (por ejemplo, tras enviar un código) --}}
@if (session('estado'))
    <div class="aviso aviso--exito" role="status">
        <x-icono nombre="correcto" />
        <span>{{ session('estado') }}</span>
    </div>
@endif

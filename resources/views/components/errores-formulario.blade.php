{{--
    Errores que encontró el servidor al guardar un formulario, todos juntos
    arriba del formulario (los campos solo se delinean en rojo).
    Uso: <x-errores-formulario />
--}}
@if ($errors->any())
    <div class="aviso aviso--error" role="alert" tabindex="-1" data-errores>
        <x-icono nombre="alerta" />
        <ul class="aviso__lista">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

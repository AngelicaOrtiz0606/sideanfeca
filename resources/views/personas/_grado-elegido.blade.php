{{--
    Un grado que tiene la persona, en el formulario: su nombre, su abreviatura
    (cambia con el género) y el botón para quitarlo. La plantilla usa __id__,
    __nombre__, __m__ y __f__, que persona-formulario.js cambia.
--}}
<li class="grado-elegido" data-grado="{{ $id }}">
    <input type="hidden" name="grados[]" value="{{ $id }}">
    <span class="grado-elegido__nombre">{{ $nombre }}</span>
    <span class="grado-elegido__abreviatura" data-abreviatura data-m="{{ $abrM }}" data-f="{{ $abrF }}">{{ ($genero ?? null) === 'F' ? $abrF : $abrM }}</span>
    <button type="button" class="grado-elegido__quitar" data-grado-quitar>
        <x-icono nombre="cerrar" />
        <span class="solo-lectores">Quitar {{ $nombre }}</span>
    </button>
</li>

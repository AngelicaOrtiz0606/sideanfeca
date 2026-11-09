{{--
    Pie de página con la leyenda OFICIAL de la dirección, en el orden que pidió:
        Hecho en México / D.R. © año
        Leyenda + AVISO DE PRIVACIDAD + CIFCA
        Documento de seguridad | Instrumento jurídico | Aviso de privacidad simplificado
    No cambies el texto. El año de "D.R. ©" se actualiza solo. Las direcciones
    de los enlaces se configuran en config/sideanfeca.php; si una queda vacía,
    su texto se muestra sin enlace.
--}}
@php
    $enlace = function (string $clave, string $texto): string {
        $url = config("sideanfeca.enlaces_legales.{$clave}");

        if (blank($url)) {
            return e($texto);
        }

        return '<a href="'.e($url).'" target="_blank" rel="noopener">'.e($texto)
            .'<span class="solo-lectores"> (PDF, se abre en otra pestaña)</span></a>';
    };
@endphp

<footer {{ $attributes->merge(['class' => 'pie']) }}>
    <div class="pie__contenido">
        <div class="pie__datos">
            <p>Hecho en México</p>
            <p>D.R. © {{ now()->year }}</p>
        </div>

        <div class="pie__texto">
            <p class="pie__leyenda">
                Esta página puede ser reproducida con fines no lucrativos, siempre y cuando no se mutile,
                se cite la fuente completa y su dirección electrónica. De otra forma requiere permiso
                previo por escrito de la institución. {!! $enlace('aviso_privacidad', 'AVISO DE PRIVACIDAD') !!}.
                Sitio web administrado por el Centro de Informática de la Facultad de Contaduría y Administración (CIFCA).
            </p>

            <ul class="pie__legales">
                <li>{!! $enlace('documento_seguridad', 'Documento de seguridad') !!}</li>
                <li>{!! $enlace('instrumento_juridico', 'Instrumento jurídico') !!}</li>
                <li>{!! $enlace('aviso_privacidad_simplificado', 'Aviso de privacidad simplificado') !!}</li>
            </ul>
        </div>
    </div>
</footer>

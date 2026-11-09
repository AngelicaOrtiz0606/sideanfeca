{{--
    Pie de página con la leyenda OFICIAL de la dirección (tomada del prototipo).
    No cambies el texto. El año de "D.R. ©" se actualiza solo. Las direcciones de los enlaces legales se configuran en
    config/sideanfeca.php; mientras estén vacías, el texto se muestra sin enlace.
--}}
@php
    $enlacesLegales = [
        ['texto' => 'AVISO DE PRIVACIDAD', 'url' => config('sideanfeca.enlaces_legales.aviso_privacidad')],
        ['texto' => 'Documento de seguridad', 'url' => config('sideanfeca.enlaces_legales.documento_seguridad')],
        ['texto' => 'Instrumento jurídico', 'url' => config('sideanfeca.enlaces_legales.instrumento_juridico')],
        ['texto' => 'Aviso de privacidad simplificado', 'url' => config('sideanfeca.enlaces_legales.aviso_privacidad_simplificado')],
    ];
@endphp

<footer {{ $attributes->merge(['class' => 'pie']) }}>
    <div class="pie__franja">
        <div class="pie__contenido pie__superior">
            <div class="pie__datos">
                <p class="pie__titulo">Sistema Integral de Directorios ANFECA</p>
                <p>Hecho en México</p>
                <p>D.R. © {{ now()->year }}</p>
            </div>

            <a href="#inicio-pagina" class="pie__subir">
                <x-icono nombre="subir" />
                <span>Subir</span>
                <span class="solo-lectores"> al inicio de la página</span>
            </a>
        </div>
    </div>

    <div class="pie__franja pie__franja--inferior">
        <div class="pie__contenido pie__inferior">
            <p class="pie__leyenda">
                Esta página puede ser reproducida con fines no lucrativos, siempre y cuando no se mutile,
                se cite la fuente completa y su dirección electrónica. De otra forma requiere permiso
                previo por escrito de la institución.
            </p>

            <div class="pie__cierre">
                <ul class="pie__legales">
                    @foreach ($enlacesLegales as $enlace)
                        <li>
                            @if (filled($enlace['url']))
                                <a href="{{ $enlace['url'] }}" target="_blank" rel="noopener">
                                    {{ $enlace['texto'] }}<span class="solo-lectores"> (PDF, se abre en otra pestaña)</span>
                                </a>
                            @else
                                <span>{{ $enlace['texto'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <p class="pie__administra">
                    Sitio web administrado por el Centro de Informática de la Facultad de Contaduría y Administración (CIFCA).
                </p>
            </div>
        </div>
    </div>
</footer>

{{--
    Un movimiento de la bitácora en una línea: "Marta Ruiz creó la cuenta de…",
    su fecha, su motivo y, si los abre, sus cambios. Lo usan el inicio y la
    bitácora completa. Parámetro: $movimiento (de BitacoraService::describir).
--}}
<li class="actividad__item">
    <div class="actividad__cuerpo">
        <p class="actividad__frase">
            <strong>{{ $movimiento['quien'] }}</strong>
            <span class="actividad__verbo" data-tono="{{ $movimiento['tono'] }}">{{ $movimiento['verbo'] }}</span>
            {{ $movimiento['objeto'] }}.
        </p>

        <p class="actividad__meta">
            <time datetime="{{ $movimiento['fecha']->toIso8601String() }}">{{ $movimiento['fecha']->format('d/m/Y') }} · {{ $movimiento['fecha']->format('H:i') }} hrs</time>
            @if ($movimiento['motivo'])
                <span>Motivo: {{ $movimiento['motivo'] }}</span>
            @endif
        </p>

        {{-- Los cambios se muestran solo si la persona los abre, para no saturar la lista --}}
        @if ($movimiento['detalles'] !== [])
            @php
                $totalCambios = count($movimiento['detalles']) + $movimiento['mas_detalles'];
            @endphp
            <details class="actividad__cambios">
                <summary>Ver {{ $totalCambios === 1 ? 'el cambio' : "los {$totalCambios} cambios" }}</summary>
                <ul>
                    @foreach ($movimiento['detalles'] as $detalle)
                        <li>{{ $detalle }}</li>
                    @endforeach
                    @if ($movimiento['mas_detalles'] > 0)
                        <li>Y {{ $movimiento['mas_detalles'] }} más en la bitácora completa.</li>
                    @endif
                </ul>
            </details>
        @endif
    </div>
</li>

{{--
    Lugar de las filas de designación (componente Vue FilasDesignacion). Sus
    datos van en data-props: los cargos activos (con el nombre en ambos
    géneros), zonas, coordinaciones, las filas escritas antes (si hubo
    errores) y qué campos tuvieron error.
    Parámetros: $cargos, $zonas, $coordinaciones, $notaInstitucional,
    $minimo (filas que siempre quedan: 1 por omisión; 0 al editar a una persona)
    e $institucion (la de la persona, si ya está fija: "Agregar designación").
--}}
@php
    $props = [
        'cargos' => $cargos->map(fn ($c) => ['id' => $c->id, 'nivel' => $c->nivel, 'nombre' => $c->nombre === $c->nombre_f ? $c->nombre : "{$c->nombre} / {$c->nombre_f}"])->values(),
        'zonas' => $zonas->map(fn ($z) => ['id' => $z->id, 'texto' => "Zona {$z->numero} · {$z->nombre}"])->values(),
        'coordinaciones' => $coordinaciones->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])->values(),
        'iniciales' => collect(old('designaciones', []))->filter(fn ($d) => is_array($d))->values(),
        'errores' => collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'designaciones.'))->map(fn ($m) => $m[0]),
        'maximo' => \App\Http\Requests\Personas\PersonaRequest::MAXIMO_DESIGNACIONES,
        'hoy' => now()->toDateString(),
        'notaInstitucional' => $notaInstitucional,
        'minimo' => $minimo ?? 1,
        'institucion' => isset($institucion) && $institucion ? (int) $institucion : null,
        'urlTitular' => route('personas.titular', ['institucion' => '__id__']),
    ];
@endphp
<div data-vue="designaciones" data-props="{{ json_encode($props, JSON_UNESCAPED_UNICODE) }}">
    <p class="campo__ayuda">Cargando las designaciones…</p>
</div>

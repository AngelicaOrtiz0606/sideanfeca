{{-- Página temporal para probar el inicio de sesión. La pantalla de inicio de la
     sección 10 (saludo, contadores y bitácora) la reemplaza en otro lote de M1. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inicio · SIDEANFECA</title>
</head>
<body>
<main>
    <h1>Sesión iniciada</h1>
    <p>Esta página es temporal.</p>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Cerrar sesión</button>
    </form>
</main>
</body>
</html>

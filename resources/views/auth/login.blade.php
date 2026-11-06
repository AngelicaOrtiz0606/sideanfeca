{{-- Versión funcional sin diseño final. El layout institucional (header, menú y
     footer) se aplica en el lote visual de M1. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · SIDEANFECA</title>
</head>
<body>
<main>
    <h1>Iniciar sesión</h1>
    <p>Sistema de Directorios de ANFECA</p>

    <form method="POST" action="{{ route('login.iniciar') }}" novalidate>
        @csrf

        <div>
            <label for="correo">Correo electrónico</label>
            <input
                id="correo"
                name="correo"
                type="email"
                value="{{ old('correo') }}"
                autocomplete="username"
                maxlength="254"
                required
                autofocus
                @error('correo') aria-invalid="true" aria-describedby="correo-error" @enderror
            >
            @error('correo')
                <p id="correo-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="contrasenia">Contraseña</label>
            <input
                id="contrasenia"
                name="contrasenia"
                type="password"
                autocomplete="current-password"
                maxlength="255"
                required
                @error('contrasenia') aria-invalid="true" aria-describedby="contrasenia-error" @enderror
            >
            @error('contrasenia')
                <p id="contrasenia-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit">Iniciar sesión</button>
    </form>
</main>
</body>
</html>

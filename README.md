# SIDEANFECA

Sistema de Directorios de la Asociación Nacional de Facultades y Escuelas de Contaduría y Administración (ANFECA). Desarrollado por el CIFCA, FCA UNAM.

Registra las instituciones afiliadas y observadoras, las personas que ocupan cargos y los puestos de los directorios (Consejo Nacional Directivo, Consejos Regionales, Coordinaciones Nacionales e Instituciones). Genera los directorios que se publican en el sitio de ANFECA y se exportan a CSV.

## Tecnologías

- PHP 8.4 y Laravel 13
- PostgreSQL 18 (el esquema usa la extensión `btree_gist`)
- Vite y Tailwind para estilos; Vue.js solo en partes interactivas
- Probado en AlmaLinux 10 con Apache

## Instalación desde cero

Todos los comandos se corren en la carpeta del proyecto.

1. **Dependencias de PHP y de JavaScript.**

   ```bash
   composer install
   npm install
   npm run build
   ```

2. **Configuración.** Copia el archivo de ejemplo y genera la llave de la aplicación.

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   En `.env` escribe los datos de PostgreSQL: `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`. El usuario de la base debe ser dueño de la base (lo necesita para crear la extensión `btree_gist`).

   Escribe también `APP_URL` con la dirección con la que se entra al sistema (en el servidor oficial, su dirección definitiva; en una máquina de práctica puede quedarse en `http://localhost`). Los enlaces de recuperación de contraseña que se envían por correo se arman siempre con ella, nunca con la dirección que diga el navegador, para que nadie pueda desviarlos a un sitio falso.

3. **Esquema de la base de datos.** La migración carga `database/sql/esquema_sideanfeca.sql` completo (tablas, funciones y triggers).

   ```bash
   php artisan migrate
   ```

4. **Datos de instalación.** Privilegios, roles, zonas regionales, entidades federativas y coordinaciones nacionales.

   ```bash
   php artisan db:seed
   ```

5. **Municipios y colonias.** Descarga el catálogo oficial de Correos de México (`CPdescarga.txt`), guárdalo en `storage/app/catalogos/` e impórtalo. El archivo no se sube al repositorio.

   ```bash
   php artisan catalogos:importar-correos --limite=500
   php artisan catalogos:importar-correos
   ```

   La primera línea es una prueba con 500 filas; la segunda importa todo.

6. **Primer superadministrador.** Solo funciona si todavía no existe ninguna cuenta. Pide los datos uno por uno y la contraseña oculta.

   ```bash
   php artisan sideanfeca:crear-superadmin
   ```

   Las contraseñas de todas las cuentas deben tener al menos 8 caracteres, una mayúscula, un número y un carácter especial.

## Pruebas

Las pruebas corren en una base aparte (`sideanfeca_testing`), nunca en la base de trabajo. `tests/TestCase.php` las detiene si la base no termina en `_testing`.

Preparación, una sola vez:

```bash
sudo -u postgres createdb -O USUARIO_DE_LA_BASE sideanfeca_testing
cp .env .env.testing
```

En `.env.testing` cambia solo `APP_ENV=testing` y `DB_DATABASE=sideanfeca_testing`. Después:

```bash
php artisan config:show database.connections.pgsql.database --env=testing
php artisan migrate --env=testing --force
```

El primer comando debe mostrar `sideanfeca_testing`. Para correr las pruebas:

```bash
php artisan test
```

Cada prueba corre dentro de una transacción que se deshace al terminar, así que la base de pruebas queda vacía.

Antes de cada entrega, revisa también las dependencias:

```bash
composer audit
```

## Antes de pasar al servidor oficial

En el `.env` del servidor oficial cambia estos valores (en la máquina de práctica se quedan como están):

| Variable | Valor | Por qué |
|---|---|---|
| `APP_ENV` | `production` | Indica que es el sistema real. |
| `APP_DEBUG` | `false` | Si algo falla, la persona ve un mensaje general y no el código ni los datos internos. |
| `APP_URL` | la dirección definitiva, con `https://` | Con ella se arman los enlaces de recuperación. |
| `LOG_LEVEL` | `warning` | El registro solo guarda avisos y errores. |
| `SESSION_SECURE_COOKIE` | `true` | La sesión solo viaja por HTTPS. Ponlo únicamente cuando el sitio ya tenga HTTPS, o no se podrá iniciar sesión. |

Después de cambiarlos:

```bash
php artisan optimize:clear
php artisan config:cache
```

## Dónde está cada cosa

| Qué | Dónde |
|---|---|
| Direcciones de las pantallas, cada una con su alias (`route('login')`) | `routes/web.php` |
| Tareas programadas | `routes/console.php` |
| Controladores: reciben la petición y eligen la vista | `app/Http/Controllers/` |
| Validación de formularios | `app/Http/Requests/` |
| Lógica de cada proceso (llamadas a las funciones SQL, bitácora, contadores) | `app/Services/` |
| Modelos de las tablas | `app/Models/` |
| Reglas de validación propias | `app/Rules/` |
| Funciones de ayuda, como `imagen('logo')` | `app/helpers.php` |
| Configuración propia (imágenes, enlaces legales) | `config/sideanfeca.php` |
| Layouts (`admin` y `publico`) | `resources/views/layouts/` |
| Piezas reutilizables (header, menú, footer, campos, íconos) | `resources/views/components/` |
| Pantallas | `resources/views/auth/`, `resources/views/inicio.blade.php` |
| Colores y estilos que se usan en todas las pantallas (títulos, subtítulos, enlaces) | `resources/css/style.css` |
| Estilos de cada pieza y de cada pantalla | `resources/css/componentes/`, `resources/css/layouts/`, `resources/css/paginas/` |
| JavaScript, dividido por función | `resources/js/modulos/` |
| Pruebas | `tests/Feature/`, con ayudas en `tests/Support/` |

Regla de diseño: nunca usar líneas de acento arriba ni a los lados de las tarjetas.

### Cómo se trabaja en las pantallas de catálogo

Zonas, coordinaciones, cargos y niveles académicos siguen el mismo patrón, para que se den los menos clics posibles:

- **Todo desde la lista.** Cada fila tiene sus acciones: Editar, Desactivar o Activar, y Borrar.
- **Nuevo y Editar se abren en una ventana** sobre la lista. Solo los registros con mucha información (personas, instituciones) tienen pantalla propia.
- **Borrar** pide el motivo. Si el registro tiene cosas relacionadas, el botón se ve gris y explica por qué no se puede y qué hacer.
- **Desactivar** pide confirmación; **activar** es un clic.
- Si el servidor rechaza los datos, la ventana se vuelve a abrir con lo escrito y el error arriba.
- La consulta (al dar clic en el nombre) es solo para ver lo relacionado, con su botón Editar.

Piezas que se reutilizan: `resources/views/catalogos/_dialogo-borrar.blade.php`, `resources/js/modulos/dialogos.js` (abre y llena cada ventana con atributos `data-*`) y los botones `.accion` de `resources/css/componentes/botones.css`.

## Reglas para programar

- La lógica de negocio vive en la base de datos. Laravel llama a las funciones SQL y les pasa la cuenta que actúa.
- Nunca se escribe directo en `BITACORA`, `BITACORA_DETALLE` ni `LOG_ACCESO`: se usan `bitacora_registrar`, `bitacora_detalle_registrar` y `registrar_acceso`.
- Las cuentas, las instituciones y la bitácora no se borran.
- Una rama por módulo (`feature/m1-autenticacion`, etc.); `main` siempre funciona.

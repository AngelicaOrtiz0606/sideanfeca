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

   Las contraseñas de todas las cuentas deben tener al menos 8 caracteres, con letras y números.

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

## Reglas para programar

- La lógica de negocio vive en la base de datos. Laravel llama a las funciones SQL y les pasa la cuenta que actúa.
- Nunca se escribe directo en `BITACORA`, `BITACORA_DETALLE` ni `LOG_ACCESO`: se usan `bitacora_registrar`, `bitacora_detalle_registrar` y `registrar_acceso`.
- Las cuentas, las instituciones y la bitácora no se borran.
- Una rama por módulo (`feature/m1-autenticacion`, etc.); `main` siempre funciona.

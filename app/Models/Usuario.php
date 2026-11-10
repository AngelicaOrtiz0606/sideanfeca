<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Cuenta de acceso al sistema (tabla USUARIO).
 *
 * Reglas del esquema que este modelo respeta:
 * - La cuenta nunca se borra: se desactiva o se anonimiza (ARCO).
 * - Una cuenta activa siempre tiene persona.
 * - El correo no se repite, sin importar mayúsculas.
 *
 * No hay asignación masiva: las cuentas se crean y cambian solo desde
 * servicios y comandos explícitos.
 */
class Usuario extends Authenticatable
{
    /**
     * Nombres de los privilegios activos, leídos una sola vez por petición.
     *
     * @var array<int, string>|null
     */
    private ?array $privilegiosActivos = null;

    // Nombre real de la tabla y de sus columnas (en minúsculas, como las crea PostgreSQL)
    protected $table = 'usuario';

    protected $primaryKey = 'usua_id_usuario';

    // Nuestra tabla no tiene created_at ni updated_at
    public $timestamps = false;

    // Nunca mostrar la contraseña en respuestas o registros
    protected $hidden = ['usua_contrasenia'];

    protected function casts(): array
    {
        return [
            'usua_activo' => 'boolean',
            'usua_fecha_registro' => 'datetime',
            // Si se asigna una contraseña en texto, se guarda ya cifrada
            'usua_contrasenia' => 'hashed',
        ];
    }

    /**
     * Persona dueña de la cuenta. Es null solo en cuentas anonimizadas.
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'usua_id_persona', 'pers_id_persona');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'usua_id_rol', 'rol_id_rol');
    }

    /**
     * Busca por correo sin importar mayúsculas ni espacios al inicio o al final.
     * Usa el índice único USUA_CORREO_UQ, que está sobre lower(USUA_CORREO).
     */
    public function scopePorCorreo(Builder $consulta, string $correo): Builder
    {
        return $consulta->whereRaw('lower(usua_correo) = lower(?)', [trim($correo)]);
    }

    public function estaActiva(): bool
    {
        return $this->usua_activo === true;
    }

    /**
     * ¿La cuenta tiene este privilegio? Mismo criterio que la función SQL
     * cuenta_tiene_privilegio: cuenta activa y privilegio activo en su rol.
     * En M2 esta revisión se expone como Gates de Laravel.
     */
    public function tienePrivilegio(string $privilegio): bool
    {
        return in_array($privilegio, $this->nombresDePrivilegios(), true);
    }

    /**
     * @return array<int, string>
     */
    public function nombresDePrivilegios(): array
    {
        if (! $this->estaActiva()) {
            return [];
        }

        return $this->privilegiosActivos ??= $this->rol()->first()?->privilegios()->pluck('priv_nombre')->all() ?? [];
    }

    /**
     * Perfil que se muestra en pantalla: "Superadmin" o "Admin".
     */
    public function etiquetaPerfil(): string
    {
        return $this->rol?->rol_nombre === Rol::SUPERADMINISTRADOR ? 'Superadmin' : 'Admin';
    }

    // Laravel debe usar nuestra columna de contraseña
    public function getAuthPasswordName(): string
    {
        return 'usua_contrasenia';
    }

    public function getAuthPassword(): string
    {
        return $this->usua_contrasenia;
    }

    /**
     * La tabla no tiene columna para "Recordarme", así que esa función
     * queda desactivada. Con el nombre vacío, Laravel no lee ni escribe
     * ningún token de sesión persistente.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }
}

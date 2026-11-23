<?php

namespace App\Services\Usuarios;

use App\Models\Rol;
use App\Models\Usuario;
use App\Services\Catalogos\BitacoraCatalogo;
use App\Support\FuncionSql;
use App\Support\TextoCatalogo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Cuentas de usuario (solo el superadministrador): cada cuenta es de una
 * persona. Su formulario es más corto que el de Personas: nombre, apellidos,
 * género y CURP (obligatoria aquí; solo la ve el sistema, nunca el sitio).
 *
 * - Si la persona ya está registrada (por ejemplo, en los directorios), la
 *   cuenta se le agrega a ella en lugar de duplicarla.
 * - Las cuentas no se borran: se desactivan (cambiar_activo_usuario, que
 *   también impide desactivar la propia).
 * - Un movimiento en la bitácora por acción; los datos personales solo
 *   nombran el campo, sin su valor.
 */
class UsuarioService
{
    use BitacoraCatalogo;

    /** Campos de la persona que se editan desde aquí: columna => nombre en la bitácora. */
    private const CAMPOS_PERSONA = [
        'pers_nombre' => 'Nombre',
        'pers_primer_apellido' => 'Primer apellido',
        'pers_segundo_apellido' => 'Segundo apellido',
        'pers_curp' => 'CURP',
        'pers_genero' => 'Género',
    ];

    /**
     * Las cuentas con su persona, rol, estado y último inicio de sesión.
     * Las anonimizadas por ARCO (sin persona) no se listan.
     *
     * @param  array{texto?: ?string, rol?: ?int, estado?: ?string}  $filtros
     * @return Collection<int, object>
     */
    public function listado(array $filtros = []): Collection
    {
        $cuentas = $this->consulta()
            ->when($filtros['rol'] ?? null, fn ($q, int $rol) => $q->where('u.usua_id_rol', $rol))
            ->when($filtros['estado'] ?? null, fn ($q, string $estado) => $q->where('u.usua_activo', $estado === 'activas'))
            ->addSelect(['ultimo_acceso' => DB::table('log_acceso')
                ->whereColumn('loga_id_usuario', 'u.usua_id_usuario')
                ->where('loga_resultado', 'Exitoso')
                ->selectRaw('max(loga_fecha_hora)')])
            ->orderBy('p.pers_primer_apellido')
            ->orderBy('p.pers_nombre')
            ->get();

        $clave = TextoCatalogo::clave((string) ($filtros['texto'] ?? ''));

        return $clave === '' ? $cuentas : $cuentas->filter(fn (object $c) => str_contains(TextoCatalogo::clave("{$c->nombre} {$c->correo}"), $clave))->values();
    }

    public function buscar(int $id): ?object
    {
        return $this->consulta()->where('u.usua_id_usuario', $id)->first();
    }

    /**
     * @return Collection<int, object>
     */
    public function roles(): Collection
    {
        // El administrador primero: es el rol de casi todas las cuentas
        return DB::table('rol')->orderByRaw('rol_nombre = ? desc', [Rol::ADMINISTRADOR])->orderBy('rol_id_rol')
            ->get(['rol_id_rol as id', 'rol_nombre as nombre']);
    }

    /** Una persona sin cuenta, para crearle una con sus datos (por ejemplo, alguien de los directorios). */
    public function personaSinCuenta(int $persona): ?object
    {
        return DB::table('persona as p')
            ->where('p.pers_id_persona', $persona)
            ->where('p.pers_oculta_admin', false)
            ->whereNotExists(fn ($q) => $q->from('usuario')->whereColumn('usua_id_persona', 'p.pers_id_persona'))
            ->first(['p.pers_id_persona as id', 'p.pers_nombre as nombre', 'p.pers_primer_apellido as primer_apellido',
                'p.pers_segundo_apellido as segundo_apellido', 'p.pers_curp as curp', 'p.pers_genero as genero']);
    }

    /**
     * Crea la cuenta (y la persona, si no se eligió una ya registrada).
     *
     * @param  array{nombre: string, primer_apellido: string, segundo_apellido: ?string, curp: string, genero: string, correo: string, rol: int, contrasenia: string}  $datos
     */
    public function registrar(Usuario $actor, array $datos, ?int $persona = null): int
    {
        return DB::transaction(function () use ($actor, $datos, $persona): int {
            if ($persona === null) {
                $persona = (int) DB::table('persona')->insertGetId($this->columnasPersona($datos), 'pers_id_persona');
            } else {
                DB::table('persona')->where('pers_id_persona', $persona)->update($this->columnasPersona($datos));
            }

            $id = (int) DB::table('usuario')->insertGetId([
                'usua_id_persona' => $persona,
                'usua_id_rol' => $datos['rol'],
                'usua_correo' => $datos['correo'],
                'usua_contrasenia' => Hash::make($datos['contrasenia']),
                'usua_activo' => true,
            ], 'usua_id_usuario');

            $this->bitacora($actor, 'Registro', 'USUARIO', $id);

            return $id;
        });
    }

    /**
     * Guarda los cambios de la cuenta y de los datos de su persona. La
     * contraseña solo cambia si se escribió una nueva.
     *
     * @param  array{nombre: string, primer_apellido: string, segundo_apellido: ?string, curp: string, genero: string, correo: string, rol: int, contrasenia: ?string}  $datos
     * @return bool si hubo algún cambio
     */
    public function actualizar(Usuario $actor, int $id, array $datos): bool
    {
        return DB::transaction(function () use ($actor, $id, $datos): bool {
            $actual = DB::table('usuario as u')
                ->join('persona as p', 'p.pers_id_persona', '=', 'u.usua_id_persona')
                ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
                ->where('u.usua_id_usuario', $id)
                ->lockForUpdate()
                ->first(['u.*', 'p.*', 'r.rol_nombre']);

            $nuevos = $this->columnasPersona($datos);
            $cambios = collect(self::CAMPOS_PERSONA)
                ->filter(fn (string $campo, string $columna) => (string) $actual->{$columna} !== (string) $nuevos[$columna])
                // Datos personales: la bitácora solo nombra el campo, sin su valor
                ->mapWithKeys(fn (string $campo) => [$campo => [null, null]])
                ->all();

            if (mb_strtolower($actual->usua_correo) !== mb_strtolower($datos['correo'])) {
                $cambios['Correo'] = [null, null];
            }
            if ((int) $actual->usua_id_rol !== $datos['rol']) {
                $cambios['Rol'] = [$actual->rol_nombre, DB::table('rol')->where('rol_id_rol', $datos['rol'])->value('rol_nombre')];
            }
            if (filled($datos['contrasenia'])) {
                $cambios['Contraseña'] = [null, null];
            }

            if ($cambios === []) {
                return false;
            }

            DB::table('persona')->where('pers_id_persona', $actual->usua_id_persona)->update($nuevos);
            DB::table('usuario')->where('usua_id_usuario', $id)->update(array_filter([
                'usua_correo' => $datos['correo'],
                'usua_id_rol' => $datos['rol'],
                'usua_contrasenia' => filled($datos['contrasenia']) ? Hash::make($datos['contrasenia']) : null,
            ]));

            $idBitacora = $this->bitacora($actor, 'Modificacion', 'USUARIO', $id);
            foreach ($cambios as $campo => [$anterior, $nuevo]) {
                $this->detalle($idBitacora, $id, $campo, $anterior, $nuevo);
            }

            return true;
        });
    }

    /**
     * Activa o desactiva con la función de la base (registra la bitácora y no
     * deja desactivar la propia cuenta).
     *
     * @return array{0: ?string, 1: ?string} [mensaje, error]
     */
    public function cambiarEstado(Usuario $actor, int $id, bool $activa): array
    {
        return FuncionSql::llamar('cambiar_activo_usuario', [$actor->usua_id_usuario, $id, $activa]);
    }

    /**
     * La propia cuenta cambia su contraseña (con su bitácora, sin el valor).
     */
    public function cambiarContrasenia(Usuario $cuenta, string $nueva): void
    {
        DB::transaction(function () use ($cuenta, $nueva): void {
            DB::table('usuario')->where('usua_id_usuario', $cuenta->usua_id_usuario)->update(['usua_contrasenia' => Hash::make($nueva)]);
            $idBitacora = $this->bitacora($cuenta, 'Modificacion', 'USUARIO', $cuenta->usua_id_usuario);
            $this->detalle($idBitacora, $cuenta->usua_id_usuario, 'Contraseña', null, null);
        });
    }

    /**
     * @param  array{nombre: string, primer_apellido: string, segundo_apellido: ?string, curp: string, genero: string}  $datos
     * @return array<string, ?string>
     */
    private function columnasPersona(array $datos): array
    {
        return [
            'pers_nombre' => $datos['nombre'],
            'pers_primer_apellido' => $datos['primer_apellido'],
            'pers_segundo_apellido' => $datos['segundo_apellido'],
            'pers_curp' => $datos['curp'],
            'pers_genero' => $datos['genero'],
        ];
    }

    private function consulta(): Builder
    {
        return DB::table('usuario as u')
            ->join('persona as p', 'p.pers_id_persona', '=', 'u.usua_id_persona')
            ->join('rol as r', 'r.rol_id_rol', '=', 'u.usua_id_rol')
            ->select([
                'u.usua_id_usuario as id', 'u.usua_id_persona as persona_id', 'u.usua_correo as correo', 'u.usua_activo as activa',
                'u.usua_id_rol as rol_id', 'r.rol_nombre as rol', 'u.usua_fecha_registro as registro',
                'p.pers_nombre as nombre_pila', 'p.pers_primer_apellido as primer_apellido', 'p.pers_segundo_apellido as segundo_apellido',
                'p.pers_curp as curp', 'p.pers_genero as genero',
                DB::raw("concat_ws(' ', p.pers_nombre, p.pers_primer_apellido, p.pers_segundo_apellido) as nombre"),
                'en_directorio' => DB::table('designacion')->whereColumn('desi_id_persona', 'u.usua_id_persona')->selectRaw('count(*) > 0'),
            ]);
    }
}

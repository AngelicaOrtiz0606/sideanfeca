<?php

namespace App\Http\Requests\Usuarios;

use App\Http\Requests\Personas\PersonaRequest;
use App\Models\Usuario;
use App\Rules\TextoSeguro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Nuevo usuario y Editar usuario.
 * - Persona: nombre, apellidos, género y CURP (obligatoria para las cuentas;
 *   no se repite entre personas).
 * - Cuenta: correo (no se repite, sin importar mayúsculas), rol y, solo al
 *   crear, una contraseña temporal. Al editar no se ve ni se cambia: es solo
 *   de su dueño (Cambiar contraseña, o el enlace para recuperarla).
 * - Nadie cambia su propio rol (para no quedarse sin acceso por error).
 */
class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'curp' => is_string($this->input('curp')) ? strtoupper(preg_replace('/\s+/', '', $this->input('curp'))) : $this->input('curp'),
            'correo' => is_string($this->input('correo')) ? trim($this->input('correo')) : $this->input('correo'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cuenta = $this->cuenta();
        $persona = $cuenta !== null
            ? (int) Usuario::query()->whereKey($cuenta)->value('usua_id_persona')
            : ($this->integer('persona') ?: null);

        return [
            'persona' => ['nullable', 'integer'],
            'nombre' => ['required', 'string', new TextoSeguro, 'max:60'],
            'primer_apellido' => ['required', 'string', new TextoSeguro, 'max:45'],
            'segundo_apellido' => ['nullable', 'string', new TextoSeguro, 'max:45'],
            'curp' => ['required', 'string', 'size:18', 'regex:'.PersonaRequest::CURP,
                Rule::unique('persona', 'pers_curp')->ignore($persona, 'pers_id_persona')],
            'genero' => ['required', Rule::in(['F', 'M'])],
            'correo' => ['required', 'string', 'max:254', 'email',
                function (string $campo, mixed $valor, \Closure $falla) use ($cuenta): void {
                    if (Usuario::porCorreo((string) $valor)->when($cuenta, fn ($q, int $id) => $q->whereKeyNot($id))->exists()) {
                        $falla('Ese correo ya es de otra cuenta.');
                    }
                }],
            'rol' => ['required', 'integer', Rule::exists('rol', 'rol_id_rol')],
            // Solo al crear: después, la contraseña es solo de su dueño
            ...($cuenta === null ? ['contrasenia' => ['required', 'string', 'confirmed', Password::defaults()]] : []),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $cuenta = $this->cuenta();
            if ($cuenta !== null && $cuenta === $this->user()->usua_id_usuario
                && $this->integer('rol') !== (int) $this->user()->usua_id_rol) {
                $validator->errors()->add('rol', 'No puedes cambiar tu propio rol. Pídeselo a otro superadministrador.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre.',
            'primer_apellido.required' => 'Escribe el primer apellido.',
            'curp.required' => 'Escribe la CURP: es obligatoria para las cuentas de usuario.',
            'curp.size' => 'La CURP tiene 18 caracteres.',
            'curp.regex' => 'La CURP no tiene un formato válido. Revísala: 4 letras, 6 números (fecha), H o M, 5 letras y 2 caracteres finales.',
            'curp.unique' => 'Esa CURP ya pertenece a otra persona. Búscala arriba para crearle su cuenta.',
            'genero.required' => 'Elige el género.',
            'correo.required' => 'Escribe el correo con el que va a entrar.',
            'correo.email' => 'Escribe un correo válido, por ejemplo nombre@dominio.mx.',
            'rol.required' => 'Elige el rol.',
            'rol.exists' => 'Elige un rol de la lista.',
            'contrasenia.required' => 'Escribe una contraseña temporal.',
            'contrasenia.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['contrasenia' => 'contraseña'];
    }

    /**
     * @return array{nombre: string, primer_apellido: string, segundo_apellido: ?string, curp: string, genero: string, correo: string, rol: int, contrasenia: ?string}
     */
    public function datos(): array
    {
        return [
            'nombre' => trim($this->input('nombre')),
            'primer_apellido' => trim($this->input('primer_apellido')),
            'segundo_apellido' => trim((string) $this->input('segundo_apellido')) ?: null,
            'curp' => $this->input('curp'),
            'genero' => $this->input('genero'),
            'correo' => $this->input('correo'),
            'rol' => $this->integer('rol'),
            'contrasenia' => $this->cuenta() === null ? (string) $this->input('contrasenia') : null,
        ];
    }

    /** La cuenta que se edita (null al crear). */
    private function cuenta(): ?int
    {
        $cuenta = $this->route('usuario');

        return $cuenta === null ? null : (int) $cuenta;
    }
}

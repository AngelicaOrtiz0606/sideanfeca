<?php

namespace App\Http\Requests\Designaciones;

use App\Http\Requests\Personas\ValidaDesignaciones;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

/**
 * "Agregar designación" en la ficha de una persona ya registrada: una o
 * varias filas (las reglas están en ValidaDesignaciones). La pertenencia es
 * la que la persona ya tiene; un cargo institucional va a su institución.
 */
class AgregarDesignacionesRequest extends FormRequest
{
    use ValidaDesignaciones;

    private ?object $persona = null;

    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta (can:gestionar_designaciones)
    }

    protected function prepareForValidation(): void
    {
        $this->filtrarDesignaciones();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->reglasDesignaciones();
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $persona = $this->persona();
                $pertenencia = $persona?->pers_id_institucion_educativa ? 'institucion' : ($persona?->pers_id_organizacion ? 'organizacion' : 'ninguna');
                $this->revisarDesignaciones($validator, $pertenencia, $this->institucion(), 'designaciones');
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mensajesDesignaciones();
    }

    public function institucion(): ?int
    {
        $institucion = $this->persona()?->pers_id_institucion_educativa;

        return $institucion === null ? null : (int) $institucion;
    }

    private function persona(): ?object
    {
        return $this->persona ??= DB::table('persona')->where('pers_id_persona', (int) $this->route('persona'))->first();
    }
}

<?php

namespace App\Http\Requests\Catalogos;

use App\Models\CoordinacionNacional;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

/**
 * Registro y edición de una coordinación nacional: nombre (sin repetirse,
 * sin importar mayúsculas) y descripción opcional.
 */
class CoordinacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta (can:gestionar_coordinaciones)
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:70'],
            'descripcion' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('nombre')) {
                    return;
                }

                $repetido = DB::table('coordinacion_nacional')
                    ->whereRaw('lower(cona_nombre) = lower(?)', [$this->nombre()])
                    ->when($this->coordinacion(), fn ($q, CoordinacionNacional $c) => $q->where('cona_id_coordinacion_nacional', '!=', $c->cona_id_coordinacion_nacional))
                    ->exists();

                if ($repetido) {
                    $validator->errors()->add('nombre', 'Ya existe una coordinación con ese nombre.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Escribe el nombre de la coordinación.',
            'nombre.max' => 'El nombre de la coordinación puede tener hasta 70 caracteres.',
            'descripcion.max' => 'La descripción puede tener hasta 100 caracteres.',
        ];
    }

    public function nombre(): string
    {
        return trim((string) $this->input('nombre'));
    }

    public function descripcion(): ?string
    {
        $descripcion = trim((string) $this->input('descripcion'));

        return $descripcion === '' ? null : $descripcion;
    }

    private function coordinacion(): ?CoordinacionNacional
    {
        $coordinacion = $this->route('coordinacion');

        return $coordinacion instanceof CoordinacionNacional ? $coordinacion : null;
    }
}

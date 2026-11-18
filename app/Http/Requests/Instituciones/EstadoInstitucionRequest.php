<?php

namespace App\Http\Requests\Instituciones;

use App\Services\Instituciones\InstitucionService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Desactivar (deja de participar el…) o activar (vuelve a participar desde…)
 * una institución, con la fecha que cierra o abre su periodo de participación.
 */
class EstadoInstitucionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'activa' => ['required', 'boolean'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $limites = app(InstitucionService::class)->limitesDeFecha((int) $this->route('institucion'));
                $fecha = (string) $this->input('fecha');

                if (! $this->boolean('activa') && $limites['desde'] !== null && $fecha < $limites['desde']) {
                    $validator->errors()->add('fecha', 'La fecha no puede ser anterior al inicio de su participación actual ('.$this->formato($limites['desde']).').');
                }

                if ($this->boolean('activa') && $limites['ultimo_fin'] !== null && $fecha < $limites['ultimo_fin']) {
                    $validator->errors()->add('fecha', 'La fecha no puede ser anterior a cuando dejó de participar ('.$this->formato($limites['ultimo_fin']).').');
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
            'fecha.required' => 'Escribe la fecha.',
            'fecha.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }

    private function formato(string $fecha): string
    {
        return date('d/m/Y', strtotime($fecha));
    }
}

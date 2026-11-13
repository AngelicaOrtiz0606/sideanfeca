<?php

namespace App\Http\Requests\Instituciones;

use App\Rules\NoRepetidoEnCatalogo;
use App\Services\Instituciones\InstitucionService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cambio de participación con historial: una observadora pasa a afiliada,
 * con su número y desde cuándo. Una afiliada nunca vuelve a ser observadora
 * y una matriz no cambia. Debe estar activa, y la fecha no puede ser
 * anterior al inicio de su periodo actual ni futura.
 */
class ParticipacionRequest extends FormRequest
{
    private ?object $institucion = null;

    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = (int) $this->route('institucion');

        return [
            'participacion' => ['required', Rule::in(['Afiliada'])],
            'desde' => ['required', 'date', 'before_or_equal:today'],
            'numero' => [
                'required', 'string', 'regex:/^[0-9]{7}$/',
                new NoRepetidoEnCatalogo('institucion_educativa', 'ined_num_afiliacion', 'ined_id_institucion_educativa', $id, 'El número :existente ya pertenece a otra institución.'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $servicio = app(InstitucionService::class);
                $institucion = $this->institucion();

                if ($institucion === null) {
                    return;
                }

                $motivo = $servicio->motivoParaNoCambiarParticipacion($institucion);
                if ($motivo !== null) {
                    $validator->errors()->add('participacion', $motivo);

                    return;
                }

                $inicio = $servicio->iniciosVigentes((int) $institucion->id)['participacion'];
                if (! $validator->errors()->has('desde') && $inicio !== null && (string) $this->input('desde') < $inicio) {
                    $validator->errors()->add('desde', 'La fecha no puede ser anterior a cuando empezó como observadora ('.date('d/m/Y', strtotime($inicio)).').');
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
            'participacion.required' => 'Solo una observadora puede pasar a afiliada.',
            'participacion.in' => 'Solo una observadora puede pasar a afiliada.',
            'desde.required' => 'Escribe desde cuándo es afiliada.',
            'desde.date' => 'Escribe una fecha válida.',
            'desde.before_or_equal' => 'La fecha no puede ser futura.',
            'numero.required' => 'Una afiliada necesita su número de afiliación de 7 dígitos.',
            'numero.regex' => 'El número de afiliación tiene exactamente 7 dígitos.',
        ];
    }

    public function institucion(): ?object
    {
        return $this->institucion ??= app(InstitucionService::class)->buscar((int) $this->route('institucion'));
    }
}

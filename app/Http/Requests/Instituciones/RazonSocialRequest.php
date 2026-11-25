<?php

namespace App\Http\Requests\Instituciones;

use App\Rules\TextoSeguro;
use App\Services\Instituciones\InstitucionService;
use App\Support\TextoCatalogo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

/**
 * Cambio de razón social con historial: la nueva razón social y desde cuándo
 * se usa. La fecha no puede ser anterior a cuando empezó la vigente (así los
 * periodos no se traslapan) ni futura.
 */
class RazonSocialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'razon_social' => ['required', 'string', new TextoSeguro, 'max:200'],
            'desde' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $id = (int) $this->route('institucion');

                if (! $validator->errors()->has('razon_social')) {
                    $vigente = (string) DB::table('institucion_nombre')->where('instn_id_institucion', $id)->whereNull('instn_fecha_fin')->value('instn_nombre');
                    if (TextoCatalogo::clave($vigente) === TextoCatalogo::clave((string) $this->input('razon_social'))) {
                        $validator->errors()->add('razon_social', 'Es la misma razón social que la vigente. Para corregir cómo está escrita, usa Editar.');
                    } else {
                        $institucion = DB::table('institucion_educativa as i')->join('tipo_institucion_educativa as t', 't.tiie_id_tipo_institucion', '=', 'i.ined_id_tipo_institucion')
                            ->where('i.ined_id_institucion_educativa', $id)->first(['i.ined_id_institucion_padre as padre', 't.tiie_nombre as tipo']);
                        $padre = $institucion?->tipo === 'Universidad' ? null : ($institucion?->padre === null ? null : (int) $institucion->padre);
                        $repetida = app(InstitucionService::class)->nombreRepetido(TextoCatalogo::limpiar((string) $this->input('razon_social')), $padre, $id);
                        if ($repetida !== null) {
                            $validator->errors()->add('razon_social', $padre === null ? "Ya existe la universidad «{$repetida}»." : "Ya existe «{$repetida}» en esa universidad.");
                        }
                    }
                }

                $inicio = app(InstitucionService::class)->iniciosVigentes($id)['razon_social'];
                if (! $validator->errors()->has('desde') && $inicio !== null && (string) $this->input('desde') < $inicio) {
                    $validator->errors()->add('desde', 'La fecha no puede ser anterior a cuando empezó la razón social vigente ('.date('d/m/Y', strtotime($inicio)).').');
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
            'razon_social.required' => 'Escribe la nueva razón social.',
            'razon_social.max' => 'La razón social puede tener hasta 200 caracteres.',
            'desde.required' => 'Escribe desde cuándo se usa.',
            'desde.date' => 'Escribe una fecha válida.',
            'desde.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }

    public function razonSocial(): string
    {
        return TextoCatalogo::limpiar((string) $this->validated('razon_social'));
    }
}

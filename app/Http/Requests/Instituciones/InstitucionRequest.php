<?php

namespace App\Http\Requests\Instituciones;

use App\Rules\NoRepetidoEnCatalogo;
use App\Rules\TextoSeguro;
use App\Services\Instituciones\InstitucionService;
use App\Support\TextoCatalogo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Registro y edición de una institución (sección 4 del documento):
 * - Matriz: solo universidades; sin número ni universidad padre; domicilio y zona opcionales.
 * - Afiliada: número de 7 dígitos sin repetirse; domicilio y zona obligatorios.
 * - Observadora: sin número; domicilio y zona obligatorios.
 * - Facultades y campus: deben pertenecer a una universidad.
 *
 * Al editar, la participación no se cambia aquí (tiene su propia acción con
 * historial): se usa la que la institución tiene hoy.
 *
 * La consulta edita por secciones (_seccion): cada ventana envía solo sus
 * campos y el resto se toma de la base, así una sección nunca cambia otra.
 */
class InstitucionRequest extends FormRequest
{
    /** Campos de cada sección de la consulta */
    public const SECCIONES = [
        'identificacion' => ['tipo', 'universidad', 'sector', 'numero'],
        'ubicacion' => ['colonia', 'calle', 'num_ext', 'num_int', 'zona'],
        'web' => ['sitio_web'],
        'razon' => ['razon_social'],
    ];

    private ?object $actual = null;

    protected function prepareForValidation(): void
    {
        $seccion = (string) $this->input('_seccion');
        $actual = $this->actual();

        if ($actual === null || ! array_key_exists($seccion, self::SECCIONES)) {
            return;
        }

        $domicilio = $actual->domicilio;
        $actuales = [
            'tipo' => $actual->tipo,
            'universidad' => $actual->universidad_id,
            'sector' => $actual->sector,
            'numero' => $actual->numero,
            'colonia' => $domicilio?->colonia_id,
            'calle' => $domicilio?->calle,
            'num_ext' => $domicilio?->num_ext,
            'num_int' => $domicilio?->num_int,
            'zona' => $actual->zona_id,
            'sitio_web' => $actual->sitio_web,
            'razon_social' => $actual->nombre,
        ];

        // Lo que no es de la sección se toma de la base (aunque se haya enviado)
        $this->merge(array_diff_key($actuales, array_flip(self::SECCIONES[$seccion])));
    }

    /**
     * La institución tal como está hoy (solo al editar).
     */
    public function actual(): ?object
    {
        $id = $this->idInstitucion();

        return $id === null ? null : ($this->actual ??= app(InstitucionService::class)->buscar($id));
    }

    public function seccion(): ?string
    {
        $seccion = (string) $this->input('_seccion');

        return array_key_exists($seccion, self::SECCIONES) ? $seccion : null;
    }

    public function authorize(): bool
    {
        return true; // El privilegio ya lo revisa la ruta (can:gestionar_instituciones)
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $id = $this->idInstitucion();
        $participacion = $this->participacion();
        $conDomicilio = $participacion !== 'Matriz' || $this->filled('colonia') || $this->filled('calle') || $this->filled('num_ext');

        return [
            'tipo' => ['required', Rule::in(InstitucionService::TIPOS)],
            'universidad' => [
                Rule::requiredIf(in_array($this->input('tipo'), ['Facultad', 'Campus'], true)),
                Rule::prohibitedIf($this->input('tipo') === 'Universidad'),
                'nullable', 'integer',
                Rule::exists('institucion_educativa', 'ined_id_institucion_educativa')
                    ->where('ined_id_tipo_institucion', DB::table('tipo_institucion_educativa')->where('tiie_nombre', 'Universidad')->value('tiie_id_tipo_institucion')),
            ],
            'participacion' => $id === null ? ['required', Rule::in(InstitucionService::PARTICIPACIONES)] : ['prohibited'],
            'desde' => $id === null ? ['required', 'date', 'after_or_equal:1900-01-01', 'before_or_equal:today'] : ['prohibited'],
            'numero_modo' => ['nullable', Rule::in(['automatico', 'manual'])],
            'numero' => [
                Rule::excludeIf($this->numeroAutomatico()),
                Rule::requiredIf($participacion === 'Afiliada'),
                Rule::prohibitedIf($participacion !== 'Afiliada' && $participacion !== null),
                'nullable', 'string', 'regex:/^[0-9]{7}$/',
                new NoRepetidoEnCatalogo('institucion_educativa', 'ined_num_afiliacion', 'ined_id_institucion_educativa', $id, 'El número :existente ya pertenece a otra institución.'),
            ],
            'razon_social' => ['required', 'string', new TextoSeguro, 'max:200'],
            'sector' => ['required', Rule::in(array_keys(InstitucionService::SECTORES))],
            'sitio_web' => ['nullable', 'string', 'max:200', 'url:http,https'],
            'zona' => [Rule::requiredIf($participacion !== 'Matriz'), 'nullable', 'integer', Rule::exists('zona_regional', 'zore_id_zona_regional')],
            'colonia' => [Rule::requiredIf($conDomicilio), 'nullable', 'integer', Rule::exists('colonia', 'colo_id_colonia')],
            'calle' => [Rule::requiredIf($conDomicilio), 'nullable', 'string', new TextoSeguro, 'max:60'],
            'num_ext' => [Rule::requiredIf($conDomicilio), 'nullable', 'string', new TextoSeguro, 'max:10'],
            'num_int' => ['nullable', 'string', new TextoSeguro, 'max:10'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->participacion() === 'Matriz' && $this->input('tipo') !== 'Universidad' && ! $validator->errors()->has('tipo')) {
                    $validator->errors()->add('tipo', 'Solo una universidad puede ser matriz.');
                }

                $id = $this->idInstitucion();
                if ($id !== null && (int) $this->input('universidad') === $id) {
                    $validator->errors()->add('universidad', 'Una institución no puede pertenecer a sí misma.');
                }

                $actual = $this->actual();
                if ($actual !== null && $this->input('tipo') !== $actual->tipo && ! $validator->errors()->has('tipo')) {
                    $motivo = app(InstitucionService::class)->motivoParaNoCambiarTipo($actual);
                    if ($motivo !== null) {
                        $validator->errors()->add('tipo', $motivo);
                    }
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
            'tipo.required' => 'Elige el tipo de institución.',
            'universidad.required' => 'Elige la universidad a la que pertenece la facultad o el campus.',
            'universidad.prohibited' => 'Una universidad no pertenece a otra universidad.',
            'universidad.exists' => 'Elige una universidad de la lista.',
            'participacion.required' => 'Elige la participación: matriz, afiliada u observadora.',
            'desde.required' => 'Escribe desde cuándo participa.',
            'desde.before_or_equal' => 'La fecha desde la que participa no puede ser futura.',
            'numero.required' => 'Una afiliada necesita su número de afiliación de 7 dígitos.',
            'numero.regex' => 'El número de afiliación tiene exactamente 7 dígitos.',
            'numero.prohibited' => 'Solo las afiliadas tienen número de afiliación.',
            'razon_social.required' => 'Escribe la razón social.',
            'razon_social.max' => 'La razón social puede tener hasta 200 caracteres.',
            'sector.required' => 'Elige el sector: pública o privada.',
            'sitio_web.url' => 'El sitio web debe empezar con http:// o https://, por ejemplo https://www.unam.mx.',
            'zona.required' => 'Elige la zona regional.',
            'colonia.required' => 'Escribe el código postal y elige la colonia.',
            'colonia.exists' => 'Elige una colonia de la lista.',
            'calle.required' => 'Escribe la calle.',
            'num_ext.required' => 'Escribe el número exterior.',
        ];
    }

    /**
     * Datos listos para InstitucionService (textos sin espacios de sobra).
     *
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        $datos = $this->validated();
        $conDomicilio = ! empty($datos['colonia']);

        return [
            'tipo' => $datos['tipo'],
            'universidad' => in_array($datos['tipo'], ['Facultad', 'Campus'], true) ? (int) $datos['universidad'] : null,
            'participacion' => $datos['participacion'] ?? null,
            'desde' => $datos['desde'] ?? null,
            // Null en una afiliada nueva = el servicio asigna el siguiente consecutivo al guardar
            'numero' => $this->participacion() === 'Afiliada' && ! $this->numeroAutomatico() ? $datos['numero'] : null,
            'razon_social' => TextoCatalogo::limpiar($datos['razon_social']),
            'sector' => $datos['sector'],
            'sitio_web' => ($datos['sitio_web'] ?? null) ?: null,
            'zona' => isset($datos['zona']) ? (int) $datos['zona'] : null,
            'domicilio' => $conDomicilio ? [
                'colonia' => (int) $datos['colonia'],
                'calle' => TextoCatalogo::limpiar($datos['calle']),
                'num_ext' => TextoCatalogo::limpiar($datos['num_ext']),
                'num_int' => TextoCatalogo::limpiar($datos['num_int'] ?? null) ?: null,
            ] : null,
        ];
    }

    /**
     * Participación que manda: la elegida al registrar o la actual al editar.
     */
    public function participacion(): ?string
    {
        $id = $this->idInstitucion();

        if ($id === null) {
            $elegida = $this->input('participacion');

            return in_array($elegida, InstitucionService::PARTICIPACIONES, true) ? $elegida : null;
        }

        return DB::table('institucion_educativa')->where('ined_id_institucion_educativa', $id)->value('ined_participacion');
    }

    /**
     * Al registrar una afiliada, el número lo asigna el sistema (año + zona +
     * consecutivo) salvo que se elija "Escribir el número" (numero_modo=manual).
     * Si no se envía el modo, se toma como manual cuando ya trae un número.
     */
    public function numeroAutomatico(): bool
    {
        if ($this->idInstitucion() !== null || $this->participacion() !== 'Afiliada') {
            return false;
        }

        $modo = $this->input('numero_modo');

        return $modo === 'automatico' || ($modo === null && blank($this->input('numero')));
    }

    private function idInstitucion(): ?int
    {
        $id = $this->route('institucion');

        return $id === null ? null : (int) $id;
    }
}

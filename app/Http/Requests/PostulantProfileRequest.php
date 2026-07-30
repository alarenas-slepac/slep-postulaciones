<?php

namespace App\Http\Requests;

use App\Rules\ChileanPhone;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// ✅ Asume que crearás este modelo para el mantenedor
use App\Models\AreaDesempeno;

class PostulantProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->id() === (int) $this->route('user');
    }

    public function rules(): array
    {
        $regionCodes = array_keys((array) config('chile.regiones', []));
        $generos = ['masculino', 'femenino', 'otro'];
        $pronombres = ['él', 'ella', 'elle', 'él/ella', 'ella/elle', 'él/elle'];

        $estamento = (string) $this->input('estamento');

        // Catálogos (los de bancos/afp/salud se mantienen como los tenías)
        $nivelesTodos     = ['Enseñanza Media', 'Enseñanza Media Laboral', 'Enseñanza Media TP', 'Técnico Nivel Superior', 'Universitaria'];
        $nivelesTP        = ['Enseñanza Media TP', 'Técnico Nivel Superior', 'Universitaria'];
        $nivelesSoloUniv  = ['Universitaria'];

        $afps = ['AFP Capital', 'AFP Cuprum', 'AFP Habitat', 'AFP Modelo', 'AFP PlanVital', 'AFP Provida', 'AFP Uno', 'IPS (ex-INP)', 'Nunca he cotizado'];
        $salud = ['FONASA', 'Isapre Banmédica', 'Isapre Colmena', 'Isapre Consalud', 'Isapre Cruz Blanca', 'Isapre Nueva Masvida', 'Isapre Vida Tres', 'Nunca he cotizado'];
        $bancos = [
            'Banco de Chile',
            'BancoEstado',
            'Scotiabank',
            'BCI',
            'Corpbanca',
            'Banco BICE',
            'Banco Santander',
            'Banco Itaú',
            'Banco Security',
            'Banco Falabella',
            'Banco Ripley',
            'Rabobank Chile',
            'Banco Consorcio',
            'Banco BBVA',
            'Coopeuch',
            'Tenpo',
            'Tapp Caja Los Andes',
            'Copec Pay',
            'American Express',
            'Mercado Pago',
        ];
        $tiposCuenta = ['Cuenta Corriente', 'Cuenta Vista', 'Cuenta RUT', 'Chequera Electrónica'];

        // -------------------------
        // Reglas base (comunes)
        // -------------------------
        $reglas = [
            // Personales
            'email_contacto'   => ['required', 'email', 'max:190'],
            'fecha_nacimiento' => ['required', 'date'],
            'direccion'        => ['required', 'string', 'max:190'],
            'region_code'      => ['required', Rule::in($regionCodes)],
            'comuna_id'        => ['required', 'integer', Rule::exists('communes', 'id')],
            'nacionalidad'     => ['required', 'string', 'max:80'],
            'telefono1'        => ['required', new ChileanPhone],
            'telefono2'        => ['nullable', new ChileanPhone],
            'genero'           => ['required', Rule::in($generos)],
            'pronombres'       => ['nullable', Rule::in($pronombres)],

            // Foto (opcional)
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],

            // Académicos base
            'estamento' => ['required', Rule::in(['docente', 'asistente'])],

            // ✅ Área de desempeño desde mantenedor (aplica a docente y asistente)
            'area_desempeno_id' => [
                'required',
                'integer',
                Rule::exists('areas_desempeno', 'id')->where(function ($q) use ($estamento) {
                    $q->where('activo', 1);
                    if ($estamento !== '') {
                        $q->where('estamento', $estamento);
                    }
                }),
            ],

            // SIEMPRE obligatorios
            'nivel_estudios'    => ['required', Rule::in($nivelesTodos)],
            'anios_experiencia' => ['required', 'integer', 'min:0', 'max:60'],

            // Semestres / Horas: SOLO si nivel = Universitaria
            'semestres' => [
                'nullable',
                'integer',
                'min:1',
                'max:40',
                Rule::requiredIf(fn() => $this->input('nivel_estudios') === 'Universitaria'),
            ],
            'horas_totales' => [
                'nullable',
                'integer',
                'min:1',
                'max:20000',
                Rule::requiredIf(fn() => $this->input('nivel_estudios') === 'Universitaria'),
            ],

            // Institución: visible en UI solo para TNS/Universitaria, pero NO obligatoria
            'institucion_titulo' => ['nullable', 'string', 'max:190'],

            // Lugares de desempeño (al menos 1)
            'lugares'   => ['required', 'array', 'min:1'],
            'lugares.*' => ['integer', Rule::exists('communes', 'id')],
        ];

        // -------------------------
        // Reglas específicas por estamento/área (usando BD)
        // -------------------------
        $area = $this->selectedArea(); // puede ser null si aún no valida
        $areaNombre = (string) ($area['nombre'] ?? '');
        $areaKey = (string) ($area['slug'] ?? '');
        if ($areaKey === '' && $areaNombre !== '') {
            $areaKey = Str::slug($areaNombre, '_'); // fallback
        }

        if ($estamento === 'docente') {
            // Religión (católica/evangélica): permite varios niveles
            $esReligion = in_array($areaKey, ['religion_catolica', 'religion_evangelica'], true)
                || in_array($areaNombre, ['Religión Católica', 'Religión Evangélica'], true);

            // TP: permite niveles TP
            $esTP = in_array($areaKey, ['docente_tecnico_profesional', 'docente_técnico_profesional'], true)
                || $areaNombre === 'Docente Técnico Profesional';

            if ($esReligion) {
                $reglas['nivel_estudios'] = ['required', Rule::in($nivelesTodos)];
            } elseif ($esTP) {
                $reglas['nivel_estudios'] = ['required', Rule::in($nivelesTP)];
            } else {
                $reglas['nivel_estudios'] = ['required', Rule::in($nivelesSoloUniv)];
            }

            // Mención: obligatoria SOLO si es Educador(a) Diferencial
            $esDiferencial = in_array($areaKey, ['educadora_diferencial', 'educador_a_diferencial', 'educador_diferencial'], true)
                || in_array($areaNombre, ['Educadora Diferencial', 'Educador(a) Diferencial'], true);

            $reglas['mencion'] = [
                'nullable',
                'string',
                'max:190',
                Rule::requiredIf(fn() => $esDiferencial),
            ];

            // Especialidad TP: obligatoria SOLO si TP
            $reglas['especialidad_tp'] = [
                'nullable',
                'string',
                'max:150',
                Rule::requiredIf(fn() => $esTP),
            ];

            // Mantén el campo si existe, pero no obligatorio
            $reglas['cargos_funcion'] = ['nullable', 'string', 'max:150'];
        }

        if ($estamento === 'asistente') {
            // ✅ CAMBIO: ya NO se valida como requerido (porque el UI ya no debe usar "cargo/función")
            // Lo dejamos nullable para no romper si el campo sigue existiendo en formularios antiguos/BD.
            $reglas['cargos_funcion'] = ['nullable', 'string', 'max:150'];
        }

        // -------------------------
        // Previsión/Banco (obligatorias para todos)
        // -------------------------
        $reglas['prevision_afp']     = ['required', Rule::in($afps)];
        $reglas['salud_institucion'] = ['required', Rule::in($salud)];
        $reglas['banco']             = ['required', Rule::in($bancos)];
        $reglas['tipo_cuenta']       = ['required', Rule::in($tiposCuenta)];
        $reglas['numero_cuenta']     = ['required', 'string', 'max:40'];

        // Fecha titulación: requerida para TNS/Universitaria
        $reglas['fecha_titulacion'] = [
            'nullable', // ✅ clave: si no aplica, no valida "date"
            Rule::requiredIf(function () {
                $nivel = (string) $this->input('nivel_estudios');
                return in_array($nivel, ['Técnico Nivel Superior', 'Universitaria'], true);
            }),
            'date',
            'before_or_equal:today',
        ];

        return $reglas;
    }

    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'email'    => 'Formato de correo inválido.',
            'date'     => 'Fecha inválida.',
            'in'       => 'Selección inválida.',
            'image'    => 'Debe ser una imagen válida.',
            'mimes'    => 'Formato de imagen no permitido.',
            'max'      => 'Valor demasiado largo.',
            'integer'  => 'Debe ser un número entero.',
            'min'      => 'Valor demasiado bajo.',
            'exists'   => 'Selección inválida.',

            'area_desempeno_id.required' => 'El área de desempeño es obligatoria.',
            'area_desempeno_id.exists'   => 'El área de desempeño seleccionada no es válida.',

            'mencion.required'         => 'La mención es obligatoria cuando el área es "Educador(a) Diferencial".',
            'especialidad_tp.required' => 'La especialidad TP es obligatoria para "Docente Técnico Profesional".',

            'nivel_estudios.required'    => 'El nivel de estudios es obligatorio.',
            'anios_experiencia.required' => 'Los años de experiencia son obligatorios.',
            'semestres.required'         => 'Indica los semestres cursados (solo para nivel Universitaria).',
            'horas_totales.required'     => 'Indica las horas totales (solo para nivel Universitaria).',

            'fecha_titulacion.required' => 'La fecha de titulación es obligatoria para nivel Técnico Nivel Superior o Universitaria.',
            'fecha_titulacion.date'     => 'Ingresa una fecha válida.',
            'fecha_titulacion.before_or_equal' => 'La fecha de titulación no puede ser futura.',

            'lugares.required' => 'Selecciona al menos una comuna de desempeño.',
            'lugares.min'      => 'Selecciona al menos una comuna de desempeño.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $email = $this->email_contacto ? strtolower(trim($this->email_contacto)) : null;
        $direccion = $this->direccion ? trim($this->direccion) : null;

        $tel1 = $this->telefono1 ? preg_replace('/\s+/', ' ', trim($this->telefono1)) : null;
        $tel2 = $this->telefono2 ? preg_replace('/\s+/', ' ', trim($this->telefono2)) : null;

        $areaId = $this->input('area_desempeno_id');
        if (is_string($areaId)) $areaId = trim($areaId);
        if ($areaId === '' || $areaId === null) $areaId = null;
        if (is_numeric($areaId)) $areaId = (int) $areaId;

        $this->merge([
            'email_contacto'    => $email,
            'direccion'         => $direccion,
            'telefono1'         => $tel1,
            'telefono2'         => $tel2,
            'area_desempeno_id' => $areaId,
        ]);
    }

    public function attributes(): array
    {
        return [
            'email_contacto'     => 'email de contacto',
            'fecha_nacimiento'   => 'fecha de nacimiento',
            'direccion'          => 'dirección',
            'region_code'        => 'región',
            'comuna_id'          => 'comuna',
            'nacionalidad'       => 'nacionalidad',
            'telefono1'          => 'teléfono 1',
            'telefono2'          => 'teléfono 2',
            'genero'             => 'género',
            'pronombres'         => 'pronombres',
            'estamento'          => 'estamento',
            'area_desempeno_id'  => 'área de desempeño',
            'mencion'            => 'mención',
            'especialidad_tp'    => 'especialidad TP',
            'nivel_estudios'     => 'nivel de estudios',
            'institucion_titulo' => 'institución',
            'fecha_titulacion'   => 'fecha de titulación',
            'semestres'          => 'semestres',
            'horas_totales'      => 'horas totales',
            'anios_experiencia'  => 'años de experiencia',
            'cargos_funcion'     => 'cargo/función',
            'lugares'            => 'lugares de desempeño',
            'prevision_afp'      => 'institución de previsión',
            'salud_institucion'  => 'institución de salud',
            'banco'              => 'banco',
            'tipo_cuenta'        => 'tipo de cuenta',
            'numero_cuenta'      => 'número de cuenta',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        Log::debug('[VALIDATION] PostulantProfileRequest failed', [
            'errors'  => $validator->errors()->toArray(),
            'payload' => $this->only(['lugares', 'area_desempeno_id', 'estamento']),
        ]);

        parent::failedValidation($validator);
    }

    /**
     * Obtiene el área seleccionada (para reglas dependientes del área).
     * OJO: esto asume que existe el modelo AreaDesempeno y tabla areas_desempeno.
     */
    private function selectedArea(): ?array
    {
        $id = $this->input('area_desempeno_id');
        if (!$id || !is_numeric($id)) return null;

        $row = AreaDesempeno::query()
            ->select(['id', 'nombre', 'slug', 'estamento', 'activo'])
            ->find((int) $id);

        if (!$row) return null;

        return [
            'id'       => $row->id,
            'nombre'   => (string) $row->nombre,
            'slug'     => (string) ($row->slug ?? ''),
            'estamento' => (string) ($row->estamento ?? ''),
            'activo'   => (int) ($row->activo ?? 0),
        ];
    }
}

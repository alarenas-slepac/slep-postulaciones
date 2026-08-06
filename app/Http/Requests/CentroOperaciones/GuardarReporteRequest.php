<?php

namespace App\Http\Requests\CentroOperaciones;

use App\Models\Establecimiento;
use App\Services\CentroOperaciones\IncidenciaCatalogo;
use App\Services\CentroOperaciones\UnidadOperacionalService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(config('centro_operaciones.rol_reporte')) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $servicios = array_keys(config('centro_operaciones.servicios', []));
        $estados = array_keys(config('centro_operaciones.estados_servicio', []));
        $afectaciones = array_keys(config('centro_operaciones.afectaciones', []));
        $incidencias = app(IncidenciaCatalogo::class)
            ->activos()
            ->keys()
            ->all();
        $modalidadesEvacuacion = array_keys(config('centro_operaciones.modalidades_incidencia.evacuacion', []));
        $establecimiento = $this->user()?->establecimiento_id
            ? Establecimiento::query()->find($this->user()->establecimiento_id)
            : null;
        $unidadesPermitidas = $establecimiento
            ? app(UnidadOperacionalService::class)->paraEstablecimiento($establecimiento)->keys()->all()
            : [];

        $reglas = [
            'unidad_codigo' => ['nullable', 'string', Rule::in($unidadesPermitidas)],
            'funcionamiento' => ['required', Rule::in(array_keys(config('centro_operaciones.funcionamientos', [])))],
            'fecha_control_plagas' => ['nullable', 'date_format:Y-m-d'],
            'servicios' => ['required', 'array:'.implode(',', $servicios)],
            'servicio_observaciones' => ['nullable', 'array'],
            'afectaciones' => ['nullable', 'array'],
            'afectaciones.*' => ['distinct', Rule::in($afectaciones)],
            'afectacion_otro' => ['nullable', 'string', 'max:1000'],
            'estudiantes_presentes' => ['required', 'integer', 'min:0'],
            'docentes_presentes' => ['required', 'integer', 'min:0'],
            'asistentes_presentes' => ['required', 'integer', 'min:0'],
            'incidencias' => ['nullable', 'array'],
            'incidencias.*' => ['distinct', Rule::in($incidencias)],
            'incidencia_detalles' => ['nullable', 'array'],
            'incidencia_detalles.*' => ['nullable', 'string', 'max:1000'],
            'incidencia_modalidades' => ['nullable', 'array'],
            'incidencia_modalidades.evacuacion' => ['nullable', Rule::in($modalidadesEvacuacion)],
            'incidencias_resueltas' => ['nullable', 'array'],
            'incidencias_resueltas.*' => [
                'integer',
                'distinct',
                Rule::exists('centro_operaciones_incidencias', 'id')->where(fn ($query) => $query
                    ->where('establecimiento_id', $this->user()?->establecimiento_id)
                    ->where('unidad_codigo', $this->input('unidad_codigo') ?: null)
                    ->where('tipo', '!=', 'control_plagas_vencido')
                    ->where('estado', 'activa')),
            ],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'necesita_apoyo' => ['nullable', 'boolean'],
            'apoyo_detalle' => ['nullable', 'string', 'max:2000', 'required_if:necesita_apoyo,1'],
            'prioridad' => ['required', Rule::in(array_keys(config('centro_operaciones.prioridades', [])))],
        ];

        foreach ($servicios as $servicio) {
            $reglas["servicios.{$servicio}"] = ['required', Rule::in($estados)];
            $reglas["servicio_observaciones.{$servicio}"] = ['nullable', 'string', 'max:1000'];
        }

        return $reglas;
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'estudiantes_presentes' => 'estudiantes presentes',
            'docentes_presentes' => 'docentes presentes',
            'asistentes_presentes' => 'asistentes de la educación presentes',
            'apoyo_detalle' => 'detalle del apoyo requerido',
            'fecha_control_plagas' => 'fecha de control de plagas',
            'incidencia_modalidades.evacuacion' => 'motivo de la evacuación',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (in_array('otro', (array) $this->input('afectaciones', []), true)
                && trim((string) $this->input('afectacion_otro')) === '') {
                $validator->errors()->add('afectacion_otro', 'Describe la otra afectación seleccionada.');
            }

            if (in_array('evacuacion', (array) $this->input('incidencias', []), true)
                && trim((string) $this->input('incidencia_modalidades.evacuacion')) === '') {
                $validator->errors()->add(
                    'incidencia_modalidades.evacuacion',
                    'Indica si la evacuación corresponde a un simulacro o a una emergencia declarada.'
                );
            }
        });
    }
}

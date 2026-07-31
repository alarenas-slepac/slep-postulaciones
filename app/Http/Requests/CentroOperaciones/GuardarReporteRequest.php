<?php

namespace App\Http\Requests\CentroOperaciones;

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
        $incidencias = array_keys(config('centro_operaciones.incidencias', []));

        $reglas = [
            'funcionamiento' => ['required', Rule::in(array_keys(config('centro_operaciones.funcionamientos', [])))],
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
            'incidencias_resueltas' => ['nullable', 'array'],
            'incidencias_resueltas.*' => [
                'integer',
                'distinct',
                Rule::exists('centro_operaciones_incidencias', 'id')->where(fn ($query) => $query
                    ->where('establecimiento_id', $this->user()?->establecimiento_id)
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
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (in_array('otro', (array) $this->input('afectaciones', []), true)
                && trim((string) $this->input('afectacion_otro')) === '') {
                $validator->errors()->add('afectacion_otro', 'Describe la otra afectación seleccionada.');
            }
        });
    }
}

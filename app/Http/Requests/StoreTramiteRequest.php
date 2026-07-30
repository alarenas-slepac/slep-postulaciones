<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTramiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['postulante', 'funcionario']) ?? false;
    }

    public function rules(): array
    {
        $tipo = (string) $this->input('tipo');
        $tipos = array_keys(config('tramites.tipos', []));
        $documentKeys = array_keys((array) config('tramites.tipos.' . $tipo . '.documentos', []));

        return [
            'tipo' => ['required', 'string', Rule::in($tipos)],
            'documentos' => ['nullable', 'array'],
            'documentos.*.tipo_documento' => ['nullable', 'string', Rule::in($documentKeys ?: ['_none'])],
            'documentos.*.formato' => ['nullable', 'string', Rule::in(['pdf', 'PDF'])],
            'documentos.*.archivo' => ['nullable', 'file', 'mimetypes:application/pdf', 'mimes:pdf', 'max:102400'],
            'documentos.*.fecha_inicio' => ['nullable', 'date'],
            'documentos.*.fecha_termino' => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tipo = (string) $this->input('tipo');
            $config = (array) config('tramites.tipos.' . $tipo . '.documentos', []);
            $rows = $this->input('documentos', []);
            $files = $this->file('documentos', []);
            $grouped = [];
            $uploadedCount = 0;

            foreach ($rows as $index => $row) {
                $docType = (string) ($row['tipo_documento'] ?? '');
                $file = $files[$index]['archivo'] ?? null;
                $hasFile = $file !== null;
                $fechaInicio = (string) ($row['fecha_inicio'] ?? '');
                $fechaTermino = (string) ($row['fecha_termino'] ?? '');
                $hasAnyMetadata = $docType !== '' || $fechaInicio !== '' || $fechaTermino !== '';

                if (!$hasFile) {
                    if ($hasAnyMetadata) {
                        $validator->errors()->add("documentos.$index.archivo", 'Debes adjuntar el archivo para este documento.');
                    }
                    continue;
                }

                $uploadedCount++;

                if ($docType === '' || !array_key_exists($docType, $config)) {
                    $validator->errors()->add("documentos.$index.tipo_documento", 'Selecciona un tipo de documento válido.');
                    continue;
                }

                $grouped[$docType] = ($grouped[$docType] ?? 0) + 1;

                $requiresPeriod = (bool) ($config[$docType]['requires_period'] ?? false);

                if ($requiresPeriod) {
                    if ($fechaInicio === '') {
                        $validator->errors()->add("documentos.$index.fecha_inicio", 'La fecha de inicio es obligatoria para este documento.');
                    }
                    if ($fechaTermino === '') {
                        $validator->errors()->add("documentos.$index.fecha_termino", 'La fecha de término es obligatoria para este documento.');
                    }
                    if ($fechaInicio !== '' && $fechaTermino !== '' && strtotime($fechaInicio) > strtotime($fechaTermino)) {
                        $validator->errors()->add("documentos.$index.fecha_termino", 'La fecha de término debe ser igual o posterior a la fecha de inicio.');
                    }
                } else {
                    if ($fechaInicio !== '') {
                        $validator->errors()->add("documentos.$index.fecha_inicio", 'Este documento no requiere fecha de inicio.');
                    }
                    if ($fechaTermino !== '') {
                        $validator->errors()->add("documentos.$index.fecha_termino", 'Este documento no requiere fecha de término.');
                    }
                }
            }

            if ($uploadedCount < 1) {
                $validator->errors()->add('documentos', 'Debes adjuntar al menos un documento para enviar el trámite.');
            }

            $requiredUploadedCount = 0;
            $optionalUploadedCount = 0;

            foreach ($config as $docType => $docConfig) {
                $count = (int) ($grouped[$docType] ?? 0);
                $isRequired = (bool) ($docConfig['required'] ?? false);

                if (!(bool) ($docConfig['multiple'] ?? false) && $count > 1) {
                    $validator->errors()->add('documentos', 'Solo puedes adjuntar un archivo para: ' . $docConfig['label'] . '.');
                }

                if ($isRequired && $count < 1) {
                    $validator->errors()->add('documentos', 'Debes adjuntar obligatoriamente: ' . $docConfig['label'] . '.');
                }

                if ($count > 0) {
                    if ($isRequired) {
                        $requiredUploadedCount += $count;
                    } else {
                        $optionalUploadedCount += $count;
                    }
                }
            }

            if ($requiredUploadedCount < 2) {
                $validator->errors()->add('documentos', 'Debes adjuntar la carta de reconocimiento y el Certificado Cotizaciones AFP Histórico Tipo B con RUT de Empleador.');
            }

            if ($optionalUploadedCount < 1) {
                $validator->errors()->add('documentos', 'Además de los documentos obligatorios, debes adjuntar al menos un documento adicional entre Certificado de Antigüedad, Contratos de Trabajo, Decretos, Orden de Trabajo, Finiquitos o Nombramientos.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Debes seleccionar un tipo de trámite.',
            'documentos.*.archivo.mimetypes' => 'Solo se permiten archivos PDF.',
            'documentos.*.archivo.mimes' => 'Solo se permiten archivos PDF.',
            'documentos.*.archivo.max' => 'Cada archivo PDF no puede superar 100 MB.',
        ];
    }
}

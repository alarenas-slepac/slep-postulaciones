<?php

namespace App\Http\Requests;

use App\Models\Tramite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTramiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tramite = $this->route('tramite');

        return $this->user()?->hasAnyRole(['postulante', 'funcionario'])
            && $tramite instanceof Tramite
            && (int) $tramite->user_id === (int) $this->user()->id
            && in_array((string) $tramite->estado, ['enviado', 'en_revision'], true);
    }

    public function rules(): array
    {
        $tipo = (string) $this->input('tipo');
        $tipos = array_keys(config('tramites.tipos', []));
        $documentKeys = array_keys((array) config('tramites.tipos.' . $tipo . '.documentos', []));

        return [
            'tipo' => ['required', 'string', Rule::in($tipos)],
            'existing_documentos_remove' => ['nullable', 'array'],
            'existing_documentos_remove.*' => ['nullable', 'integer'],
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
            $tramite = $this->route('tramite');
            if (!$tramite instanceof Tramite) {
                return;
            }

            $tipo = (string) $this->input('tipo');
            $config = (array) config('tramites.tipos.' . $tipo . '.documentos', []);
            $rows = $this->input('documentos', []);
            $files = $this->file('documentos', []);
            $grouped = [];
            $removedIds = collect($this->input('existing_documentos_remove', []))
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->values();

            foreach ($tramite->documentos as $documento) {
                if ($removedIds->contains((int) $documento->id)) {
                    if ((string) $documento->estado_revision !== 'pendiente') {
                        $validator->errors()->add('existing_documentos_remove', 'Solo puedes quitar documentos pendientes. Los aprobados o rechazados deben mantenerse o reemplazarse según corresponda.');
                    }
                    continue;
                }

                if ((string) $documento->estado_revision === 'rechazado') {
                    continue;
                }

                $grouped[$documento->tipo_documento] = ($grouped[$documento->tipo_documento] ?? 0) + 1;
            }

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

            foreach ($config as $docType => $docConfig) {
                $count = (int) ($grouped[$docType] ?? 0);
                if (!(bool) ($docConfig['multiple'] ?? false) && $count > 1) {
                    $validator->errors()->add('documentos', 'Solo puedes adjuntar un archivo para: ' . $docConfig['label'] . '.');
                }
                if ((bool) ($docConfig['required'] ?? false) && $count < 1) {
                    $validator->errors()->add('documentos', 'Debes adjuntar obligatoriamente: ' . $docConfig['label'] . '.');
                }
            }

            $requiredKeys = collect($config)->filter(fn ($doc) => !empty($doc['required']))->keys();
            $requiredCount = $requiredKeys->sum(fn ($key) => (int) ($grouped[$key] ?? 0));
            $optionalCount = collect($config)->filter(fn ($doc) => empty($doc['required']))->keys()->sum(fn ($key) => (int) ($grouped[$key] ?? 0));

            if ($requiredCount < 2) {
                $validator->errors()->add('documentos', 'Debes adjuntar la carta de reconocimiento y el Certificado Cotizaciones AFP Histórico Tipo B con RUT de Empleador.');
            }
            if ($optionalCount < 1) {
                $validator->errors()->add('documentos', 'Además de los documentos obligatorios, debes adjuntar al menos un documento adicional entre Certificado de Antigüedad, Contratos de Trabajo, Decretos, Orden de Trabajo, Finiquitos o Nombramientos.');
            }
        });
    }
}

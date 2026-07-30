<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAdmisionGaleriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $activeRole = $user && method_exists($user, 'activeRoleName')
            ? (string) $user->activeRoleName()
            : '';

        return in_array($activeRole, ['admin', 'coordinador_uatp', 'comunicaciones'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'marcar_primera_como_portada' => $this->boolean('marcar_primera_como_portada'),
        ]);
    }

    public function rules(): array
    {
        $maxMb = max(1, (int) config('admision.max_imagen_mb', 100));
        $maxKb = $maxMb * 1024;
        $maxFiles = max(1, (int) config('admision.max_imagenes_por_carga', 10));

        return [
            'imagenes' => ['required', 'array', 'min:1', 'max:' . $maxFiles],
            'imagenes.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . $maxKb],
            'texto_alternativo_base' => ['nullable', 'string', 'max:180'],
            'marcar_primera_como_portada' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $files = $this->file('imagenes', []);
            $maxTotalMb = max(1, (int) config('admision.max_carga_total_mb', 200));
            $maxTotalBytes = $maxTotalMb * 1024 * 1024;
            $totalBytes = 0;

            foreach (is_array($files) ? $files : [] as $file) {
                if ($file && $file->isValid()) {
                    $totalBytes += max(0, (int) $file->getSize());
                }
            }

            if ($totalBytes > $maxTotalBytes) {
                $validator->errors()->add(
                    'imagenes',
                    "La carga completa supera {$maxTotalMb} MB. Divide las imagenes en dos o mas cargas."
                );
            }
        });
    }

    public function messages(): array
    {
        $maxMb = max(1, (int) config('admision.max_imagen_mb', 100));

        return [
            'imagenes.required' => 'Selecciona al menos una imagen.',
            'imagenes.*.file' => 'Uno de los archivos recibidos no es valido.',
            'imagenes.*.image' => 'Cada archivo debe ser una imagen valida.',
            'imagenes.*.mimes' => 'Solo se permiten imagenes JPG, PNG o WebP.',
            'imagenes.*.max' => "Cada imagen puede pesar hasta {$maxMb} MB antes de ser optimizada.",
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAdmisionEstablecimientoRequest extends FormRequest
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
            'destacado' => $this->boolean('destacado'),
            'eliminar_logo' => $this->boolean('eliminar_logo'),
            'eliminar_director_foto' => $this->boolean('eliminar_director_foto'),
        ]);
    }

    public function rules(): array
    {
        $maxMb = max(1, (int) config('admision.max_imagen_mb', 100));
        $maxKb = $maxMb * 1024;

        return [
            'sello_educativo' => ['nullable', 'string', 'max:3000'],
            'descripcion_corta' => ['nullable', 'string', 'max:500'],
            'director_nombre' => ['nullable', 'string', 'max:180'],
            'director_resena' => ['nullable', 'string', 'max:1200'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . $maxKb],
            'director_foto' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . $maxKb],
            'eliminar_logo' => ['required', 'boolean'],
            'eliminar_director_foto' => ['required', 'boolean'],
            'sitio_web_url' => ['nullable', 'url:http,https', 'max:500'],
            'facebook_url' => ['nullable', 'url:http,https', 'max:500'],
            'instagram_url' => ['nullable', 'url:http,https', 'max:500'],
            'direccion_publica' => ['nullable', 'string', 'max:500'],
            'sector' => ['nullable', Rule::in(['Urbano', 'Rural'])],
            'telefono_publico' => ['nullable', 'string', 'max:80'],
            'email_publico' => ['nullable', 'email:rfc', 'max:255'],
            'destacado' => ['required', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $files = array_filter([
                $this->file('logo'),
                $this->file('director_foto'),
            ]);
            $maxTotalMb = max(1, (int) config('admision.max_carga_total_mb', 200));
            $totalBytes = 0;

            foreach ($files as $file) {
                if ($file->isValid()) {
                    $totalBytes += max(0, (int) $file->getSize());
                }
            }

            if ($totalBytes > ($maxTotalMb * 1024 * 1024)) {
                $validator->errors()->add(
                    'media',
                    "Los archivos seleccionados superan {$maxTotalMb} MB en total. Carga el logo y la fotografia por separado."
                );
            }
        });
    }

    public function messages(): array
    {
        $maxMb = max(1, (int) config('admision.max_imagen_mb', 100));

        return [
            'sitio_web_url.url' => 'El sitio web debe comenzar con http:// o https://.',
            'facebook_url.url' => 'El enlace de Facebook debe comenzar con http:// o https://.',
            'instagram_url.url' => 'El enlace de Instagram debe comenzar con http:// o https://.',
            'email_publico.email' => 'El correo publico no tiene un formato valido.',
            'logo.image' => 'El logo debe ser una imagen valida.',
            'logo.mimes' => 'El logo debe ser JPG, PNG o WebP.',
            'logo.max' => "El logo puede pesar hasta {$maxMb} MB antes de ser optimizado.",
            'director_foto.image' => 'La fotografia del director o directora debe ser una imagen valida.',
            'director_foto.mimes' => 'La fotografia debe ser JPG, PNG o WebP.',
            'director_foto.max' => "La fotografia puede pesar hasta {$maxMb} MB antes de ser optimizada.",
        ];
    }
}

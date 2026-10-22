<?php

namespace App\Http\Requests\Votaciones;

use Illuminate\Foundation\Http\FormRequest;

class GuardarConvocadosLocalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('votaciones.manage-rutas') === true;
    }

    public function rules(): array
    {
        return [
            'modo' => ['required', 'in:padron,manual'],
            'establecimientos' => ['nullable', 'array', 'max:2000'],
            'establecimientos.*' => ['required', 'integer', 'min:0', 'max:9999999', 'distinct'],
        ];
    }
}

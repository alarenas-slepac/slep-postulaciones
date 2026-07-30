<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubsectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo admin o funcionario_slep
        $u = $this->user();
        return $u && (method_exists($u, 'hasAnyRole') ? $u->hasAnyRole(['admin', 'funcionario_slep']) : true);
    }

    public function rules(): array
    {
        $subsector = $this->route('subsector'); // por parámetros() en routes
        $id = $subsector?->id;

        return [
            'subsector' => [
                'required',
                'string',
                'max:190',
                Rule::unique('subsectores', 'subsector')->ignore($id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'subsector.required' => 'El nombre del subsector es obligatorio.',
            'subsector.unique'   => 'Ya existe un subsector con ese nombre.',
        ];
    }
}

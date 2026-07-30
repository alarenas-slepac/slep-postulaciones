<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MencionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo admin o funcionario_slep
        $u = $this->user();
        return $u && (method_exists($u, 'hasAnyRole') ? $u->hasAnyRole(['admin', 'funcionario_slep']) : true);
    }

    public function rules(): array
    {
        /** @var \App\Models\Mencion|null $mencione */
        $mencione = $this->route('mencione'); // nombre del parámetro singular de resource('menciones', ...)
        $id = $mencione?->id;

        return [
            'nombre'       => ['required', 'string', 'max:190'],
            'universidad'  => ['nullable', 'string', 'max:190'],
            'anio'         => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'subsector_id' => ['required', 'exists:subsectores,id'],

            // Unicidad compuesta: nombre + universidad + anio + subsector_id
            Rule::unique('menciones')
                ->where(fn($q) => $q->where('nombre', $this->input('nombre'))
                    ->where('universidad', $this->input('universidad') ?: null)
                    ->where('anio', $this->input('anio') ?: null)
                    ->where('subsector_id', $this->input('subsector_id')))
                ->ignore($id),
        ];
    }

    public function messages(): array
    {
        return [
            'subsector_id.required' => 'Debe seleccionar un subsector.',
            'subsector_id.exists'   => 'Subsector inválido.',
            'nombre.required'       => 'El nombre de la mención es obligatorio.',
            'anio.integer'          => 'El año debe ser numérico.',
            'anio.min'              => 'El año es demasiado antiguo.',
            'anio.max'              => 'El año es demasiado grande.',
            'menciones_unique'      => 'Esta mención ya existe para ese subsector/universidad/año.',
        ];
    }
}

<?php

namespace App\Http\Requests\Votaciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarJornadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('votaciones.manage-jornadas') === true;
    }

    public function rules(): array
    {
        $id = $this->route('jornada')?->id;

        return ['nombre' => ['required', 'string', 'max:255'], 'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('jornadas_votacion', 'slug')->ignore($id)], 'fecha' => ['required', 'date'], 'descripcion' => ['nullable', 'string', 'max:3000'], 'procesos' => ['required', 'array', 'min:1'], 'procesos.*' => ['integer', 'exists:procesos_votacion,id']];
    }
}

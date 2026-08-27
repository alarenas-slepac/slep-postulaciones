<?php

namespace App\Http\Requests\Votaciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarRutaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('votaciones.manage-rutas') === true;
    }

    public function rules(): array
    {
        return ['establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id', Rule::unique('rutas_votacion', 'establecimiento_id')->where('grupo_votacion_id', $this->route('grupo')?->id)]];
    }
}

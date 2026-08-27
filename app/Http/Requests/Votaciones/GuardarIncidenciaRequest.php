<?php

namespace App\Http\Requests\Votaciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarIncidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['ruta_votacion_id' => ['nullable', 'integer', Rule::exists('rutas_votacion', 'id')->where('grupo_votacion_id', $this->route('grupo')?->id)], 'tipo' => ['required', Rule::in(array_keys(config('votaciones.tipos_incidencia')))], 'detalle_interno' => ['required', 'string', 'max:2000'], 'mensaje_publico' => ['nullable', 'required_if:publica,1', 'string', 'max:1000'], 'publica' => ['nullable', 'boolean']];
    }
}

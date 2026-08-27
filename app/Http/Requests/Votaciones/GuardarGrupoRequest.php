<?php

namespace App\Http\Requests\Votaciones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarGrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('votaciones.manage-grupos') === true;
    }

    public function rules(): array
    {
        $grupo = $this->route('grupo');
        $jornadaId = $this->route('jornada')?->id ?? $grupo?->jornada_votacion_id;

        return ['nombre' => ['required', 'string', 'max:255'], 'numero' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('grupos_votacion', 'numero')->where('jornada_votacion_id', $jornadaId)->ignore($grupo?->id)], 'encargado_id' => ['required', 'integer', 'exists:users,id'], 'miembros' => ['nullable', 'array'], 'miembros.*' => ['integer', 'distinct', 'exists:users,id']];
    }
}

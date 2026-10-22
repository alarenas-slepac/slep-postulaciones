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
        $asociar = ! $grupo && $this->input('modo') === 'asociar';

        return [
            'modo' => ['sometimes', Rule::in($grupo ? ['nuevo'] : ['nuevo', 'asociar'])],
            'grupo_origen_id' => $asociar ? ['required', 'integer', Rule::exists('grupos_votacion', 'id')->whereNot('jornada_votacion_id', $jornadaId)] : ['prohibited'],
            'nombre' => [$asociar ? 'prohibited' : 'required', 'string', 'max:255'],
            'numero' => ['required', 'integer', 'min:1', 'max:999', Rule::unique('grupos_votacion', 'numero')->where('jornada_votacion_id', $jornadaId)->ignore($grupo?->id)],
            'encargado_id' => [$asociar ? 'prohibited' : 'required', 'integer', 'exists:users,id'],
            'miembros' => [$asociar ? 'prohibited' : 'nullable', 'array'],
            'miembros.*' => ['integer', 'distinct', 'exists:users,id'],
            'fecha_inicio' => ['nullable', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}

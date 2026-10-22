<?php

namespace App\Http\Requests\Votaciones;

use App\Support\Votaciones\PapeletasVotacion;
use Illuminate\Foundation\Http\FormRequest;

class GuardarResultadoDiarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        $papeletas = $this->papeletas();
        $rules = [
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now(config('votaciones.timezone'))->toDateString()],
            'version' => ['required', 'integer', 'min:0', 'max:4294967294'],
            'observacion_interna' => ['nullable', 'string', 'max:2000'],
            'conteos' => ['required', 'array:'.implode(',', array_keys($papeletas))],
        ];
        foreach ($papeletas as $clave => $papeleta) {
            $rules['conteos.'.$clave] = ['required', 'array:'.implode(',', array_keys($papeleta['campos']))];
            foreach ($papeleta['campos'] as $opcion => $label) {
                $rules["conteos.$clave.$opcion"] = ['required', 'integer', 'min:0', 'max:1000000'];
            }
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = ['observacion_interna' => 'observación interna'];
        foreach ($this->papeletas() as $clave => $papeleta) {
            foreach ($papeleta['campos'] as $opcion => $label) {
                $attributes["conteos.$clave.$opcion"] = $papeleta['titulo'].' · '.$label;
            }
        }

        return $attributes;
    }

    private function papeletas(): array
    {
        $fecha = $this->input('fecha');
        $dia = is_string($fecha) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha)
            ? $this->route('jornada')->resultadosDiarios()->whereDate('fecha', $fecha)->first() : null;

        return PapeletasVotacion::para($this->route('jornada'), $dia);
    }
}

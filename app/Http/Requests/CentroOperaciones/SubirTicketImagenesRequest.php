<?php

namespace App\Http\Requests\CentroOperaciones;

use App\Models\CentroOperacionesTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubirTicketImagenesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();
        $ticket = $this->route('ticket');

        if (! $usuario || ! $ticket instanceof CentroOperacionesTicket) {
            return false;
        }

        $ticket->loadMissing('incidencia');

        return $usuario->hasRole('funcionario_directivo_estab')
            && (int) $usuario->establecimiento_id === (int) $ticket->incidencia?->establecimiento_id;
    }

    public function rules(): array
    {
        $maximoKb = (int) config('centro_operaciones.ticket_imagenes.maximo_mb', 20) * 1024;
        $restantes = max(0, (int) config('centro_operaciones.ticket_imagenes.maximo', 10) - $this->cantidadActual());

        return [
            'imagenes' => ['required', 'array', 'min:1', 'max:'.$restantes],
            'imagenes.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$maximoKb],
        ];
    }

    public function attributes(): array
    {
        return ['imagenes' => 'imágenes', 'imagenes.*' => 'imagen'];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $maximo = (int) config('centro_operaciones.ticket_imagenes.maximo', 10);

            if ($this->cantidadActual() + count($this->file('imagenes', [])) > $maximo) {
                $validator->errors()->add('imagenes', 'El ticket admite un máximo acumulado de '.$maximo.' imágenes.');
            }
        });
    }

    private function cantidadActual(): int
    {
        $ticket = $this->route('ticket');

        return $ticket instanceof CentroOperacionesTicket ? $ticket->imagenes()->count() : 0;
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdmisionGaleriaImagenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $activeRole = $user && method_exists($user, 'activeRoleName')
            ? (string) $user->activeRoleName()
            : '';

        return in_array($activeRole, ['admin', 'coordinador_uatp', 'comunicaciones'], true);
    }

    public function rules(): array
    {
        return [
            'texto_alternativo' => ['required', 'string', 'max:255'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'orden' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }
}

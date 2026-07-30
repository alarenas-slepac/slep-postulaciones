<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // público (guest)
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255'], // RUT o email
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Devuelve el arreglo credenciales para Auth::attempt()
     * - Si 'login' es email válido → ['email' => ..., 'password' => ...]
     * - Si no, se asume RUT → normaliza y retorna ['rut' => ..., 'password' => ...]
     */
    public function credentials(): array
    {
        $login = (string) $this->input('login');

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => $login,
                'password' => (string) $this->input('password'),
            ];
        }

        $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', $login));

        return [
            'rut' => $rut,
            'password' => (string) $this->input('password'),
        ];
    }
}

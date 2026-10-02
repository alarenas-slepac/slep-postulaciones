<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

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
            // El checkbox histórico envía "on"; Request::boolean lo interpreta.
            'remember' => ['nullable'],
            'active_role' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Este campo es obligatorio.',
            'password.required' => 'Este campo es obligatorio.',
        ];
    }

    /**
     * Devuelve el arreglo credenciales para Auth::attempt()
     * - Si 'login' es email válido → ['email' => ..., 'password' => ...]
     * - Si no, se asume RUT → normaliza y retorna ['rut' => ..., 'password' => ...]
     */
    public function credentials(): array
    {
        $login = trim((string) $this->input('login'));

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => strtolower($login),
                'password' => (string) $this->input('password'),
            ];
        }

        $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', $login));

        return [
            'rut' => $rut,
            'password' => (string) $this->input('password'),
        ];
    }

    public function throttleKey(): string
    {
        $credentials = $this->credentials();
        $field = array_key_first($credentials);

        return 'login:identifier:'.$this->digest($field.'|'.$credentials[$field].'|'.$this->ip());
    }

    public function ipThrottleKey(): string
    {
        return 'login:ip:'.$this->digest((string) $this->ip());
    }

    private function digest(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    public function ensureIsNotRateLimited(): void
    {
        $seconds = 0;
        $limited = false;
        foreach ([
            $this->throttleKey() => max(1, (int) config('auth.login_limits.identifier_ip', 5)),
            $this->ipThrottleKey() => max(1, (int) config('auth.login_limits.ip', 30)),
        ] as $key => $maximum) {
            if (RateLimiter::tooManyAttempts($key, $maximum)) {
                $limited = true;
                $seconds = max($seconds, RateLimiter::availableIn($key));
            }
        }

        if ($limited) {
            $seconds = max(1, $seconds);
            $unit = $seconds === 1 ? 'segundo' : 'segundos';
            throw ValidationException::withMessages([
                'login' => "Demasiados intentos de inicio de sesión. Intenta nuevamente en {$seconds} {$unit}.",
            ]);
        }
    }

    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();
        $credentials = $this->credentials();
        $field = array_key_first($credentials);
        $user = User::query()->where($field, $credentials[$field])->first();

        // Conserva la autenticación por email de las cuentas localizadas por RUT.
        if (! $user || ! Auth::attempt(['email' => $user->email, 'password' => $credentials['password']], $this->boolean('remember'))) {
            $decay = max(1, (int) config('auth.login_limits.decay_seconds', 60));
            RateLimiter::hit($this->throttleKey(), $decay);
            RateLimiter::hit($this->ipThrottleKey(), $decay);

            throw ValidationException::withMessages(['login' => 'Credenciales inválidas.']);
        }

        return $user;
    }

    public function clearLoginAttempts(): void
    {
        // El contador de IP se conserva aunque las credenciales sean válidas.
        RateLimiter::clear($this->throttleKey());
    }
}

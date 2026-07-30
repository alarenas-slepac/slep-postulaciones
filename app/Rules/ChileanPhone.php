<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ChileanPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $raw = (string) $value;
        // Normaliza: quita todo excepto dígitos
        $digits = preg_replace('/\D+/', '', $raw);

        // Si parte con 56, quita 56
        if (str_starts_with($digits, '56')) {
            $digits = substr($digits, 2);
        }
        // Quita 0 inicial si existe
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // Debe tener exactamente 9 dígitos (móviles y fijos en CL)
        if (strlen($digits) !== 9) {
            $fail('Número telefónico inválido. Usa 9 dígitos (p. ej. 912345678 o +56912345678).');
            return;
        }

        // Primer dígito 2-9 (8 o 9 móviles; 2-7 fijos en algunas zonas)
        if (!preg_match('/^[2-9][0-9]{8}$/', $digits)) {
            $fail('Número telefónico inválido para Chile.');
        }
    }
}

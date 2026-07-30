<?php

namespace App\Http\Requests\Auth;

use App\Services\FuncionarioRegisterLookupService;
use App\Services\RestrictedRutService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterUserRequest extends FormRequest
{
    private ?array $cachedLookup = null;

    public function authorize(): bool
    {
        return true; // público (guest)
    }

    public function rules(): array
    {
        $restrictedService = app(RestrictedRutService::class);
        $isFuncionario = ($this->registerLookup()['is_funcionario'] ?? false) === true;

        return [
            'rut' => [
                'required',
                'string',
                'max:12',
                'unique:users,rut',
                function (string $attribute, mixed $value, \Closure $fail) use ($restrictedService) {
                    $lookup = $this->registerLookup();

                    if (($lookup['status'] ?? null) === 'invalid') {
                        $fail('El formato del RUT es inválido.');
                        return;
                    }

                    if (($lookup['status'] ?? null) === 'error') {
                        $fail((string) ($lookup['message'] ?? 'No fue posible validar el RUT contra el padrón vigente.'));
                        return;
                    }

                    if (!($lookup['is_funcionario'] ?? false) && $restrictedService->isRestrictedRut((string) $value)) {
                        $fail('Este RUT mantiene una restricción vigente para ejercer y no puede registrarse como postulante.');
                    }
                },
            ],
            'fecha_nacimiento_funcionario' => ['nullable', 'date'],
            'nombres' => [$isFuncionario ? 'nullable' : 'required', 'string', 'max:255'],
            'apellido_paterno' => [$isFuncionario ? 'nullable' : 'required', 'string', 'max:255'],
            'apellido_materno' => [$isFuncionario ? 'nullable' : 'required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lookup = $this->registerLookup();

            if (($lookup['status'] ?? null) === 'invalid' || ($lookup['status'] ?? null) === 'error') {
                return;
            }

            if (($lookup['is_funcionario'] ?? false) !== true) {
                return;
            }

            $providedBirthDate = (string) $this->input('fecha_nacimiento_funcionario', '');
            if ($providedBirthDate === '') {
                $validator->errors()->add(
                    'fecha_nacimiento_funcionario',
                    'Si el RUT figura como funcionario, debes ingresar tu fecha de nacimiento para completar el registro.'
                );
                return;
            }

            $expectedBirthDate = (string) ($lookup['fecha_nacimiento'] ?? '');
            if ($expectedBirthDate === '') {
                $validator->errors()->add(
                    'rut',
                    'El padrón vigente no tiene fecha de nacimiento disponible para este RUT. No es posible completar el auto-registro.'
                );
                return;
            }

            if ($providedBirthDate !== $expectedBirthDate) {
                $validator->errors()->add(
                    'fecha_nacimiento_funcionario',
                    'La fecha de nacimiento no coincide con la registrada para este funcionario.'
                );
            }
        });
    }

    /**
     * Normaliza el RUT quitando puntos/guiones y K mayúscula.
     */
    protected function prepareForValidation(): void
    {
        $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $this->input('rut')));
        $this->merge([
            'rut' => $rut,
            'fecha_nacimiento_funcionario' => $this->input('fecha_nacimiento_funcionario')
                ? (string) $this->input('fecha_nacimiento_funcionario')
                : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'rut.required' => 'El RUT es obligatorio.',
            'rut.unique' => 'El RUT ya está registrado.',
            'rut.max' => 'El RUT ingresado es demasiado largo.',
            'email.unique' => 'El email ya está registrado.',
            'fecha_nacimiento_funcionario.date' => 'La fecha de nacimiento debe tener un formato válido.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }

    private function registerLookup(): array
    {
        if ($this->cachedLookup === null) {
            $this->cachedLookup = app(FuncionarioRegisterLookupService::class)
                ->lookup((string) $this->input('rut'));
        }

        return $this->cachedLookup;
    }
}

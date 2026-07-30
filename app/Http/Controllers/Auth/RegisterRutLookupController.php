<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\FuncionarioRegisterLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegisterRutLookupController extends Controller
{
    public function __invoke(Request $request, FuncionarioRegisterLookupService $service): JsonResponse
    {
        $rawRut = (string) $request->query('rut', '');
        $birthDate = (string) $request->query('fecha_nacimiento', '');
        $lookup = $service->lookup($rawRut);

        if (($lookup['status'] ?? null) === 'invalid') {
            return response()->json(array_merge($lookup, [
                'found' => false,
                'source' => null,
            ]));
        }

        if (($lookup['status'] ?? null) === 'error') {
            return response()->json(array_merge($lookup, [
                'found' => false,
                'source' => 'reemplazos_personal',
            ]));
        }

        if (($lookup['is_funcionario'] ?? false) === true) {
            $expectedBirthDate = (string) ($lookup['fecha_nacimiento'] ?? '');

            if ($birthDate === '') {
                return response()->json([
                    'valid' => true,
                    'status' => 'funcionario_requires_birth_date',
                    'found' => true,
                    'is_funcionario' => true,
                    'source' => 'reemplazos_personal',
                    'requires_birth_date' => true,
                    'message' => 'RUT encontrado en el padrón vigente de personal. Ingresa tu fecha de nacimiento para completar el auto-registro como funcionario.',
                ]);
            }

            if ($expectedBirthDate === '' || $birthDate !== $expectedBirthDate) {
                return response()->json([
                    'valid' => true,
                    'status' => 'funcionario_birth_date_mismatch',
                    'found' => true,
                    'is_funcionario' => true,
                    'source' => 'reemplazos_personal',
                    'requires_birth_date' => true,
                    'message' => 'La fecha de nacimiento no coincide con la registrada para este funcionario.',
                ]);
            }

            return response()->json([
                'valid' => true,
                'status' => 'funcionario_prefill',
                'found' => true,
                'is_funcionario' => true,
                'source' => 'reemplazos_personal',
                'requires_birth_date' => true,
                'nombres' => (string) ($lookup['nombres'] ?? ''),
                'apellido_paterno' => (string) ($lookup['apellido_paterno'] ?? ''),
                'apellido_materno' => (string) ($lookup['apellido_materno'] ?? ''),
                'establecimiento_label' => (string) ($lookup['establecimiento_label'] ?? ''),
                'message' => 'Identidad confirmada. Completamos los datos desde el padrón vigente para registrar al funcionario.',
            ]);
        }

        return response()->json(array_merge($lookup, [
            'valid' => true,
            'status' => 'postulante_available',
            'found' => false,
            'is_funcionario' => false,
            'source' => null,
            'message' => 'RUT válido. Puedes continuar con el registro como postulante.',
        ]));
    }
}

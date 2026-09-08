<?php

namespace App\Services\LicenciasMedicas;

use App\Models\FuncionarioAcAutorizado;
use App\Services\Padron\PadronVigenciaService;
use Illuminate\Support\Facades\Schema;

class LicenciaFuncionarioResolver
{
    /**
     * Resuelve si el funcionario pertenece a Administracion Central o a un establecimiento.
     * Asociación al momento del ingreso, no certificación del vínculo a la fecha del reposo.
     * Mantiene prioridad de identidad AC; su autorización no equivale a vigencia laboral.
     */
    public function resolve(?string $rutNormalizado, ?string $rutCuerpo, ?string $establecimientoManual = null, ?string $comunaManual = null): array
    {
        $base = $this->base($establecimientoManual, $comunaManual);

        $rut = RutNormalizer::normalize($rutNormalizado);
        if (! $rut['valido'] || ($rutCuerpo !== null && $rutCuerpo !== ''
            && ltrim(preg_replace('/\D/', '', $rutCuerpo), '0') !== $rut['rut'])) {
            return array_replace($base, ['advertencia' => 'No se pudo asociar: el RUT completo y su dígito verificador deben ser válidos.']);
        }
        $rutNorm = $rut['normalizado'];
        $rutDigits = $rut['rut'];

        if ($rutNorm !== '' || $rutDigits !== '') {
            $ac = $this->buscarFuncionarioAc($rutNorm, $rutDigits);
            if ($ac) {
                return $this->desdeFuncionarioAc($ac, $base);
            }
        }

        return $this->buscarReemplazosPersonal($rutNorm, $base);
    }

    private function base(?string $establecimientoManual, ?string $comunaManual): array
    {
        return [
            'tipo_dependencia' => 'sin_asociacion',
            'establecimiento_id' => null,
            'establecimiento_nombre' => $establecimientoManual,
            'comuna' => $comunaManual,
            'subdireccion' => null,
            'unidad_departamento' => null,
            'cargo' => null,
            'grado' => null,
            'escalafon' => null,
            'calidad_juridica' => null,
            'estamento' => null,
            'correo_funcionario' => null,
            'fuente' => 'sin_asociacion',
            'periodo' => null,
            'advertencia' => null,
        ];
    }

    private function buscarFuncionarioAc(string $rutNorm, string $rutDigits): ?FuncionarioAcAutorizado
    {
        try {
            if (! Schema::hasTable('funcionarios_ac_autorizados')) {
                return null;
            }

            return FuncionarioAcAutorizado::query()
                ->where(function ($query) use ($rutNorm, $rutDigits) {
                    if ($rutNorm !== '') {
                        $query->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(rut_normalizado), '.', ''), '-', ''), ' ', '') = ?", [$rutNorm]);
                    }

                    if ($rutDigits !== '') {
                        $query->orWhere(function ($run) use ($rutDigits, $rutNorm) {
                            $run->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(TRIM(run)), '.', ''), '-', ''), ' ', '') = ?", [$rutDigits])
                                ->whereRaw('UPPER(TRIM(dv)) = ?', [substr($rutNorm, -1)]);
                        });
                    }
                })
                ->orderByRaw("CASE WHEN estado_autorizacion = 'activo' THEN 0 ELSE 1 END")
                ->orderByDesc('periodo_nomina')
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function desdeFuncionarioAc(FuncionarioAcAutorizado $funcionario, array $base): array
    {
        $nombre = trim(collect([
            $funcionario->nombres,
            $funcionario->apellido_paterno,
            $funcionario->apellido_materno,
        ])->filter()->implode(' '));

        return array_merge($base, [
            'tipo_dependencia' => 'administracion_central',
            'establecimiento_id' => null,
            'establecimiento_nombre' => 'Administracion Central',
            'comuna' => $funcionario->comuna ?: $base['comuna'],
            'subdireccion' => $funcionario->subdireccion_dependencia,
            'unidad_departamento' => $funcionario->unidad_departamento,
            'cargo' => $funcionario->cargo_funcion,
            'grado' => $funcionario->grado,
            'escalafon' => $funcionario->escalafon,
            'calidad_juridica' => $funcionario->calidad_juridica,
            'estamento' => $funcionario->escalafon,
            'correo_funcionario' => $funcionario->email,
            'fuente' => 'funcionarios_ac_autorizados',
            'periodo' => $funcionario->periodo_nomina,
            'nombre_funcionario' => $nombre !== '' ? $nombre : null,
        ]);
    }

    private function buscarReemplazosPersonal(string $rutNorm, array $base): array
    {
        try {
            if (! Schema::hasTable('reemplazos_personal')) {
                return array_replace($base, ['advertencia' => 'Padrón no disponible. Se conservan los datos manuales sin asociación automática.']);
            }

            $padron = app(PadronVigenciaService::class)->porRut($rutNorm);
            $registros = $padron['vigentes'];
            if ($registros->isEmpty()) {
                return array_replace($base, ['advertencia' => $padron['tiene_antecedentes']
                    ? 'El RUT tiene antecedentes históricos, pero no contrato vigente en el padrón. Se conservan los datos manuales sin asociación automática.'
                    : 'El RUT no se encuentra en el padrón. Se conservan los datos manuales sin asociación automática.']);
            }
            if ($registros->contains(fn ($row) => ! $row->establecimiento_id || ! $row->establecimiento)) {
                return array_replace($base, ['advertencia' => 'Hay contratos actuales sin establecimiento válido. Regularice el padrón; no se asignó un establecimiento automáticamente.']);
            }
            if ($registros->pluck('establecimiento_id')->unique()->count() !== 1) {
                return array_replace($base, ['advertencia' => 'El RUT tiene contratos vigentes en más de un establecimiento. Revise la dependencia de esta licencia; no se seleccionó un RBD automáticamente.']);
            }
            $registro = $registros->first();
            $calidades = $registros->pluck('tipocontrato')->filter()->unique();
            $estamentos = $registros->map(fn ($row) => $row->escalafon ?: $row->estatuto)->filter()->unique();

            return array_merge($base, [
                'tipo_dependencia' => 'establecimiento',
                'establecimiento_id' => $registro->establecimiento_id,
                'establecimiento_nombre' => optional($registro->establecimiento)->nombre ?: optional($registro->establecimiento)->nombre_establecimiento ?: $base['establecimiento_nombre'],
                'comuna' => optional($registro->establecimiento)->comuna ?: $base['comuna'],
                'calidad_juridica' => $calidades->count() === 1 ? $calidades->first() : null,
                'estamento' => $estamentos->count() === 1 ? $estamentos->first() : null,
                'fuente' => 'reemplazos_personal_vigente',
                'periodo' => sprintf('%04d-%02d', $registro->anio, $registro->mes),
                'advertencia' => $calidades->count() > 1 || $estamentos->count() > 1
                    ? 'La dependencia es única, pero hay distintas calidades jurídicas o estamentos vigentes. Revise esos datos manuales.' : null,
            ]);
        } catch (\Throwable $e) {
            return array_replace($base, ['advertencia' => 'No fue posible consultar el padrón. Se conservan los datos manuales sin asociación automática; revise la consulta antes de usar esta dependencia.']);
        }
    }
}

<?php

namespace App\Services\LicenciasMedicas;

use App\Models\Establecimiento;
use App\Models\FuncionarioAcAutorizado;
use App\Models\ReemplazoPersonal;
use Illuminate\Support\Facades\Schema;

class LicenciaFuncionarioResolver
{
    /**
     * Resuelve si el funcionario pertenece a Administracion Central o a un establecimiento.
     * Prioridad: funcionarios_ac_autorizados; si no existe, reemplazos_personal del mes mas reciente.
     */
    public function resolve(?string $rutNormalizado, ?string $rutCuerpo, ?string $establecimientoManual = null, ?string $comunaManual = null): array
    {
        $base = $this->base($establecimientoManual, $comunaManual);

        $rutNorm = $this->cleanRut($rutNormalizado);
        $rutDigits = preg_replace('/\D/', '', (string) $rutCuerpo);

        if ($rutNorm !== '' || $rutDigits !== '') {
            $ac = $this->buscarFuncionarioAc($rutNorm, $rutDigits);
            if ($ac) {
                return $this->desdeFuncionarioAc($ac, $base);
            }
        }

        return $this->buscarReemplazosPersonal($rutNorm, $rutDigits, $base);
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
                        $query->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(run), '.', ''), '-', ''), ' ', '') = ?", [$rutDigits]);
                        $query->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(run), '.', ''), '-', ''), ' ', '') LIKE ?", [$rutDigits . '%']);
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

    private function buscarReemplazosPersonal(string $rutNorm, string $rutDigits, array $base): array
    {
        try {
            if (! Schema::hasTable('reemplazos_personal')) {
                return $base;
            }

            $periodo = ReemplazoPersonal::query()
                ->select('anio', 'mes')
                ->whereNotNull('anio')
                ->whereNotNull('mes')
                ->orderByDesc('anio')
                ->orderByDesc('mes')
                ->first();

            if (! $periodo) {
                return $base;
            }

            $registro = ReemplazoPersonal::query()
                ->with('establecimiento')
                ->where('anio', $periodo->anio)
                ->where('mes', $periodo->mes)
                ->where(function ($q) use ($rutDigits, $rutNorm) {
                    if ($rutNorm !== '') {
                        $q->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(rut), '.', ''), '-', ''), ' ', '') = ?", [$rutNorm]);
                    }
                    if ($rutDigits !== '') {
                        $q->orWhereRaw("REPLACE(REPLACE(REPLACE(UPPER(rut), '.', ''), '-', ''), ' ', '') LIKE ?", [$rutDigits . '%']);
                    }
                })
                ->first();

            if (! $registro) {
                $base['periodo'] = sprintf('%04d-%02d', $periodo->anio, $periodo->mes);
                return $base;
            }

            return array_merge($base, [
                'tipo_dependencia' => 'establecimiento',
                'establecimiento_id' => $registro->establecimiento_id,
                'establecimiento_nombre' => optional($registro->establecimiento)->nombre ?: optional($registro->establecimiento)->nombre_establecimiento ?: $base['establecimiento_nombre'],
                'comuna' => optional($registro->establecimiento)->comuna ?: $base['comuna'],
                'calidad_juridica' => $registro->tipocontrato,
                'estamento' => $registro->escalafon ?: $registro->estatuto,
                'fuente' => 'reemplazos_personal_mes_reciente',
                'periodo' => sprintf('%04d-%02d', $periodo->anio, $periodo->mes),
            ]);
        } catch (\Throwable $e) {
            return $base;
        }
    }

    private function cleanRut(?string $value): string
    {
        return preg_replace('/[^0-9K]/', '', strtoupper((string) $value)) ?: '';
    }
}

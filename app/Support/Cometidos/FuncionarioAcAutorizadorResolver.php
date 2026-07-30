<?php

namespace App\Support\Cometidos;

use App\Models\FuncionarioAcAutorizado;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FuncionarioAcAutorizadorResolver
{
    public function funcionarioParaUsuario(User $user): ?FuncionarioAcAutorizado
    {
        if (! Schema::hasTable('funcionarios_ac_autorizados')) {
            return null;
        }

        $rut = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) ($user->rut ?? '')));
        if ($rut === '') {
            return null;
        }

        return FuncionarioAcAutorizado::query()
            ->where(function ($q) use ($rut) {
                $q->whereRaw("REPLACE(REPLACE(REPLACE(UPPER(COALESCE(rut_normalizado, '')), '.', ''), '-', ''), ' ', '') = ?", [$rut])
                    ->orWhereRaw("CONCAT(REPLACE(REPLACE(REPLACE(UPPER(COALESCE(run, '')), '.', ''), '-', ''), ' ', ''), UPPER(COALESCE(dv, ''))) = ?", [$rut]);
            })
            ->where(function ($q) {
                $q->where('estado_autorizacion', 'activo')->orWhereNull('estado_autorizacion');
            })
            ->first();
    }

    public function autorizadorPara(FuncionarioAcAutorizado $funcionario): ?array
    {
        if (! Schema::hasTable('funcionarios_ac_jefaturas_dependencias')) {
            return null;
        }

        $dependenciaSolicitante = (string) ($funcionario->subdireccion_dependencia ?? '');
        $esJefatura = (bool) ($funcionario->jefatura ?? false);

        // Regla especial: si el solicitante es el Director Ejecutivo, no puede autoautorizarse.
        // En ese caso autoriza obligatoriamente el Subrogante 1 registrado en la matriz de Dirección Ejecutiva.
        if ($this->esDirectorEjecutivo($funcionario)) {
            return $this->autorizadorSubroganteDirectorEjecutivo();
        }

        // Regla jerárquica: las jefaturas/subdirectores(as) son autorizadas por Dirección Ejecutiva.
        $dependenciaAutorizadora = $esJefatura ? 'Dirección Ejecutiva' : $dependenciaSolicitante;
        if ($dependenciaAutorizadora === '') {
            return null;
        }

        $registro = $this->registroDependencia($dependenciaAutorizadora);
        if (! $registro) {
            return null;
        }

        if ((bool) ($registro->subrogancia_activa ?? false)) {
            $nivel = (int) ($registro->subrogante_activo_nivel ?? 0);
            if (in_array($nivel, [1, 2, 3], true)) {
                $id = $registro->{'subrogante_' . $nivel . '_funcionario_ac_id'} ?? null;
                if ($id) {
                    return $this->payloadAutorizador((int) $id, true, $nivel, $dependenciaAutorizadora, 'subrogancia_activa');
                }
            }
        }

        if (! empty($registro->jefatura_funcionario_ac_id)) {
            return $this->payloadAutorizador((int) $registro->jefatura_funcionario_ac_id, false, null, $dependenciaAutorizadora, $esJefatura ? 'director_autoriza_jefatura' : 'jefatura_directa');
        }

        return null;
    }

    public function usuarioPuedeAutorizar(User $user, FuncionarioAcAutorizado $autorizador): bool
    {
        $rutUser = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) ($user->rut ?? '')));
        $rutAutorizador = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) (($autorizador->rut_normalizado ?: ($autorizador->run . $autorizador->dv)) ?? '')));
        return $rutUser !== '' && $rutUser === $rutAutorizador;
    }

    public function esDirectorEjecutivo(?FuncionarioAcAutorizado $funcionario): bool
    {
        if (! $funcionario) {
            return false;
        }

        $registroDireccion = $this->registroDependencia('Dirección Ejecutiva');
        if ($registroDireccion && ! empty($registroDireccion->jefatura_funcionario_ac_id)
            && (int) $registroDireccion->jefatura_funcionario_ac_id === (int) $funcionario->id) {
            return true;
        }

        $texto = $this->normaliza(collect([
            $funcionario->cargo_funcion ?? '',
            $funcionario->unidad_departamento ?? '',
            $funcionario->subdireccion_dependencia ?? '',
            $funcionario->observaciones ?? '',
        ])->filter()->implode(' '));

        if (str_contains($texto, 'DIRECTOR EJECUTIVO')) {
            return true;
        }

        return (bool) ($funcionario->jefatura ?? false)
            && str_contains($this->normaliza((string) ($funcionario->subdireccion_dependencia ?? '')), 'DIRECCION EJECUTIVA')
            && str_contains($texto, 'DIRECTOR');
    }

    private function autorizadorSubroganteDirectorEjecutivo(): ?array
    {
        $registro = $this->registroDependencia('Dirección Ejecutiva');
        if (! $registro) {
            return null;
        }

        $idSubrogante1 = $registro->subrogante_1_funcionario_ac_id ?? null;
        if (! $idSubrogante1) {
            return null;
        }

        return $this->payloadAutorizador((int) $idSubrogante1, true, 1, 'Dirección Ejecutiva', 'subrogante_1_director_ejecutivo');
    }

    private function registroDependencia(string $dependencia): ?object
    {
        if (! Schema::hasTable('funcionarios_ac_jefaturas_dependencias')) {
            return null;
        }

        $normalizada = $this->normaliza($dependencia);

        return DB::table('funcionarios_ac_jefaturas_dependencias')
            ->where(function ($q) use ($dependencia, $normalizada) {
                $q->where('subdireccion_dependencia', $dependencia)
                    ->orWhereRaw("UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(subdireccion_dependencia, ''), 'á', 'A'), 'é', 'E'), 'í', 'I'), 'ó', 'O'), 'ú', 'U'), 'Á', 'A'), 'É', 'E'), 'Í', 'I')) = ?", [$normalizada]);
            })
            ->where(function ($q) {
                $q->where('activo', true)->orWhereNull('activo');
            })
            ->first();
    }

    private function payloadAutorizador(int $funcionarioAcId, bool $subrogante, ?int $nivel, string $dependencia, string $tipoAutorizacion = 'jefatura_directa'): ?array
    {
        $funcionario = FuncionarioAcAutorizado::find($funcionarioAcId);
        if (! $funcionario) {
            return null;
        }

        return [
            'funcionario' => $funcionario,
            'es_subrogante' => $subrogante,
            'nivel_subrogancia' => $nivel,
            'dependencia_autorizadora' => $dependencia,
            'tipo_autorizacion' => $tipoAutorizacion,
        ];
    }

    private function normaliza(?string $texto): string
    {
        $texto = mb_strtoupper(trim((string) $texto), 'UTF-8');
        $texto = strtr($texto, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ü' => 'U', 'ñ' => 'N',
        ]);
        $texto = preg_replace('/[^A-Z0-9]+/u', ' ', $texto) ?: '';
        return trim(preg_replace('/\s+/', ' ', $texto) ?: '');
    }
}

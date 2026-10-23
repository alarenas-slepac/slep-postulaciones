<?php

namespace App\Services\Funcionarios;

use App\Models\FuncionarioAcAutorizado;
use App\Models\ReemplazoPersonal;
use App\Services\Padron\PadronPeriodoService;
use App\Services\Padron\PadronVigenciaService;
use Illuminate\Support\Collection;

class FuncionarioConsultaService
{
    public function consultar(string $rut): array
    {
        // Igualdad sobre columnas generadas e indexadas. Nunca consultar usuarios para clasificar.
        $rows = ReemplazoPersonal::where('rut_consulta_api', $rut)
            ->select(['id', 'nombre', 'rbd', 'establecimiento_id', 'estatuto', 'escalafon', 'anio', 'mes', 'vigente'])
            ->with('establecimiento:id,rbd,nombre_establecimiento,comuna')
            ->orderByDesc('anio')->orderByDesc('mes')->orderByDesc('id')->get();
        $currentIds = app(PadronVigenciaService::class)->consultaActual()
            ->where('rut_consulta_api', $rut)->pluck('reemplazos_personal.id');
        $max = $rows->contains(fn ($row) => !$row->establecimiento_id)
            ? app(PadronPeriodoService::class)->periodoMaximo() : null;
        $current = $rows->filter(fn ($row) => $currentIds->contains($row->id)
            || (!$row->establecimiento_id && $row->vigente && $row->anio * 100 + $row->mes === $max));
        $ac = FuncionarioAcAutorizado::where('rut_consulta_api', $rut)->get([
            'rut_normalizado', 'apellido_paterno', 'apellido_materno', 'nombres', 'unidad_departamento',
            'subdireccion_dependencia', 'jefatura', 'estado_autorizacion', 'fecha_inicio_autorizacion', 'fecha_fin_autorizacion',
        ]);
        $activeAc = $ac->filter(fn ($row) => $row->estaActivo());

        $base = ['encontrado' => $rows->isNotEmpty() || $ac->isNotEmpty(), 'clasificacion' => null,
            'estado' => 'sin_antecedentes', 'fuente' => null, 'funcionario' => null, 'advertencias' => []];
        if ($activeAc->count() > 1 || ($activeAc->isNotEmpty() && $current->isNotEmpty())) {
            return array_replace($base, ['clasificacion' => 'revision_requerida', 'estado' => 'revision_requerida',
                'coincidencias' => [
                    'establecimiento' => $current->isNotEmpty() ? $this->escuela($rut, $current) : null,
                    'administracion_central' => $activeAc->map(fn ($row) => $this->central($rut, $row))->values()->all(),
                ], 'advertencias' => ['Existen coincidencias actuales que requieren revisar la dependencia.']]);
        }
        if ($current->isNotEmpty()) {
            $warnings = [];
            if ($current->contains(fn ($row) => !$row->establecimiento)) {
                $warnings[] = 'Hay vínculos actuales sin un establecimiento asociado válido. Regulariza el padrón.';
            }
            if ($current->pluck('nombre')->map(fn ($name) => mb_strtoupper(trim($name)))->unique()->count() > 1) {
                $warnings[] = 'Los vínculos actuales contienen nombres diferentes. Revisa el padrón.';
            }
            return array_replace($base, ['clasificacion' => 'establecimiento',
                'estado' => $warnings ? 'revision_requerida' : 'padron_actual', 'fuente' => 'reemplazos_personal',
                'funcionario' => $this->escuela($rut, $current), 'advertencias' => $warnings]);
        }
        if ($activeAc->isNotEmpty()) {
            return array_replace($base, ['clasificacion' => 'administracion_central', 'estado' => 'autorizacion_activa',
                'fuente' => 'funcionarios_ac_autorizados', 'funcionario' => $this->central($rut, $activeAc->first()),
                'advertencias' => ['La autorización del SGA no constituye una certificación de vigencia laboral.']]);
        }
        if ($base['encontrado']) {
            // No devolver como actual una identidad o dependencia obtenida sólo de antecedentes antiguos.
            return array_replace($base, ['estado' => 'sin_vinculo_actual_confirmado',
                'advertencias' => ['Existen antecedentes históricos o autorizaciones inactivas/futuras, sin dependencia actual confirmada.']]);
        }
        return $base;
    }

    private function escuela(string $rut, Collection $rows): array
    {
        return ['rut_normalizado' => $rut, 'nombre' => $this->text($rows->first()->nombre),
            'vinculos' => $rows->map(fn ($row) => [
                'rbd' => $row->establecimiento?->rbd !== null ? (int) $row->establecimiento->rbd : $row->rbd,
                'establecimiento' => $this->text($row->establecimiento?->nombre_establecimiento),
                'estatuto' => $this->text($row->estatuto), 'escalafon' => $this->text($row->escalafon),
                'periodo' => sprintf('%04d-%02d', $row->anio, $row->mes),
            ])->unique(fn ($row) => json_encode($row))->values()->all()];
    }

    private function central(string $rut, FuncionarioAcAutorizado $row): array
    {
        return ['rut_normalizado' => $rut,
            'apellido_paterno' => $this->text($row->apellido_paterno),
            'apellido_materno' => $this->text($row->apellido_materno), 'nombres' => $this->text($row->nombres),
            'unidad_departamento' => $this->text($row->unidad_departamento),
            'subdireccion_dependencia' => $this->text($row->subdireccion_dependencia),
            'jefatura' => (int) (bool) $row->jefatura];
    }

    private function text(?string $value): ?string
    {
        return trim((string) $value) !== '' ? trim($value) : null;
    }
}

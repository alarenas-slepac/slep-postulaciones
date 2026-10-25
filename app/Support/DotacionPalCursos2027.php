<?php

namespace App\Support;

use App\Models\Establecimiento;
use Illuminate\Validation\ValidationException;

/** Unidades curriculares del PAL: planes requeridos, sin usar cobertura asignada. */
class DotacionPalCursos2027
{
    public function construir(Establecimiento $ee, array $data): array
    {
        $niveles = (array) data_get($data, 'cursos.rows', []);
        $grupos = collect(data_get($data, 'cursos_combinados.grupos', []))->where('activo', true)->values();
        $detalles = []; $etiquetas = [];
        foreach ($niveles as $nivel) {
            foreach ($nivel['detalles'] ?? [] as $detalle) {
                $id = (int) ($detalle['establecimiento_curso_id'] ?? 0);
                $detalles[$id] = $detalle;
                $etiquetas[$id] = trim((string) ($detalle['nombre_seccion'] ?? ''))
                    ?: trim(($nivel['label'] ?? 'Curso').' '.($detalle['letra'] ?? ''));
            }
        }
        $filasCombinadas = []; $combinados = []; $advertencias = [];
        foreach ($grupos as $grupo) {
            $miembros = collect(data_get($grupo, 'miembros', []))
                ->unique(fn ($m) => (int) data_get($m, 'id'))->values();
            if ($miembros->isEmpty()) { continue; }
            $nombres = []; $matricula = 0; $matriculaCompleta = true;
            foreach ($miembros as $miembro) {
                $id = (int) data_get($miembro, 'id');
                if (isset($combinados[$id])) {
                    throw ValidationException::withMessages(['pal' => 'El RBD '.$ee->rbd.' tiene un curso en más de una combinación activa. Revise los grupos antes de descargar el PAL.']);
                }
                $combinados[$id] = true;
                $nombre = trim((string) data_get($miembro, 'label', '')) ?: ($etiquetas[$id] ?? 'Curso sin detalle');
                $nombres[] = $nombre;
                $alumnos = data_get($miembro, 'matricula', $detalles[$id]['matricula'] ?? null);
                if ($alumnos === null) { $matriculaCompleta = false; }
                else { $matricula += (int) $alumnos; }
            }
            $nombreGrupo = trim((string) data_get($grupo, 'nombre', ''));
            $label = 'Curso combinado'.($nombreGrupo !== '' ? ' «'.$nombreGrupo.'»' : '').': '.implode(' + ', $nombres);
            $horas = data_get($grupo, 'totales.horas_requeridas');
            $contrato = data_get($grupo, 'totales.horas_contrato');
            if ($horas === null || (float) $horas <= 0) {
                $horas = null; $label .= ' (plan pendiente de configurar)';
                $advertencias[] = $label.'.';
            }
            if (! $matriculaCompleta) { $advertencias[] = $label.': falta el detalle de matrícula de sus integrantes.'; }
            // E=1 unidad de plan compartida; sus cursos administrativos se
            // detallan en C. Así la fórmula G=E*F no multiplica el plan común.
            $filasCombinadas[] = $this->fila($ee, $label, $matriculaCompleta ? $matricula : null, 1,
                $horas === null ? null : (float) $horas, $contrato === null ? null : (float) $contrato);
        }

        $filas = [];
        foreach ($niveles as $nivel) {
            if ((int) ($nivel['cursos'] ?? 0) === 0) { continue; }
            $independientes = collect($nivel['detalles'] ?? [])
                ->reject(fn ($d) => isset($combinados[(int) ($d['establecimiento_curso_id'] ?? 0)]))->values();
            if ($independientes->isEmpty()) { continue; }
            $porPlan = $independientes->groupBy(function ($d): string {
                $horas = $this->horasPlan($d);
                return $horas === null ? 'sin_plan' : number_format($horas, 4, '.', '');
            });
            foreach ($porPlan as $cursos) {
                $horas = $this->horasPlan($cursos->first());
                $label = (string) ($nivel['label'] ?? 'Nivel sin identificar');
                if ($porPlan->count() > 1 || $independientes->count() !== count($nivel['detalles'] ?? [])) {
                    $label = $cursos->map(fn ($d) => $etiquetas[(int) $d['establecimiento_curso_id']] ?? $label)->implode(', ');
                }
                if ($horas === null) {
                    $label .= ' (plan pendiente de configurar)';
                    $advertencias[] = $label.'.';
                }
                $contratos = $cursos->map(fn ($d) => $this->contratoPlan($d));
                $matricula = $cursos->contains(fn ($d) => ! isset($d['matricula'])) ? null : (int) $cursos->sum('matricula');
                $filas[] = $this->fila($ee, $label, $matricula, $cursos->count(), $horas,
                    $contratos->containsStrict(null) ? null : (float) $contratos->sum());
            }
        }
        array_push($filas, ...$filasCombinadas);
        if ($filas === []) { $advertencias[] = 'No hay cursos activos con matrícula registrados para 2027.'; }
        return ['filas' => $filas, 'advertencias' => array_values(array_unique($advertencias))];
    }

    private function horasPlan(array $detalle): ?float
    {
        $horas = $detalle['horas_plan_base'] ?? (isset($detalle['horas'])
            ? (float) $detalle['horas'] - (float) ($detalle['horas_plan_refuerzo_ld_otro_docente'] ?? 0) : null);
        return $horas !== null && (float) $horas > 0 ? (float) $horas : null;
    }

    private function contratoPlan(array $detalle): ?float
    {
        $horas = $detalle['horas_contrato_base'] ?? (isset($detalle['horas_contrato_equivalente_redondeado'])
            ? (float) $detalle['horas_contrato_equivalente_redondeado'] - (float) ($detalle['horas_contrato_refuerzo_ld_otro_docente'] ?? 0) : null);
        return $horas === null ? null : max(0, (float) $horas);
    }

    private function fila(Establecimiento $ee, string $label, ?int $matricula, int $cantidad, ?float $horas, ?float $contrato): array
    {
        return ['A' => (string) $ee->nombre_establecimiento, 'B' => (string) $ee->rbd, 'C' => $label,
            'D' => $matricula, 'E' => $cantidad, 'F' => $horas, 'H' => $contrato];
    }
}

<?php

use App\Models\DeclaracionSostenedor;
use App\Models\DotacionDocenteAsignacion;
use App\Models\EstablecimientoCurso;
use App\Support\DotacionProfesionDocenteResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dotacion_docente_asignaciones')
            || ! Schema::hasTable('establecimiento_cursos')) {
            return;
        }

        DotacionDocenteAsignacion::query()
            ->where('tipo_asignacion', 'plan_estudio')
            ->where('estado', 'activa')
            ->whereNotNull('establecimiento_curso_id')
            ->where(function ($query) {
                $query->whereNull('estamento_cobertura')
                    ->orWhere('estamento_cobertura', 'docente');
            })
            ->orderBy('id')
            ->chunkById(200, function ($asignaciones): void {
                $cursoIds = $asignaciones->pluck('establecimiento_curso_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();
                $cursos = EstablecimientoCurso::query()
                    ->with(['curso', 'planEstudio'])
                    ->whereIn('id', $cursoIds)
                    ->get()
                    ->keyBy('id');

                $declaracionIds = $asignaciones->pluck('declaracion_sostenedor_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();
                $declaraciones = DeclaracionSostenedor::query()
                    ->whereIn('id', $declaracionIds)
                    ->get()
                    ->keyBy('id');

                foreach ($asignaciones as $asignacion) {
                    $curso = $cursos->get((int) $asignacion->establecimiento_curso_id);
                    if (! $curso || ! DotacionProfesionDocenteResolver::esCursoNt($curso)) {
                        continue;
                    }

                    $declaracion = $asignacion->declaracion_sostenedor_id
                        ? $declaraciones->get((int) $asignacion->declaracion_sostenedor_id)
                        : null;
                    $persona = [
                        'titulo' => $declaracion?->nombre_titulo ?: 'Sin título declarado',
                        'declaracion' => $declaracion,
                    ];
                    $conversion = DotacionProfesionDocenteResolver::conversionNt(
                        $curso,
                        (float) ($asignacion->horas_plan_pedagogicas ?? 0),
                        $persona,
                        (string) ($asignacion->proporcion_aplicada ?? '')
                    );

                    if ($conversion === null) {
                        continue;
                    }

                    $asignacion->forceFill([
                        'horas_contrato' => (float) ($conversion['horas_contrato_equivalente_redondeado'] ?? 0),
                        'horas_cronologicas_aula' => (float) ($conversion['horas_aula_cronologicas'] ?? 0),
                        'proporcion_aplicada' => (string) ($conversion['proporcion_label'] ?? '65/35'),
                        'fuente_calculo' => 'Recalculada por profesión declarada NT1/NT2 · '
                            .($conversion['origen_proporcion_label'] ?? 'Regla profesional')
                            .' · '.($conversion['motivo'] ?? ''),
                        'updated_at' => now(),
                    ])->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // No se revierte el cálculo para evitar restaurar equivalencias contractuales incorrectas.
    }
};

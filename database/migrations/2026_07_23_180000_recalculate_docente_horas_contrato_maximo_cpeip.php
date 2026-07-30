<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PROPORTIONS_TABLE = 'docente_horas_proporciones';

    private const ASSIGNMENTS_TABLE = 'dotacion_docente_asignaciones';

    public function up(): void
    {
        $this->recalculateActivePlanAssignments(true, 'criterio CPEIP corregido: mayor jornada contractual en tramos repetidos');
    }

    public function down(): void
    {
        $this->recalculateActivePlanAssignments(false, 'criterio anterior restaurado: menor jornada contractual en tramos repetidos');
    }

    /**
     * Recalcula las asignaciones curriculares activas que usan 65/35 o 60/40.
     *
     * La tabla CPEIP se encuentra definida desde horas de contrato hacia horas
     * aula. Cuando al invertirla una cantidad de horas aula aparece en más de
     * una jornada, el criterio vigente debe seleccionar la jornada contractual
     * mayor. El rollback conserva la posibilidad de volver al criterio anterior.
     */
    private function recalculateActivePlanAssignments(bool $preferMaximumContract, string $sourceLabel): void
    {
        if (! $this->requiredStructureExists()) {
            return;
        }

        $proportions = $this->loadProportions();

        if (($proportions['65_35'] ?? collect())->isEmpty()
            || ($proportions['60_40'] ?? collect())->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($proportions, $preferMaximumContract, $sourceLabel): void {
            $query = DB::table(self::ASSIGNMENTS_TABLE)
                ->where('tipo_asignacion', 'plan_estudio')
                ->where('estado', 'activa')
                ->whereIn('proporcion_aplicada', ['65/35', '60/40', '65_35', '60_40'])
                ->whereNotNull('horas_plan_pedagogicas');

            if (Schema::hasColumn(self::ASSIGNMENTS_TABLE, 'estamento_cobertura')) {
                $query->where(function ($query): void {
                    $query->whereNull('estamento_cobertura')
                        ->orWhere('estamento_cobertura', '!=', 'asistente');
                });
            }

            $query
                ->select(['id', 'horas_plan_pedagogicas', 'proporcion_aplicada'])
                ->orderBy('id')
                ->chunkById(250, function ($assignments) use ($proportions, $preferMaximumContract, $sourceLabel): void {
                    $now = now();

                    foreach ($assignments as $assignment) {
                        $proportion = str_contains((string) $assignment->proporcion_aplicada, '60')
                            ? '60_40'
                            : '65_35';
                        $classroomHours = round(max(0.0, (float) $assignment->horas_plan_pedagogicas), 2);
                        $contractHours = $this->contractHoursForClassroomHours(
                            $proportions[$proportion],
                            $classroomHours,
                            $preferMaximumContract
                        );

                        $payload = [
                            'horas_contrato' => $contractHours,
                        ];

                        if (Schema::hasColumn(self::ASSIGNMENTS_TABLE, 'horas_cronologicas_aula')) {
                            $payload['horas_cronologicas_aula'] = round($classroomHours * 45 / 60, 2);
                        }

                        if (Schema::hasColumn(self::ASSIGNMENTS_TABLE, 'fuente_calculo')) {
                            $payload['fuente_calculo'] = sprintf(
                                'Conversión automática desde %s · proporción %s',
                                $sourceLabel,
                                str_replace('_', '/', $proportion)
                            );
                        }

                        if (Schema::hasColumn(self::ASSIGNMENTS_TABLE, 'updated_at')) {
                            $payload['updated_at'] = $now;
                        }

                        DB::table(self::ASSIGNMENTS_TABLE)
                            ->where('id', $assignment->id)
                            ->update($payload);
                    }
                }, 'id');
        });
    }

    private function requiredStructureExists(): bool
    {
        if (! Schema::hasTable(self::PROPORTIONS_TABLE)
            || ! Schema::hasTable(self::ASSIGNMENTS_TABLE)) {
            return false;
        }

        foreach ([
            'tipo_asignacion',
            'estado',
            'horas_plan_pedagogicas',
            'horas_contrato',
            'proporcion_aplicada',
        ] as $column) {
            if (! Schema::hasColumn(self::ASSIGNMENTS_TABLE, $column)) {
                return false;
            }
        }

        foreach ([
            'proporcion',
            'horas_contrato',
            'horas_aula_pedagogicas',
            'vigente',
        ] as $column) {
            if (! Schema::hasColumn(self::PROPORTIONS_TABLE, $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, Collection<int, object>>
     */
    private function loadProportions(): array
    {
        $rows = DB::table(self::PROPORTIONS_TABLE)
            ->whereIn('proporcion', ['65_35', '60_40'])
            ->where('vigente', true)
            ->orderBy('horas_aula_pedagogicas')
            ->orderByDesc('horas_contrato')
            ->get(['proporcion', 'horas_contrato', 'horas_aula_pedagogicas']);

        return [
            '65_35' => $rows->where('proporcion', '65_35')->values(),
            '60_40' => $rows->where('proporcion', '60_40')->values(),
        ];
    }

    /**
     * Invierte la tabla oficial de contrato -> aula.
     *
     * Si un tramo de horas aula está repetido, selecciona la jornada contractual
     * máxima durante el up(). Para horas fraccionadas toma el menor tramo de aula
     * que cubra la carga y aplica el mismo criterio dentro de ese tramo.
     *
     * @param Collection<int, object> $rows
     */
    private function contractHoursForClassroomHours(
        Collection $rows,
        float $classroomHours,
        bool $preferMaximumContract
    ): float {
        if ($classroomHours <= 0 || $rows->isEmpty()) {
            return 0.0;
        }

        $maximumClassroom = (float) $rows->max('horas_aula_pedagogicas');
        $maximumContract = $this->contractForClassroomBucket(
            $rows,
            $maximumClassroom,
            $preferMaximumContract
        );
        $remaining = $classroomHours;
        $contractTotal = 0.0;

        while ($remaining > $maximumClassroom) {
            $contractTotal += $maximumContract;
            $remaining = round($remaining - $maximumClassroom, 2);
        }

        if ($remaining <= 0) {
            return $contractTotal;
        }

        $eligible = $rows->filter(
            fn ($row) => (float) $row->horas_aula_pedagogicas >= $remaining
        );

        if ($eligible->isEmpty()) {
            return $contractTotal + $maximumContract;
        }

        $targetClassroom = (float) $eligible->min('horas_aula_pedagogicas');

        return $contractTotal + $this->contractForClassroomBucket(
            $eligible,
            $targetClassroom,
            $preferMaximumContract
        );
    }

    /**
     * @param Collection<int, object> $rows
     */
    private function contractForClassroomBucket(
        Collection $rows,
        float $classroomHours,
        bool $preferMaximumContract
    ): float {
        $contracts = $rows
            ->filter(fn ($row) => (float) $row->horas_aula_pedagogicas === $classroomHours)
            ->pluck('horas_contrato')
            ->map(fn ($hours) => (float) $hours);

        if ($contracts->isEmpty()) {
            return 0.0;
        }

        return $preferMaximumContract
            ? (float) $contracts->max()
            : (float) $contracts->min();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PROPORTIONS_TABLE = 'docente_horas_proporciones';

    /**
     * Correspondencia oficial CPEIP 2019 entre horas cronológicas de contrato
     * y horas pedagógicas máximas de docencia de aula.
     *
     * Fuente: CPEIP, "Incremento del tiempo no lectivo", Anexo 1.
     * La clave corresponde a horas cronológicas de función docente en contrato.
     */
    private const CPEIP_HORAS_AULA = [
        '65_35' => [
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 3,
            5 => 4,
            6 => 5,
            7 => 6,
            8 => 7,
            9 => 8,
            10 => 9,
            11 => 10,
            12 => 10,
            13 => 11,
            14 => 12,
            15 => 13,
            16 => 14,
            17 => 15,
            18 => 16,
            19 => 16,
            20 => 17,
            21 => 18,
            22 => 19,
            23 => 20,
            24 => 21,
            25 => 22,
            26 => 22,
            27 => 23,
            28 => 24,
            29 => 25,
            30 => 26,
            31 => 27,
            32 => 28,
            33 => 29,
            34 => 29,
            35 => 30,
            36 => 31,
            37 => 32,
            38 => 33,
            39 => 34,
            40 => 35,
            41 => 35,
            42 => 36,
            43 => 37,
            44 => 38,
        ],
        '60_40' => [
            1 => 1,
            2 => 2,
            3 => 2,
            4 => 3,
            5 => 4,
            6 => 5,
            7 => 6,
            8 => 6,
            9 => 7,
            10 => 8,
            11 => 9,
            12 => 10,
            13 => 10,
            14 => 11,
            15 => 12,
            16 => 13,
            17 => 14,
            18 => 14,
            19 => 15,
            20 => 16,
            21 => 17,
            22 => 18,
            23 => 18,
            24 => 19,
            25 => 20,
            26 => 21,
            27 => 21,
            28 => 22,
            29 => 23,
            30 => 24,
            31 => 25,
            32 => 25,
            33 => 26,
            34 => 27,
            35 => 28,
            36 => 29,
            37 => 29,
            38 => 30,
            39 => 31,
            40 => 32,
            41 => 33,
            42 => 33,
            43 => 34,
            44 => 35,
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::PROPORTIONS_TABLE)) {
            return;
        }

        DB::transaction(function (): void {
            $this->persistProportions(self::CPEIP_HORAS_AULA);
            $this->recalculateActivePlanAssignments(self::CPEIP_HORAS_AULA, 'tabla oficial CPEIP 2019 corregida');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::PROPORTIONS_TABLE)) {
            return;
        }

        DB::transaction(function (): void {
            $legacy = $this->legacyGeneratedProportions();
            $this->persistProportions($legacy);
            $this->recalculateActivePlanAssignments($legacy, 'tabla anterior restaurada por rollback');
        });
    }

    /**
     * @param array<string, array<int, int>> $proportions
     */
    private function persistProportions(array $proportions): void
    {
        $now = now();

        foreach ($proportions as $proportion => $rows) {
            foreach ($rows as $contractHours => $classroomHours) {
                $recessMinutes = (int) round($contractHours * 45 / 11);
                $classroomChronologicalMinutes = $classroomHours * 45;
                $nonTeachingMinutes = max(
                    0,
                    ($contractHours * 60) - $classroomChronologicalMinutes - $recessMinutes
                );

                $where = [
                    'proporcion' => $proportion,
                    'horas_contrato' => $contractHours,
                ];

                $values = [
                    'horas_aula_pedagogicas' => $classroomHours,
                    'horas_aula_cronologicas_minutos' => $classroomChronologicalMinutes,
                    'recreo_minutos' => $recessMinutes,
                    'horas_no_lectivas_minutos' => $nonTeachingMinutes,
                    'vigente' => true,
                    'updated_at' => $now,
                ];

                $updated = DB::table(self::PROPORTIONS_TABLE)
                    ->where($where)
                    ->update($values);

                if ($updated === 0 && ! DB::table(self::PROPORTIONS_TABLE)->where($where)->exists()) {
                    DB::table(self::PROPORTIONS_TABLE)->insert($where + $values + [
                        'created_at' => $now,
                    ]);
                }
            }
        }
    }

    /**
     * Recalcula únicamente asignaciones curriculares docentes que almacenaban
     * una conversión simple 65/35 o 60/40. Se excluyen AAEE y reglas especiales
     * NT1/NT2 para no alterar horas informadas manualmente ni cálculos propios.
     *
     * @param array<string, array<int, int>> $proportions
     */
    private function recalculateActivePlanAssignments(array $proportions, string $sourceLabel): void
    {
        $table = 'dotacion_docente_asignaciones';

        if (! Schema::hasTable($table)
            || ! Schema::hasColumn($table, 'horas_plan_pedagogicas')
            || ! Schema::hasColumn($table, 'horas_contrato')
            || ! Schema::hasColumn($table, 'proporcion_aplicada')) {
            return;
        }

        $query = DB::table($table)
            ->where('tipo_asignacion', 'plan_estudio')
            ->where('estado', 'activa')
            ->whereIn('proporcion_aplicada', ['65/35', '60/40'])
            ->whereNotNull('horas_plan_pedagogicas');

        if (Schema::hasColumn($table, 'estamento_cobertura')) {
            $query->where(function ($query): void {
                $query->whereNull('estamento_cobertura')
                    ->orWhere('estamento_cobertura', '!=', 'asistente');
            });
        }

        $columns = ['id', 'horas_plan_pedagogicas', 'proporcion_aplicada'];

        $query->select($columns)
            ->orderBy('id')
            ->chunkById(250, function ($assignments) use ($table, $proportions, $sourceLabel): void {
                foreach ($assignments as $assignment) {
                    $proportion = trim((string) $assignment->proporcion_aplicada) === '60/40'
                        ? '60_40'
                        : '65_35';
                    $classroomHours = round(max(0.0, (float) $assignment->horas_plan_pedagogicas), 2);
                    $contractHours = $this->contractHoursForClassroomHours(
                        $proportions[$proportion],
                        $classroomHours
                    );

                    $payload = [
                        'horas_contrato' => $contractHours,
                    ];

                    if (Schema::hasColumn($table, 'horas_cronologicas_aula')) {
                        $payload['horas_cronologicas_aula'] = round($classroomHours * 45 / 60, 2);
                    }

                    if (Schema::hasColumn($table, 'fuente_calculo')) {
                        $payload['fuente_calculo'] = sprintf(
                            'Conversión automática desde %s · proporción %s',
                            $sourceLabel,
                            str_replace('_', '/', $proportion)
                        );
                    }

                    if (Schema::hasColumn($table, 'updated_at')) {
                        $payload['updated_at'] = now();
                    }

                    DB::table($table)
                        ->where('id', $assignment->id)
                        ->update($payload);
                }
            }, 'id');
    }

    /**
     * Replica la conversión utilizada por DocenteHorasNoLectivasCalculator:
     * selecciona el contrato mínimo cuyo máximo de horas aula cubra la carga.
     * Si la carga supera el máximo tabulado, suma bloques de 44 horas contrato.
     *
     * @param array<int, int> $rows
     */
    private function contractHoursForClassroomHours(array $rows, float $classroomHours): float
    {
        if ($classroomHours <= 0) {
            return 0.0;
        }

        ksort($rows);
        $maximumContract = (int) array_key_last($rows);
        $maximumClassroom = (float) $rows[$maximumContract];
        $remaining = $classroomHours;
        $contractTotal = 0.0;

        while ($remaining > $maximumClassroom) {
            $contractTotal += $maximumContract;
            $remaining = round($remaining - $maximumClassroom, 2);
        }

        if ($remaining <= 0) {
            return $contractTotal;
        }

        foreach ($rows as $contractHours => $maximumClassroomHours) {
            if ((float) $maximumClassroomHours >= $remaining) {
                return $contractTotal + (float) $contractHours;
            }
        }

        return $contractTotal + (float) $maximumContract;
    }

    /**
     * Valores generados por el algoritmo anterior, usados sólo para rollback.
     *
     * @return array<string, array<int, int>>
     */
    private function legacyGeneratedProportions(): array
    {
        $rows = [
            '65_35' => [],
            '60_40' => [],
        ];

        foreach (range(1, 44) as $contractHours) {
            foreach ([
                '65_35' => 0.65,
                '60_40' => 0.60,
            ] as $proportion => $teachingRatio) {
                $classroomHours = max(
                    1,
                    (int) floor(($contractHours * 60 * $teachingRatio) / 45)
                );

                if ($proportion === '65_35' && $contractHours <= 3) {
                    $classroomHours = $contractHours;
                }

                if ($proportion === '60_40' && $contractHours === 2) {
                    $classroomHours = 2;
                }

                if ($contractHours === 44 && $proportion === '65_35') {
                    $classroomHours = 38;
                }

                if ($contractHours === 44 && $proportion === '60_40') {
                    $classroomHours = 35;
                }

                $rows[$proportion][$contractHours] = $classroomHours;
            }
        }

        return $rows;
    }
};

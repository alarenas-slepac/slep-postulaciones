<?php

namespace App\Services\Pie;

use App\Models\EstablecimientoCurso;
use App\Models\EstablecimientoCursoPie;
use App\Models\PieHorasApoyoMinimo;
use App\Support\PieHorasCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PieComparisonService
{
    public const HOUR_FIELDS = ['total_crono_minutos', 'prof_educ_dif_minutos', 'pae_minutos'];

    public function screen(int $establishmentId, array $years): array
    {
        $courses = $this->courses($establishmentId, $years)->get()->sortBy([
            fn ($a, $b) => ($a->curso->orden ?? 999) <=> ($b->curso->orden ?? 999),
            fn ($a, $b) => strnatcasecmp($a->curso->nombre, $b->curso->nombre),
            fn ($a, $b) => strnatcasecmp((string) $a->letra, (string) $b->letra),
        ]);
        $records = EstablecimientoCursoPie::whereIn('establecimiento_curso_id', $courses->modelKeys())
            ->whereIn('anio', $years)->get()->groupBy(fn ($record) => $record->establecimiento_curso_id.'|'.$record->anio);
        $rules = PieHorasApoyoMinimo::where('vigente', true)->orderBy('id')->get();
        $groups = []; $rows = []; $matrix = [];
        foreach ($courses as $course) {
            $matches = $records->get($course->id.'|'.$course->anio, collect());
            $record = $matches->first();
            $valid = $matches->count() <= 1 && $this->consistent($course, $record);
            $letter = mb_strtoupper(trim((string) $course->letra));
            $groupKey = $course->curso_id.'|'.$letter;
            $groups[$groupKey] ??= ['label' => $course->curso->nombre, 'letter' => $letter, 'years' => array_fill_keys($years, [])];
            $groups[$groupKey]['years'][$course->anio][] = (int) $course->id;
            $ruleKey = PieHorasCalculator::determineRegimen($course).(PieHorasCalculator::isEpja($course) ? ':epja' : '');
            if (!isset($matrix[$ruleKey])) {
                foreach ([0, 1, 5] as $neet) for ($neep = 0; $neep <= 32; $neep++) {
                    $matrix[$ruleKey][$neet][$neep] = $this->hours(PieHorasCalculator::calculate($course, $neet, $neep, $rules));
                }
            }
            $calculation = $record ? $this->hours($record->getAttributes()) : $this->hours(PieHorasCalculator::calculate($course, 0, 0, $rules));
            $rows[$course->id] = [
                'id' => (int) $course->id, 'year' => $course->anio, 'label' => $course->nombre_seccion ?: trim($course->curso->nombre.' '.$letter),
                'plan' => $course->planEstudio?->nombre_plan ?: 'Sin plan asociado', 'matricula' => $course->matricula,
                'regime' => PieHorasCalculator::determineRegimen($course), 'rule_key' => $ruleKey,
                'record_id' => $record?->id, 'state' => $record?->estadoLabel() ?: 'Sin registro PIE',
                'neet' => $record?->necesidades_transitorias ?? 0, 'neep' => $record?->necesidades_permanentes ?? 0,
                'calculation' => $calculation, 'version' => $this->revision($course, $record), 'editable' => $valid,
            ];
        }
        return ['groups' => array_values($groups), 'rows' => $rows, 'matrix' => $matrix, 'rules_token' => $this->rulesToken($rules)];
    }

    public function save(int $establishmentId, array $years, array $rows, string $rulesToken, int $actorId): int
    {
        return DB::transaction(function () use ($establishmentId, $years, $rows, $rulesToken, $actorId) {
            $ids = array_map(fn ($row) => (int) $row['course_id'], $rows);
            if (count(array_unique($ids)) !== count($rows)) $this->invalid('rows', 'No repitas un curso/sección en el guardado.');
            $courses = $this->courses($establishmentId, $years)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $records = EstablecimientoCursoPie::whereIn('establecimiento_curso_id', $ids)->whereIn('anio', $years)
                ->orderBy('id')->lockForUpdate()->get()->groupBy(fn ($record) => $record->establecimiento_curso_id.'|'.$record->anio);
            $rules = PieHorasApoyoMinimo::where('vigente', true)->orderBy('id')->lockForUpdate()->get();
            if (!hash_equals($this->rulesToken($rules), $rulesToken)) $this->invalid('rules_token', 'El catálogo de horas PIE cambió. Recarga la comparación antes de guardar.');
            $operations = [];
            foreach ($rows as $index => $row) {
                $course = $courses->get((int) $row['course_id']);
                if (!$course) $this->invalid("rows.$index.course_id", 'El curso ya no está activo o no pertenece al establecimiento y años seleccionados.');
                $matches = $records->get($course->id.'|'.$course->anio, collect());
                $record = $matches->first();
                if ($matches->count() > 1 || !$this->consistent($course, $record)) $this->invalid("rows.$index.course_id", 'El registro PIE tiene datos inconsistentes; revísalo antes de editarlo.');
                if (!hash_equals($this->revision($course, $record), $row['version'])) $this->invalid("rows.$index.version", 'El curso o su registro PIE cambió desde que abriste la comparación. Recarga los datos antes de guardar.');
                $neet = (int) $row['neet']; $neep = (int) $row['neep'];
                if ($neet + $neep > (int) $course->matricula) $this->invalid("rows.$index.neep", 'NEET + NEEP no puede superar la matrícula de '.$course->nombre_seccion.'.');
                if ($neet === (int) ($record?->necesidades_transitorias ?? 0) && $neep === (int) ($record?->necesidades_permanentes ?? 0)) continue;
                $payload = ['necesidades_transitorias' => $neet, 'necesidades_permanentes' => $neep, 'total_pie' => $neet + $neep,
                    'updated_by' => $actorId] + PieHorasCalculator::calculate($course, $neet, $neep, $rules);
                if (!$record) $payload += ['establecimiento_id' => $course->establecimiento_id, 'establecimiento_curso_id' => $course->id,
                    'curso_id' => $course->curso_id, 'plan_estudio_id' => $course->plan_estudio_id, 'anio' => $course->anio,
                    'rbd' => $course->rbd ?: $course->establecimiento?->rbd, 'estado' => 'borrador', 'created_by' => $actorId];
                $operations[] = [$record, $payload];
            }
            // Validar todo antes de escribir. La transacción revierte el lote ante cualquier fallo.
            foreach ($operations as [$record, $payload]) {
                if ($record) $record->update($payload);
                else EstablecimientoCursoPie::create($payload);
            }
            return count($operations);
        }, 3);
    }

    private function courses(int $establishmentId, array $years)
    {
        return EstablecimientoCurso::with(['curso', 'planEstudio', 'establecimiento:id,rbd'])->where('establecimiento_id', $establishmentId)
            ->whereIn('anio', $years)->where('activo', true)->whereHas('curso');
    }

    private function consistent(EstablecimientoCurso $course, ?EstablecimientoCursoPie $record): bool
    {
        return !$record || ((int) $record->establecimiento_id === (int) $course->establecimiento_id
            && ($record->curso_id === null || (int) $record->curso_id === (int) $course->curso_id) && (int) $record->anio === (int) $course->anio);
    }

    private function revision(EstablecimientoCurso $course, ?EstablecimientoCursoPie $record): string
    {
        return $this->token([$course->getRawOriginal(), $course->curso?->getRawOriginal(), $course->planEstudio?->getRawOriginal(), $record?->getRawOriginal()]);
    }

    private function rulesToken(Collection $rules): string { return $this->token($rules->map(fn ($rule) => $rule->getRawOriginal())->all()); }
    private function token(array $value): string { return hash_hmac('sha256', json_encode($value, JSON_THROW_ON_ERROR), (string) config('app.key')); }
    private function hours(array $data): array { return array_intersect_key($data, array_flip(array_merge(self::HOUR_FIELDS, ['calculo_observacion']))); }
    private function invalid(string $field, string $message): never { throw ValidationException::withMessages([$field => $message]); }
}

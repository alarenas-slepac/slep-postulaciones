<?php

namespace Database\Seeders;

use App\Models\PlanEstudio;
use App\Models\PlanEstudioBloque;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanEstudioBloquesSeeder extends Seeder
{
    public function run(): void
    {
        PlanEstudio::with('curso')
            ->orderBy('anio')
            ->orderBy('curso_id')
            ->orderBy('regimen_jec')
            ->get()
            ->each(function (PlanEstudio $plan): void {
                foreach ($this->bloquesParaPlan($plan) as $bloque) {
                    PlanEstudioBloque::updateOrCreate(
                        [
                            'plan_estudio_id' => $plan->id,
                            'tipo_bloque' => $bloque['tipo_bloque'],
                            'nombre' => $bloque['nombre'],
                        ],
                        $bloque
                    );
                }
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bloquesParaPlan(PlanEstudio $plan): array
    {
        $cursoNombre = $this->normalizar($plan->curso->nombre ?? '');
        $modalidad = $this->normalizar($plan->modalidad ?: ($plan->curso->modalidad ?? ''));
        $totalSemanal = $this->numero($plan->horas_semanales_total);
        $totalAnual = $this->numero($plan->horas_anuales_total);
        $semanas = $totalSemanal > 0 && $totalAnual > 0 ? $totalAnual / $totalSemanal : 38.0;

        if (in_array($cursoNombre, ['NT1', 'NT2'], true)) {
            return $this->bloquesParvulariaTransicion($plan, $semanas);
        }

        if (Str::contains($cursoNombre, ['3 MEDIO HC', '4 MEDIO HC']) || Str::contains($modalidad, 'HUMANISTICO')) {
            return $this->bloquesMediaDiferenciada(
                plan: $plan,
                semanas: $semanas,
                tipoDiferenciado: 'plan_diferenciado_hc',
                nombreDiferenciado: 'Plan diferenciado Humanístico-Científico',
                horasDiferenciado: 18.0,
                planComunElectivoConCargoLibreDisposicion: false
            );
        }

        if (Str::contains($cursoNombre, ['3 MEDIO TP', '4 MEDIO TP']) || Str::contains($modalidad, 'TECNICO')) {
            return $this->bloquesMediaDiferenciada(
                plan: $plan,
                semanas: $semanas,
                tipoDiferenciado: 'plan_diferenciado_tp',
                nombreDiferenciado: 'Plan diferenciado Técnico-Profesional',
                horasDiferenciado: 22.0,
                planComunElectivoConCargoLibreDisposicion: true
            );
        }

        if (Str::contains($cursoNombre, ['3 MEDIO ARTISTICO', '4 MEDIO ARTISTICO']) || Str::contains($modalidad, 'ARTISTICA')) {
            return $this->bloquesMediaDiferenciada(
                plan: $plan,
                semanas: $semanas,
                tipoDiferenciado: 'plan_diferenciado_artistico',
                nombreDiferenciado: 'Plan diferenciado Artístico',
                horasDiferenciado: 21.0,
                planComunElectivoConCargoLibreDisposicion: false
            );
        }

        return $this->bloquesPlanGeneral($plan, $semanas);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bloquesParvulariaTransicion(PlanEstudio $plan, float $semanas): array
    {
        $regimen = $this->normalizar((string) ($plan->regimen_jec ?? ''));
        $conJec = Str::contains($regimen, ['CON JEC', 'JEC']) && ! Str::contains($regimen, 'SIN JEC');

        $total = $this->numero($plan->horas_semanales_total);
        if ($total <= 0) {
            $total = $conJec ? 36.0 : 30.0;
        }

        $libre = $this->numero($plan->horas_semanales_libre_disposicion);
        if ($conJec && $libre <= 0) {
            $libre = 4.0;
        }
        if (! $conJec) {
            $libre = 0.0;
        }

        return [
            $this->bloque($plan, 1, 'Ámbito Desarrollo Personal y Social', 'ambito_parvularia', 0.0, $semanas, false, false, true, 0.0),
            $this->bloque($plan, 2, 'Ámbito Comunicación Integral', 'ambito_parvularia', 0.0, $semanas, false, false, true, 0.0),
            $this->bloque($plan, 3, 'Ámbito Interacción y Comprensión del Entorno', 'ambito_parvularia', 0.0, $semanas, false, false, true, 0.0),
            $this->bloque($plan, 4, 'Horas de libre disposición', 'libre_disposicion', $libre, $semanas, $libre > 0, $libre > 0, true),
            $this->bloque($plan, 5, 'Total tiempo mínimo', 'total', $total, $semanas, false, false, true, $this->numero($plan->horas_anuales_total) ?: ($total * $semanas)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bloquesPlanGeneral(PlanEstudio $plan, float $semanas): array
    {
        $subtotal = $this->numero($plan->horas_semanales_subtotal);
        $libre = $this->numero($plan->horas_semanales_libre_disposicion);
        $total = $this->numero($plan->horas_semanales_total);

        return array_values(array_filter([
            $this->bloque($plan, 1, 'Tiempo mínimo obligatorio', 'plan_comun_formacion_general', $subtotal, $semanas, false, false, $subtotal > 0),
            $this->bloque($plan, 2, 'Horas de libre disposición', 'libre_disposicion', $libre, $semanas, $libre > 0, $libre > 0, true),
            $this->bloque($plan, 3, 'Total tiempo mínimo', 'total', $total, $semanas, false, false, $total > 0, $this->numero($plan->horas_anuales_total)),
        ]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bloquesMediaDiferenciada(
        PlanEstudio $plan,
        float $semanas,
        string $tipoDiferenciado,
        string $nombreDiferenciado,
        float $horasDiferenciado,
        bool $planComunElectivoConCargoLibreDisposicion
    ): array {
        $librePlan = $this->numero($plan->horas_semanales_libre_disposicion);
        $horasElectivo = 2.0;
        $horasLibreConfigurable = $planComunElectivoConCargoLibreDisposicion
            ? max(0.0, $librePlan - $horasElectivo)
            : $librePlan;

        $nombreLibre = $planComunElectivoConCargoLibreDisposicion
            ? 'Horas de libre disposición restantes'
            : 'Horas de libre disposición';

        return [
            $this->bloque($plan, 1, 'Plan común formación general', 'plan_comun_formacion_general', 14.0, $semanas, false, false, true),
            $this->bloque($plan, 2, 'Plan común formación general electivo', 'plan_comun_formacion_general_electivo', $horasElectivo, $semanas, true, false, true),
            $this->bloque($plan, 3, $nombreDiferenciado, $tipoDiferenciado, $horasDiferenciado, $semanas, true, false, true),
            $this->bloque($plan, 4, $nombreLibre, 'libre_disposicion', $horasLibreConfigurable, $semanas, $horasLibreConfigurable > 0, $horasLibreConfigurable > 0, true),
            $this->bloque($plan, 5, 'Total tiempo mínimo', 'total', $this->numero($plan->horas_semanales_total), $semanas, false, false, true, $this->numero($plan->horas_anuales_total)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bloque(
        PlanEstudio $plan,
        int $orden,
        string $nombre,
        string $tipo,
        float $horasSemanales,
        float $semanas,
        bool $seleccionEstablecimiento,
        bool $personalizadas,
        bool $activo,
        ?float $horasAnualesForzadas = null
    ): array {
        return [
            'plan_estudio_id' => $plan->id,
            'nombre' => $nombre,
            'tipo_bloque' => $tipo,
            'horas_semanales' => round($horasSemanales, 2),
            'horas_anuales' => round($horasAnualesForzadas ?? ($horasSemanales * $semanas), 2),
            'permite_asignaturas_establecimiento' => $seleccionEstablecimiento,
            'permite_asignaturas_personalizadas' => $personalizadas,
            'orden' => $orden,
            'activo' => $activo,
        ];
    }

    private function numero(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', (string) $valor);
    }

    private function normalizar(string $texto): string
    {
        $texto = Str::ascii($texto);
        $texto = strtoupper($texto);
        return preg_replace('/\s+/', ' ', trim($texto)) ?: '';
    }
}

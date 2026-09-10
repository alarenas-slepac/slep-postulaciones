<?php

namespace App\Services\Padron;

use App\Models\PadronRevision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Decisiones de revisión. Solo el escritor transaccional libera asignaciones. */
class PadronBajaAsignacionesService
{
    public function instalado(): bool
    {
        return Schema::hasTable('padron_bajas_asignaciones') && Schema::hasTable('padron_asignacion_cambios');
    }

    public function ultimas(PadronRevision $revision): Collection
    {
        return $this->instalado()
            ? DB::table('padron_bajas_asignaciones')->where('padron_revision_id', $revision->id)->orderBy('id')->get()->keyBy('rut')
            : collect();
    }

    /** Reutiliza las filas y el snapshot acotado del diagnóstico, sin otra lectura de asignaciones. */
    public function evaluar(PadronRevision $revision, Collection $filas, array $estados, array $asignaciones): array
    {
        if (! $this->instalado() || ! $revision->anio || $revision->errores || $filas->whereNotNull('fila_excel')->isEmpty()) {
            return [];
        }
        $entrantes = [];
        $ausentes = [];
        foreach ($filas as $fila) {
            $rut = PadronConciliador::rut($fila->rut);
            if ($fila->fila_excel !== null || ($estados[$fila->id] ?? '') === 'conservada') {
                // Incluso un reemplazo omitido o una línea inválida impide presumir retiro.
                $entrantes[$rut] = true;
            } else {
                $ausentes[$rut][] = $fila;
            }
        }
        $ausentes = array_diff_key($ausentes, $entrantes);
        unset($ausentes['']);
        if (! $ausentes) {
            return [];
        }
        $ids = [];
        foreach ($ausentes as $rows) {
            foreach ($rows as $fila) { $ids[] = $fila->personal_id; }
        }
        $ids = array_unique(array_filter([...$ids, ...array_column($asignaciones, 'reemplazos_personal_id')]));
        $columnas = ['id', 'rut', 'establecimiento_id', 'anio'];
        if (Schema::hasColumn('reemplazos_personal', 'vigente')) { $columnas[] = 'vigente'; }
        $personal = DB::table('reemplazos_personal')->whereIn('id', $ids)->get($columnas)->keyBy('id');
        $porRut = [];
        $incompatibles = [];
        foreach ($asignaciones as $a) {
            $id = $a['reemplazos_personal_id'] ?? null;
            $normalizado = PadronConciliador::rut($a['docente_rut_normalizado'] ?? '');
            $literal = PadronConciliador::rut($a['docente_rut'] ?? '');
            $contractual = PadronConciliador::rut($personal[$id]->rut ?? '');
            $rut = $normalizado ?: ($literal ?: $contractual);
            $identidades = array_unique(array_filter([$normalizado, $literal, $contractual]));
            if (count($identidades) > 1 || ($id && ! isset($personal[$id]))) {
                foreach ($identidades as $identidad) { $incompatibles[$identidad] = true; }
            }
            if (isset($ausentes[$rut])) { $porRut[$rut][] = $a; }
        }
        $ultimas = $this->ultimas($revision);
        $out = [];
        foreach ($porRut as $rut => $rows) {
            $bajas = [];
            $establecimientos = [];
            $elegible = ! isset($incompatibles[$rut]);
            foreach ($ausentes[$rut] as $fila) {
                $p = $personal[$fila->personal_id] ?? null;
                $elegible = $elegible && $p && PadronConciliador::rut($p->rut) === (string) $rut
                    && (int) $p->anio === (int) $revision->anio
                    && in_array($fila->accion, ['baja_propuesta', 'ausencia_por_revisar'], true)
                    && in_array($estados[$fila->id] ?? '', ['resuelta', 'propuesta_automatica'], true);
                $bajas[] = (int) $fila->personal_id;
                if ($p) { $establecimientos[(int) $p->establecimiento_id] = true; }
            }
            $huellas = [];
            $horas = 0;
            foreach ($rows as $a) {
                $id = $a['reemplazos_personal_id'] ?? null;
                $elegible = $elegible && (int) $a['anio'] === (int) $revision->anio
                    && isset($establecimientos[(int) $a['establecimiento_id']])
                    && (! $id || (in_array((int) $id, $bajas, true)
                        && (int) $personal[$id]->establecimiento_id === (int) $a['establecimiento_id']))
                    && is_numeric($a['horas_contrato']) && (float) $a['horas_contrato'] >= 0;
                $huellas[(int) $a['id']] = $a['_huella'];
                $horas += (float) $a['horas_contrato'];
            }
            sort($bajas); ksort($huellas);
            $alcance = ['bajas' => array_values(array_unique($bajas)), 'asignaciones' => $huellas, 'anio' => (int) $revision->anio];
            $hash = hash('sha256', json_encode([$revision->id, $revision->base_hash, (string) $rut, $alcance], JSON_THROW_ON_ERROR));
            $ultima = $ultimas->get($rut);
            $confirmada = $elegible && $ultima && $ultima->confirmada && hash_equals($hash, $ultima->alcance_hash);
            $out[$rut] = ['rut' => (string) $rut, 'elegible' => (bool) $elegible, 'confirmada' => (bool) $confirmada,
                'autorizada' => (bool) ($ultima && $ultima->confirmada),
                'alcance' => $alcance, 'alcance_hash' => $hash, 'ultima_id' => (int) ($ultima->id ?? 0),
                'horas' => round($horas, 2), 'cantidad' => count($rows), 'establecimientos' => count($establecimientos),
                'justificacion' => $ultima->justificacion ?? null, 'usuario_id' => $ultima->usuario_id ?? null,
                'desactualizada' => (bool) ($ultima && $ultima->confirmada && ! $confirmada)];
        }
        return $out;
    }

    public function registrar(PadronRevision $revision, string $rut, string $hash, int $anterior, string $motivo, int $usuario, bool $confirmar): void
    {
        $rut = PadronConciliador::rut($rut);
        $motivo = trim($motivo);
        Validator::make(compact('rut', 'hash', 'anterior', 'motivo', 'usuario'), [
            'rut' => ['required', 'string', 'max:32'], 'hash' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'anterior' => ['integer', 'min:0'], 'motivo' => ['required', 'string', 'min:10', 'max:2000'],
            'usuario' => ['integer', 'min:1'],
        ])->validate();
        if (! $this->instalado()) { $this->fail('Instale la migración de bajas y liberación de asignaciones con PHP 8.3.'); }
        DB::transaction(function () use ($revision, $rut, $hash, $anterior, $motivo, $usuario, $confirmar): void {
            $revision = PadronRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            if ($revision->aplicada_at || $revision->errores || app(PadronRevisionService::class)->stale($revision)) {
                $this->fail('La revisión está cerrada, contiene errores o cambió el padrón base.');
            }
            $ultima = $this->ultimas($revision)->get($rut);
            if ((int) ($ultima->id ?? 0) !== $anterior) {
                // Solo reintentos idénticos del último formulario son idempotentes.
                if ($ultima && (bool) $ultima->confirmada === $confirmar && $ultima->alcance_hash === $hash
                    && $ultima->justificacion === $motivo && (int) $ultima->usuario_id === $usuario) { return; }
                $this->fail('Otra decisión de baja fue registrada. Recargue antes de confirmar o retirar la autorización.');
            }
            $candidato = app(PadronConflictosAsignacionService::class)->analizar($revision)['bajas_asignaciones'][$rut] ?? null;
            if ($confirmar && (! $candidato || ! $candidato['elegible'] || ! hash_equals($candidato['alcance_hash'], $hash))) {
                $this->fail('El RUT no es una baja completa elegible o sus asignaciones cambiaron. Recargue y revise el alcance.');
            }
            if (! $confirmar && (! $ultima || ! $ultima->confirmada)) { $this->fail('No hay una liberación confirmada que retirar.'); }
            DB::table('padron_bajas_asignaciones')->insert([
                'padron_revision_id' => $revision->id, 'rut' => $rut, 'confirmada' => $confirmar,
                'alcance_hash' => $confirmar ? $hash : $ultima->alcance_hash,
                'alcance' => $confirmar ? json_encode($candidato['alcance'], JSON_THROW_ON_ERROR) : $ultima->alcance,
                'justificacion' => $motivo, 'usuario_id' => $usuario, 'created_at' => now(),
            ]);
        });
    }

    /** Solo después de revalidar el plan y bajo los bloqueos del escritor. */
    public function aplicar(PadronRevision $revision, array $bajas, array $confirmaciones, int $usuario): void
    {
        if (! $confirmaciones) { return; }
        if (DB::transactionLevel() === 0 || ! $this->instalado()) { $this->fail('La liberación requiere la transacción de aplicación.'); }
        $columnas = array_flip(Schema::getColumnListing('dotacion_docente_asignaciones'));
        foreach ($confirmaciones as $confirmacion) {
            if (! $confirmacion['confirmada']) { continue; }
            if (array_diff($confirmacion['alcance']['bajas'], $bajas)) { $this->fail('La liberación no corresponde a las bajas del plan.'); }
            foreach ($confirmacion['alcance']['asignaciones'] as $id => $huella) {
                $antes = DB::table('dotacion_docente_asignaciones')->where('id', $id)->lockForUpdate()->first();
                if (! $antes || $antes->estado !== 'activa' || (int) $antes->anio !== (int) $revision->anio
                    || ! hash_equals($huella, hash('sha256', json_encode($antes, JSON_THROW_ON_ERROR)))) {
                    $this->fail('Una asignación cambió desde la confirmación de baja. No se aplicaron cambios.');
                }
                DB::table('dotacion_docente_asignaciones')->where('id', $id)->update(array_intersect_key([
                    'estado' => 'inactiva', 'updated_by' => $usuario, 'updated_at' => now()->toDateTimeString(),
                ], $columnas));
                DB::table('padron_asignacion_cambios')->insert([
                    'padron_revision_id' => $revision->id, 'baja_asignaciones_id' => $confirmacion['ultima_id'],
                    'asignacion_id' => $id, 'antes' => json_encode($antes, JSON_THROW_ON_ERROR),
                    'despues' => json_encode(DB::table('dotacion_docente_asignaciones')->find($id), JSON_THROW_ON_ERROR),
                    'usuario_id' => $usuario, 'created_at' => now(),
                ]);
            }
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['revision' => $message]);
    }
}

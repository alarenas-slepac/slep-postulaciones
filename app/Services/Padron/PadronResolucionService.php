<?php

namespace App\Services\Padron;

use App\Models\PadronRevision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PadronResolucionService
{
    public function __construct(private PadronRevisionService $revisiones) {}

    public function disponible(): bool
    {
        return Schema::hasTable('padron_revision_decisiones')
            && Schema::hasColumn('padron_revisiones', 'aplicada_at');
    }

    public function historial(PadronRevision $revision): Collection
    {
        return DB::table('padron_revision_decisiones')->where('padron_revision_id', $revision->id)->orderBy('id')->get();
    }

    public function decisiones(PadronRevision $revision): Collection
    {
        return $this->historial($revision)->keyBy('padron_revision_fila_id');
    }

    /** Selecciones efectivas; las propuestas originales no se sobrescriben. */
    public function selecciones(Collection $filas, Collection $decisiones): array
    {
        $out = [];
        foreach ($filas->whereNotNull('fila_excel') as $fila) {
            if (in_array($fila->accion, ['error', PadronReemplazosVigentes::OMITIDO], true)) {
                continue;
            }
            if ($fila->accion === 'revision_manual') {
                if ($decisiones->has($fila->id)) {
                    $out[$fila->id] = $decisiones[$fila->id]->personal_id;
                }
            } else {
                $out[$fila->id] = $fila->personal_id;
            }
        }
        return $out;
    }

    public function resolver(PadronRevision $revision, int $filaId, ?int $personalId, string $motivo, int $usuario, int $decisionAnterior = 0): void
    {
        if (! $this->disponible()) {
            $this->fail('Ejecute las migraciones de revisión del padrón con PHP 8.3 antes de resolver coincidencias.');
        }
        DB::transaction(function () use ($revision, $filaId, $personalId, $motivo, $usuario, $decisionAnterior): void {
            $revision = PadronRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            if ($revision->aplicada_at || $revision->errores || $this->revisiones->stale($revision)) {
                $this->fail('La revisión está cerrada, contiene errores o cambió la base. Genere un nuevo análisis.');
            }
            $filas = $revision->filas()->orderBy('id')->get(['id', 'fila_excel', 'accion', 'personal_id']);
            $fila = $revision->filas()->find($filaId);
            if (! $fila || ! in_array($fila->accion, ['revision_manual', 'ausencia_por_revisar'], true)) {
                $this->fail('Solo puede resolver filas ambiguas pertenecientes a esta revisión.');
            }
            $motivo = trim($motivo);
            if (mb_strlen($motivo) < 10 || mb_strlen($motivo) > 2000) {
                $this->fail('Ingrese una justificación de entre 10 y 2.000 caracteres.');
            }
            if ($fila->fila_excel && PadronConciliador::tipo($fila->datos) === 'por_clasificar') {
                $this->fail('Corrija el tipo de contrato desconocido en el Excel y vuelva a analizar.');
            }
            $candidatos = collect($fila->candidatos)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($personalId !== null && (! $fila->fila_excel || ! in_array($personalId, $candidatos, true))) {
                $this->fail('El ID seleccionado no es un candidato válido para esta fila.');
            }
            $decisiones = $this->decisiones($revision);
            $actual = $decisiones->get($filaId);
            // Reenvío del mismo formulario: no duplica decisiones ni auditoría.
            if ($actual && ($actual->personal_id === null ? null : (int) $actual->personal_id) === $personalId
                && $actual->justificacion === $motivo && (int) $actual->resuelta_por === $usuario) {
                return;
            }
            if ((int) ($actual->id ?? 0) !== $decisionAnterior) {
                $this->fail('Otra decisión fue registrada después de abrir la pantalla. Recargue antes de corregirla.');
            }
            $selecciones = $this->selecciones($filas, $decisiones);
            unset($selecciones[$filaId]);
            if ($personalId !== null && in_array($personalId, array_map('intval', array_filter($selecciones, fn ($id) => $id !== null)), true)) {
                $this->fail('El ID ya está seleccionado en otra fila. No puede actualizar dos contratos sobre el mismo registro.');
            }
            if (! $fila->fila_excel && in_array((int) $fila->personal_id, array_map('intval', array_filter($selecciones, fn ($id) => $id !== null)), true)) {
                $this->fail('Esta ausencia ya está cubierta por una fila del archivo. No corresponde confirmar su baja.');
            }
            DB::table('padron_revision_decisiones')->insert([
                'padron_revision_id' => $revision->id, 'padron_revision_fila_id' => $filaId,
                'personal_id' => $personalId, 'justificacion' => $motivo, 'resuelta_por' => $usuario,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function resumen(Collection $filas, Collection $decisiones): array
    {
        $selecciones = $this->selecciones($filas, $decisiones);
        $usados = array_fill_keys(array_filter($selecciones, fn ($id) => $id !== null), true);
        $estados = [];
        foreach ($filas as $fila) {
            $estados[$fila->id] = match (true) {
                $fila->accion === PadronReemplazosVigentes::OMITIDO => 'omitida_por_vigencia',
                $fila->accion === 'error' => 'error',
                ! $fila->fila_excel && isset($usados[$fila->personal_id]) => 'ausencia_vinculada',
                $fila->accion === 'revision_manual' && ! $decisiones->has($fila->id) => 'pendiente',
                $fila->accion === 'ausencia_por_revisar' && ! $decisiones->has($fila->id) => 'pendiente',
                $decisiones->has($fila->id) => 'resuelta',
                default => 'propuesta_automatica',
            };
        }
        return ['selecciones' => $selecciones, 'estados' => $estados, 'totales' => array_count_values($estados)];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['revision' => $message]);
    }
}

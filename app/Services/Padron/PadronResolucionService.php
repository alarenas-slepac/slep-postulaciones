<?php

namespace App\Services\Padron;

use App\Models\PadronRevision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
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
        $this->registrarDecisiones($revision, [[
            'fila' => $filaId, 'personal_id' => $personalId,
            'justificacion' => $motivo, 'decision_anterior' => $decisionAnterior,
        ]], $usuario);
    }

    public function resolverVarias(PadronRevision $revision, string $rut, array $decisiones, int $usuario): int
    {
        $rut = PadronConciliador::rut($rut);
        if ($rut === '') {
            $this->fail('Indique el RUT de las correspondencias que desea resolver juntas.');
        }

        return $this->registrarDecisiones($revision, $decisiones, $usuario, $rut);
    }

    private function registrarDecisiones(PadronRevision $revision, array $entradas, int $usuario, ?string $rut = null): int
    {
        Validator::make(['decisiones' => $entradas], [
            'decisiones' => ['required', 'array', 'min:1', 'max:50'],
            'decisiones.*' => ['required', 'array'],
            'decisiones.*.fila' => ['required', 'integer', 'min:1', 'distinct'],
            // null solo representa una elección explícita de nueva línea/baja.
            'decisiones.*.personal_id' => ['present', 'nullable', 'integer', 'min:1'],
            'decisiones.*.justificacion' => ['required', 'string', 'min:10', 'max:2000'],
            'decisiones.*.decision_anterior' => ['required', 'integer', 'min:0'],
        ])->validate();
        if (! $this->disponible()) {
            $this->fail('Ejecute las migraciones de revisión del padrón con PHP 8.3 antes de resolver coincidencias.');
        }

        return DB::transaction(function () use ($revision, $entradas, $usuario, $rut): int {
            $revision = PadronRevision::whereKey($revision->id)->lockForUpdate()->firstOrFail();
            if ($revision->aplicada_at || $revision->errores || $this->revisiones->stale($revision)) {
                $this->fail('La revisión está cerrada, contiene errores o cambió la base. Genere un nuevo análisis.');
            }
            $filas = $revision->filas()->orderBy('id')->get(['id', 'fila_excel', 'accion', 'personal_id']);
            $objetivos = $revision->filas()->whereIn('id', array_column($entradas, 'fila'))->get()->keyBy('id');
            $decisiones = $this->decisiones($revision);
            $selecciones = $this->selecciones($filas, $decisiones);
            // Validar la selección final completa, independiente del orden del lote.
            foreach ($entradas as $entrada) {
                $fila = $objetivos->get((int) $entrada['fila']);
                if ($fila && $fila->fila_excel) {
                    $selecciones[$fila->id] = $entrada['personal_id'] === null ? null : (int) $entrada['personal_id'];
                }
            }
            $nuevas = [];
            foreach ($entradas as $entrada) {
                $filaId = (int) $entrada['fila'];
                $personalId = $entrada['personal_id'] === null ? null : (int) $entrada['personal_id'];
                $motivo = trim($entrada['justificacion']);
                $decisionAnterior = (int) $entrada['decision_anterior'];
                $fila = $objetivos->get($filaId);
                if (! $fila || ! in_array($fila->accion, ['revision_manual', 'ausencia_por_revisar'], true)) {
                    $this->fail('Solo puede resolver filas ambiguas pertenecientes a esta revisión.');
                }
                if ($rut !== null && PadronConciliador::rut($fila->rut) !== $rut) {
                    $this->fail('Todas las correspondencias del envío deben pertenecer al mismo RUT.');
                }
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
                $actual = $decisiones->get($filaId);
                // Reenvío del mismo formulario: no duplica decisiones ni auditoría.
                $identica = $actual && ($actual->personal_id === null ? null : (int) $actual->personal_id) === $personalId
                    && $actual->justificacion === $motivo && (int) $actual->resuelta_por === $usuario;
                if (! $identica && (int) ($actual->id ?? 0) !== $decisionAnterior) {
                    $this->fail('Otra decisión fue registrada después de abrir la pantalla. Recargue antes de corregirla.');
                }
                $otras = $selecciones;
                unset($otras[$filaId]);
                if ($personalId !== null && in_array($personalId, array_map('intval', array_filter($otras, fn ($id) => $id !== null)), true)) {
                    $this->fail('El ID ya está seleccionado en otra fila. No puede actualizar dos contratos sobre el mismo registro.');
                }
                if (! $fila->fila_excel && in_array((int) $fila->personal_id, array_map('intval', array_filter($selecciones, fn ($id) => $id !== null)), true)) {
                    $this->fail('Esta ausencia ya está cubierta por una fila del archivo. No corresponde confirmar su baja.');
                }
                if ($identica) {
                    continue;
                }
                $nuevas[] = [
                    'padron_revision_id' => $revision->id, 'padron_revision_fila_id' => $filaId,
                    'personal_id' => $personalId, 'justificacion' => $motivo, 'resuelta_por' => $usuario,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            if ($nuevas) {
                DB::table('padron_revision_decisiones')->insert($nuevas);
            }

            return count($nuevas);
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

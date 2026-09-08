<?php

namespace App\Models\Concerns;

use App\Services\Padron\PadronHistorialService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ConservaPadronHistorico
{
    abstract protected function relacionPadronHistorico(): string;

    public function initializeConservaPadronHistorico(): void
    {
        $this->mergeCasts(['padron_personal_snapshot' => 'array']);
        $this->makeHidden('padron_personal_snapshot');
    }

    public static function bootConservaPadronHistorico(): void
    {
        static::saving(function ($documento): void {
            if (! Schema::hasColumn($documento->getTable(), 'padron_personal_snapshot')) {
                return; // Compatibilidad durante despliegue: no habilita importación.
            }
            $snapshot = $documento->copiaPadronHistorica();
            if (! $documento->reemplazo_personal_id) {
                if ($documento->isDirty('reemplazo_personal_id') && $snapshot !== null) {
                    $anteriores = $snapshot['anteriores'] ?? [];
                    unset($snapshot['anteriores']);
                    $documento->padron_personal_snapshot = [
                        'version' => 1, 'capturado_at' => now()->toIso8601String(),
                        'origen' => 'desvinculacion_documento', 'personal' => null,
                        'anteriores' => [...$anteriores, $snapshot],
                    ];
                }
                return;
            }
            if ($snapshot === null || $documento->isDirty('reemplazo_personal_id')) {
                $nueva = app(PadronHistorialService::class)->capturar(
                    (int) $documento->reemplazo_personal_id, $documento->exists ? 'edicion_documento' : 'creacion_documento',
                );
                if ($snapshot !== null) {
                    $anteriores = $snapshot['anteriores'] ?? [];
                    unset($snapshot['anteriores']);
                    $nueva['anteriores'] = [...$anteriores, $snapshot];
                }
                $documento->padron_personal_snapshot = $nueva;
            } else {
                app(PadronHistorialService::class)->leer($snapshot, (int) $documento->reemplazo_personal_id);
            }
        });
        static::saved(fn ($documento) => $documento->unsetRelation($documento->relacionPadronHistorico()));
    }

    private function copiaPadronHistorica(): ?array
    {
        if (array_key_exists('padron_personal_snapshot', $this->getAttributes())) {
            return $this->getAttribute('padron_personal_snapshot');
        }
        // Algunas nóminas seleccionan columnas específicas sin el snapshot.
        if ($this->exists && $this->getKey() && Schema::hasColumn($this->getTable(), 'padron_personal_snapshot')) {
            $json = DB::table($this->getTable())->where('id', $this->getKey())->value('padron_personal_snapshot');
            return $json === null ? null : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

    public function getRelationValue($key)
    {
        if ($key === $this->relacionPadronHistorico() && $this->reemplazo_personal_id && ($snapshot = $this->copiaPadronHistorica()) !== null) {
            $loaded = $this->relations[$key] ?? null;
            if ($loaded instanceof \App\Models\ReemplazoPersonalHistorico
                && (int) $loaded->id === (int) $this->reemplazo_personal_id && ! $this->isDirty('padron_personal_snapshot')) {
                return $loaded;
            }
            return $this->relations[$key] = app(PadronHistorialService::class)->leer($snapshot, (int) $this->reemplazo_personal_id);
        }
        return parent::getRelationValue($key);
    }

    public function setRelation($relation, $value)
    {
        if ($relation === $this->relacionPadronHistorico() && $this->reemplazo_personal_id && ($snapshot = $this->copiaPadronHistorica()) !== null) {
            $historical = app(PadronHistorialService::class)->leer($snapshot, (int) $this->reemplazo_personal_id);
            if ($value instanceof \App\Models\ReemplazoPersonal) {
                // Respeta las columnas expuestas por cargas parciales al serializar.
                $historical->setVisible(array_keys($value->getAttributes()));
            }
            $value = $historical;
        }
        return parent::setRelation($relation, $value);
    }
}

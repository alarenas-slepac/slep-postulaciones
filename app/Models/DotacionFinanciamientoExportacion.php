<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DotacionFinanciamientoExportacion extends Model
{
    use HasUuids, Prunable;

    protected $table = 'dotacion_financiamiento_exports';

    protected $fillable = ['user_id', 'anio', 'filtros', 'estado', 'expires_at'];

    protected $casts = ['anio' => 'integer', 'filtros' => 'array', 'expires_at' => 'datetime'];

    public static function disponible(): bool
    {
        return Schema::hasTable((new self)->getTable());
    }

    public function ruta(): string
    {
        return 'exports/dotacion-financiamiento/'.$this->id.'.xlsx';
    }

    public function nombreDescarga(): string
    {
        return 'horas_financiamiento_por_rbd_'.$this->anio.'_'.$this->created_at->format('Ymd_His').'.xlsx';
    }

    public function estadoActual(): string
    {
        if (! $this->expires_at->isFuture()) {
            return 'vencido';
        }

        if ($this->estado === 'listo' && ! Storage::disk('local')->exists($this->ruta())) {
            return 'error';
        }

        return $this->estado;
    }

    public function prunable(): Builder
    {
        return self::query()->where('expires_at', '<=', now());
    }

    protected function pruning(): void
    {
        $disk = Storage::disk('local');
        foreach ([$this->ruta(), $this->ruta().'.tmp'] as $path) {
            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new \RuntimeException('No se pudo limpiar el archivo de financiamiento.');
            }
        }
    }
}

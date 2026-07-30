<?php

namespace App\Jobs;

use App\Models\MaeCarga;
use App\Services\MaeImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMaeCargaImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $cargaId)
    {
    }

    public function handle(MaeImportService $importService): void
    {
        $importService->processMaeCarga($this->cargaId);
    }

    public function failed(Throwable $exception): void
    {
        $carga = MaeCarga::find($this->cargaId);
        if (!$carga) {
            return;
        }

        $carga->update([
            'estado' => 'fallido',
            'observaciones' => mb_substr($exception->getMessage(), 0, 2000),
            'updated_at' => now(),
        ]);

        Log::error('Fallo la importacion asincrona del MAE de endeudamiento.', [
            'mae_carga_id' => $this->cargaId,
            'message' => $exception->getMessage(),
        ]);
    }
}

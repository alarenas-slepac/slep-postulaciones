<?php

namespace App\Jobs;

use App\Models\LiquidacionCarga;
use App\Services\Liquidaciones\LiquidacionPdfImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLiquidacionCargaPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(public int $liquidacionCargaId)
    {
    }

    public function handle(LiquidacionPdfImportService $service): void
    {
        $carga = LiquidacionCarga::findOrFail($this->liquidacionCargaId);
        $service->process($carga);
    }

    public function failed(\Throwable $exception): void
    {
        $carga = LiquidacionCarga::find($this->liquidacionCargaId);
        if ($carga) {
            $carga->update([
                'estado' => 'fallido',
                'errores' => [$exception->getMessage()],
                'total_errores' => 1,
                'procesada_at' => now(),
            ]);
        }

        Log::error('Fallo procesamiento de liquidaciones PDF', [
            'carga_id' => $this->liquidacionCargaId,
            'message' => $exception->getMessage(),
        ]);
    }
}

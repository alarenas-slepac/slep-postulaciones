<?php

namespace App\Jobs;

use App\Models\CertificadoImportacion;
use App\Services\Certificados\ContratoHistoricoImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcesarCertificadoImportacion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $importacionId) {}

    public function handle(ContratoHistoricoImportService $service): void
    {
        $service->procesar($this->importacionId);
    }

    public function failed(Throwable $exception): void
    {
        $importacion = CertificadoImportacion::query()->find($this->importacionId);
        if ($importacion) {
            $importacion->update([
                'estado' => 'fallido',
                'errores' => [[
                    'fila' => null,
                    'mensaje' => mb_substr($exception->getMessage(), 0, 500),
                ]],
            ]);
        }

        Log::error('Falló la importación histórica para certificados.', [
            'certificado_importacion_id' => $this->importacionId,
            'message' => $exception->getMessage(),
        ]);
    }
}

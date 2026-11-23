<?php

namespace App\Jobs;

use App\Exports\DotacionInformeFinanciamientoExport;
use App\Models\DotacionFinanciamientoExportacion;
use App\Support\DotacionInformeFunciones;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerarExcelFinanciamiento implements ShouldQueue
{
    use Queueable;

    public const CONEXION = 'sga_dotacion';
    public const COLA = 'dotacion-financiamiento';
    public const TIMEOUT = 600;

    public int $tries = 1;
    public int $timeout = self::TIMEOUT;
    public bool $failOnTimeout = true;

    public function __construct(public readonly string $exportacionId)
    {
        $this->onConnection(self::CONEXION)->onQueue(self::COLA);
    }

    public function handle(DotacionInformeFinanciamientoExport $exportador): void
    {
        $solicitud = DotacionFinanciamientoExportacion::find($this->exportacionId);
        if (! $solicitud || ! $this->pendienteVigente($solicitud)->update([
            'estado' => 'procesando', 'expires_at' => now()->addMinutes(15),
        ])) {
            return;
        }

        $disk = Storage::disk('local');
        $ruta = $solicitud->ruta();
        $temporal = $ruta.'.tmp';

        try {
            if (! $disk->makeDirectory(dirname($ruta))) {
                throw new \RuntimeException('No se pudo preparar el directorio de financiamiento.');
            }
            $filtros = $solicitud->filtros;
            $establecimientos = DotacionInformeFunciones::establecimientosFiltrados($filtros['q'], $filtros['comuna']);
            $exportador->guardar($establecimientos, $solicitud->anio, $disk->path($temporal));

            if (! $disk->move($temporal, $ruta)) {
                throw new \RuntimeException('No se pudo publicar el Excel de financiamiento.');
            }
            $publicado = DotacionFinanciamientoExportacion::whereKey($solicitud->id)->where('estado', 'procesando')
                ->where('expires_at', '>', now())->update(['estado' => 'listo', 'expires_at' => now()->addDay()]);
            if (! $publicado) {
                $disk->delete($ruta);
            }
        } catch (Throwable $exception) {
            $this->failed($exception);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $solicitud = DotacionFinanciamientoExportacion::find($this->exportacionId);
        if (! $solicitud || ! DotacionFinanciamientoExportacion::whereKey($solicitud->id)
            ->whereIn('estado', ['pendiente', 'procesando'])
            ->update(['estado' => 'error', 'expires_at' => now()->addDay()])) {
            return;
        }

        Storage::disk('local')->delete([$solicitud->ruta(), $solicitud->ruta().'.tmp']);
    }

    private function pendienteVigente(DotacionFinanciamientoExportacion $solicitud): \Illuminate\Database\Eloquent\Builder
    {
        return DotacionFinanciamientoExportacion::whereKey($solicitud->id)
            ->where('estado', 'pendiente')->where('expires_at', '>', now());
    }
}

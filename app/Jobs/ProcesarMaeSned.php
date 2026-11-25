<?php

namespace App\Jobs;

use App\Models\Establecimiento;
use App\Models\SnedCarga;
use App\Services\Remuneraciones\SnedMaeReader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcesarMaeSned implements ShouldQueue
{
    use Queueable;

    public const COLA = 'sned';

    public const CONEXION = 'sga_sned';

    public const TIMEOUT = 480;

    public int $tries = 1;

    public int $timeout = self::TIMEOUT;

    public bool $failOnTimeout = true;

    public function __construct(public int $cargaId)
    {
        $this->onConnection(self::CONEXION)->onQueue(self::COLA);
    }

    public function handle(SnedMaeReader $reader): void
    {
        $carga = SnedCarga::query()->with('cuota')->find($this->cargaId);
        if (! $carga || ! SnedCarga::query()->whereKey($this->cargaId)->where('estado', 'pendiente')->update(['estado' => 'procesando'])) {
            return;
        }

        try {
            $establecimientos = Establecimiento::query()->where('rbd', '>', 0)->get(['rbd', 'comuna', 'nombre_establecimiento'])
                ->filter(fn ($ee) => Str::slug($ee->comuna) === $carga->cuota->comuna_clave);
            $ruta = Storage::disk('local')->path($carga->ruta_archivo);
            $lectura = $reader->leer($ruta, $establecimientos);
            $carga->update(['estado' => 'lista', 'lectura' => $lectura, 'error' => null]);
        } catch (ValidationException $exception) {
            $carga->update(['estado' => 'fallida', 'error' => $exception->validator->errors()->first()]);
        } catch (Throwable) {
            $this->registrarFallo();
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->registrarFallo();
    }

    private function registrarFallo(): void
    {
        SnedCarga::query()->whereKey($this->cargaId)->whereIn('estado', ['pendiente', 'procesando'])->update([
            'estado' => 'fallida',
            'error' => 'El MAE no pudo procesarse. Verifique que sea un XLSX legible y vuelva a cargarlo.',
        ]);
    }
}

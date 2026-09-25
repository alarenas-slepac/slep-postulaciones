<?php

namespace App\Services\Remuneraciones;

use App\Models\DescuentoCgr;
use App\Models\DescuentoCgrArchivo;
use App\Models\DescuentoCgrNotificacion;
use App\Mail\DescuentoCgrEtapaMail;
use App\Support\NotificationAudit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DescuentoCgrWorkflowService
{
    public const TIPOS = ['liquidacion', 'sigfe', 'tgr', 'liquidacion_validada'];

    public function guardarArchivos(DescuentoCgr $descuento, string $tipo, array $archivos, int $usuarioId, array $metadatos = []): void
    {
        $estado = match ($tipo) {
            'liquidacion' => 'ingresado',
            'sigfe', 'tgr' => 'descuentos_realizados',
            'liquidacion_validada' => 'en_auditoria',
        };
        $guardados = [];
        $anteriores = [];
        $certificadoAnterior = null;

        try {
            DB::transaction(function () use ($descuento, $tipo, $archivos, $usuarioId, $metadatos, $estado, &$guardados, &$anteriores, &$certificadoAnterior): void {
                $registro = DescuentoCgr::query()->lockForUpdate()->findOrFail($descuento->id);
                $this->exigirEstado($registro, $estado);
                foreach ($archivos as $item) {
                    /** @var UploadedFile $archivo */
                    $archivo = $item['archivo'];
                    $cuotas = array_values(array_unique(array_map('intval', $item['cuotas'])));
                    if ($cuotas === [] || collect($cuotas)->contains(fn ($cuota) => $cuota < 1 || $cuota > $registro->numero_cuotas)) {
                        throw ValidationException::withMessages(['cuotas' => 'Selecciona cuotas válidas del cronograma.']);
                    }
                    $grupo = (string) Str::uuid();
                    $path = $archivo->storeAs("descuentos-cgr/{$registro->id}/{$tipo}", $grupo.'.pdf', 'local');
                    if (! $path) {
                        throw new \RuntimeException('No fue posible guardar el archivo.');
                    }
                    $guardados[] = $path;
                    foreach ($cuotas as $cuota) {
                        $anterior = DescuentoCgrArchivo::query()->where('descuento_cgr_id', $registro->id)->where('numero_cuota', $cuota)->where('tipo', $tipo)->value('path');
                        if ($anterior) {
                            $anteriores[] = $anterior;
                        }
                        DescuentoCgrArchivo::query()->updateOrCreate(
                            ['descuento_cgr_id' => $registro->id, 'numero_cuota' => $cuota, 'tipo' => $tipo],
                            ['grupo_archivo' => $grupo, 'path' => $path, 'nombre_original' => $archivo->getClientOriginalName(),
                                'tamano' => $archivo->getSize(), 'folio' => $metadatos['folio'] ?? null,
                                'fecha_reintegro' => $metadatos['fecha_reintegro'] ?? null,
                                'monto_reintegro_pesos' => $metadatos['monto_reintegro_pesos'] ?? null,
                                'cargado_por_id' => $usuarioId]
                        );
                    }
                }
                if ($tipo === 'liquidacion_validada') {
                    $certificadoAnterior = $registro->certificado_firmado_path;
                    $registro->update([
                        'certificado_generado_en' => null,
                        'certificado_generado_por_id' => null,
                        'certificado_firmado_path' => null,
                        'certificado_firmado_nombre' => null,
                        'certificado_firmado_en' => null,
                        'certificado_firmado_por_id' => null,
                    ]);
                }
            });
        } catch (Throwable $error) {
            Storage::disk('local')->delete($guardados);
            throw $error;
        }
        foreach (array_unique($anteriores) as $anterior) {
            if (! DescuentoCgrArchivo::query()->where('path', $anterior)->exists()) {
                Storage::disk('local')->delete($anterior);
            }
        }
        if ($certificadoAnterior) {
            Storage::disk('local')->delete($certificadoAnterior);
        }
    }

    public function completos(DescuentoCgr $descuento, array $tipos): bool
    {
        foreach ($tipos as $tipo) {
            $cantidad = $descuento->archivos()->where('tipo', $tipo)->distinct()->count('numero_cuota');
            if ($cantidad !== (int) $descuento->numero_cuotas) {
                return false;
            }
        }

        return (int) $descuento->numero_cuotas > 0;
    }

    public function avanzar(DescuentoCgr $descuento, string $desde, string $hacia, array $tipos, ?string $fechaColumna, string $rolDestino, string $evento): void
    {
        DB::transaction(function () use ($descuento, $desde, $hacia, $tipos, $fechaColumna): void {
            $registro = DescuentoCgr::query()->lockForUpdate()->findOrFail($descuento->id);
            $this->exigirEstado($registro, $desde);
            if (! $this->completos($registro, $tipos)) {
                throw ValidationException::withMessages(['documentos' => 'Debes completar los documentos de todas las cuotas antes de avanzar.']);
            }
            $registro->estado = $hacia;
            if ($fechaColumna) {
                $registro->{$fechaColumna} = now();
            }
            $registro->save();
        });

        foreach (DescuentoCgrNotificacion::destinatarios($evento, $rolDestino) as $email) {
            try {
                NotificationAudit::sendMail($email, new DescuentoCgrEtapaMail($descuento->fresh(), $evento), [
                    'event_key' => 'descuentos_cgr.'.$evento,
                    'subject' => $evento === 'finanzas' ? 'Descuento CGR para Finanzas' : 'Descuento CGR para Auditoría',
                    'related' => $descuento,
                ]);
            } catch (Throwable $error) {
                report($error);
            }
        }
    }

    public function exigirEstado(DescuentoCgr $descuento, string $estado): void
    {
        if ($descuento->estadoActual() !== $estado) {
            throw ValidationException::withMessages(['estado' => 'Esta acción ya no está disponible en la etapa actual.']);
        }
    }
}

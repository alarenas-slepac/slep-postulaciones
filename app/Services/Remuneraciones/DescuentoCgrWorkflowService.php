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
    public const TIPOS = ['liquidacion', 'sigfe', 'tgr', 'transferencia_institucion', 'liquidacion_validada'];

    public function guardarArchivos(DescuentoCgr $descuento, string $tipo, array $archivos, int $usuarioId, array $metadatos = []): void
    {
        $estado = match ($tipo) {
            'liquidacion' => 'ingresado',
            'sigfe', 'tgr', 'transferencia_institucion' => 'descuentos_realizados',
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
                        'certificado_residuo_utm_aceptado' => null,
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
            $cantidad = $descuento->archivos()->where('tipo', $tipo)
                ->whereBetween('numero_cuota', [1, $descuento->numero_cuotas])->distinct()->count('numero_cuota');
            if ($cantidad !== (int) $descuento->numero_cuotas) {
                return false;
            }
        }

        return (int) $descuento->numero_cuotas > 0;
    }

    public function corregirMetadatos(DescuentoCgr $descuento, string $tipo, array $cuotas, array $metadatos, int $usuarioId): void
    {
        DB::transaction(function () use ($descuento, $tipo, $cuotas, $metadatos, $usuarioId): void {
            $registro = DescuentoCgr::query()->lockForUpdate()->findOrFail($descuento->id);
            $this->exigirEstado($registro, 'descuentos_realizados');
            if (! $registro->observacionesAuditoria()->whereNull('reenviada_en')->exists()) {
                throw ValidationException::withMessages(['archivo' => 'Adjunta el PDF del comprobante.']);
            }

            $archivos = $registro->archivos()->where('tipo', $tipo);
            $seleccionados = (clone $archivos)->whereIn('numero_cuota', $cuotas)->get();
            $grupos = $seleccionados->pluck('grupo_archivo')->unique();
            if ($seleccionados->count() !== count($cuotas) || $grupos->count() !== 1
                || max($cuotas) > $registro->numero_cuotas) {
                throw ValidationException::withMessages(['archivo' => 'Para corregir sólo los datos, selecciona cuotas de un mismo comprobante cargado.']);
            }

            $archivos->where('grupo_archivo', $grupos->first())->update([
                'folio' => $metadatos['folio'],
                'fecha_reintegro' => $metadatos['fecha_reintegro'],
                'monto_reintegro_pesos' => $metadatos['monto_reintegro_pesos'],
                'cargado_por_id' => $usuarioId,
            ]);
        });
    }

    public function avanzar(DescuentoCgr $descuento, string $desde, string $hacia, array $tipos, ?string $fechaColumna, string $rolDestino, string $evento): void
    {
        DB::transaction(function () use ($descuento, $desde, $hacia, $tipos, $fechaColumna): void {
            $registro = DescuentoCgr::query()->lockForUpdate()->findOrFail($descuento->id);
            $this->exigirEstado($registro, $desde);
            if ($registro->observacionesAuditoria()->whereNull('reenviada_en')->exists()) {
                throw ValidationException::withMessages(['estado' => 'Usa la acción de reenvío de la observación de Auditoría.']);
            }
            if (! $this->completos($registro, $tipos)) {
                throw ValidationException::withMessages(['documentos' => 'Debes completar los documentos de todas las cuotas antes de avanzar.']);
            }
            $registro->estado = $hacia;
            if ($fechaColumna) {
                $registro->{$fechaColumna} = now();
            }
            $registro->save();
        });

        $this->notificarEtapa($descuento, $rolDestino, $evento);
    }

    public function notificarEtapa(DescuentoCgr $descuento, string $rolDestino, string $evento): void
    {
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

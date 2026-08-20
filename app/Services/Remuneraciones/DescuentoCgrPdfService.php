<?php

namespace App\Services\Remuneraciones;

use App\Models\DescuentoCgr;
use App\Models\DescuentoCgrDocumentoMensual;
use App\Support\Cometidos\SimpleQrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class DescuentoCgrPdfService
{
    public function __construct(private readonly CronogramaDescuentoCgrService $cronograma)
    {
    }

    public function generar(DescuentoCgr $descuento): string
    {
        $descuento = $this->prepararDocumento($descuento);
        $calculo = $this->cronograma->calcular($descuento);
        $validacionUrl = route('descuentos-cgr.verificar', $descuento->codigo_verificacion);

        return Pdf::loadView('pdf.descuentos-cgr.informe', [
            'descuentoCgr' => $descuento,
            'calculo' => $calculo,
            'validacionUrl' => $validacionUrl,
            'qrDataUri' => SimpleQrCode::dataUri($validacionUrl, 3, 3),
            'logoDataUri' => $this->logoDataUri(),
        ])->setPaper('a4', 'landscape')->output();
    }

    public function prepararDocumento(DescuentoCgr $descuento): DescuentoCgr
    {
        if (! $descuento->codigo_verificacion) {
            $descuento->forceFill(['codigo_verificacion' => $this->nuevoCodigo()]);
        }

        $hash = $this->huellaActual($descuento);
        if ($descuento->documento_hash !== $hash || ! $descuento->documento_emitido_en) {
            $descuento->forceFill([
                'documento_hash' => $hash,
                'documento_emitido_en' => now(),
            ]);
        }

        if ($descuento->isDirty()) {
            $descuento->save();
        }

        return $descuento->fresh();
    }

    /**
     * @return array{contenido:string,documento:DescuentoCgrDocumentoMensual,fila:array}
     */
    public function generarMensual(DescuentoCgr $descuento, int $numeroCuota): array
    {
        $fila = $this->filaMensual($descuento, $numeroCuota);
        abort_if($fila === null, 404);

        $documento = DescuentoCgrDocumentoMensual::query()->firstOrNew([
            'descuento_cgr_id' => $descuento->id,
            'numero_cuota' => $numeroCuota,
        ]);
        $hash = $this->huellaMensual($descuento, $fila);
        $contenidoCambio = $documento->exists && $documento->documento_hash !== $hash;

        $documento->periodo = $fila['periodo']->startOfMonth()->toDateString();
        if (! $documento->codigo_verificacion || $contenidoCambio) {
            $documento->codigo_verificacion = $this->nuevoCodigoMensual();
        }
        if ($documento->documento_hash !== $hash || ! $documento->documento_emitido_en) {
            $documento->documento_hash = $hash;
            $documento->documento_emitido_en = now();
        }
        if ($documento->isDirty()) {
            $documento->save();
        }

        $validacionUrl = route('descuentos-cgr.mensual.verificar', $documento->codigo_verificacion);
        $contenido = Pdf::loadView('pdf.descuentos-cgr.mensual', [
            'descuentoCgr' => $descuento,
            'documento' => $documento,
            'fila' => $fila,
            'validacionUrl' => $validacionUrl,
            'qrDataUri' => SimpleQrCode::dataUri($validacionUrl, 3, 3),
            'logoDataUri' => $this->logoDataUri(),
        ])->setPaper('a4', 'portrait')->output();

        return compact('contenido', 'documento', 'fila');
    }

    /** @return array{hash_actual:string,integro:bool} */
    public function verificarIntegridad(DescuentoCgr $descuento): array
    {
        $hashActual = $this->huellaActual($descuento);

        return [
            'hash_actual' => $hashActual,
            'integro' => $descuento->documento_hash !== null
                && hash_equals($descuento->documento_hash, $hashActual),
        ];
    }

    /** @return array{hash_actual:?string,integro:bool,fila:?array} */
    public function verificarIntegridadMensual(DescuentoCgrDocumentoMensual $documento): array
    {
        $descuento = $documento->descuentoCgr;
        $fila = $descuento ? $this->filaMensual($descuento, $documento->numero_cuota) : null;
        $hashActual = $descuento && $fila ? $this->huellaMensual($descuento, $fila) : null;

        return [
            'hash_actual' => $hashActual,
            'integro' => $hashActual !== null
                && hash_equals($documento->documento_hash, $hashActual),
            'fila' => $fila,
        ];
    }

    private function huellaActual(DescuentoCgr $descuento): string
    {
        $calculo = $this->cronograma->calcular($descuento);
        $contenido = [
            'registro' => [
                'id' => $descuento->id,
                'rut' => $descuento->rut,
                'nombre' => $descuento->nombre,
                'numero_resolucion' => $descuento->numero_resolucion,
                'fecha_resolucion' => $descuento->fecha_resolucion?->toDateString(),
                'deuda_definitiva_pesos' => (int) $descuento->deuda_definitiva_pesos,
                'deuda_equivalente_utm' => (string) $descuento->deuda_equivalente_utm,
                'cuota_utm' => (string) $descuento->cuota_utm,
                'numero_cuotas' => (int) $descuento->numero_cuotas,
                'tasa_interes_anual' => (string) $descuento->tasa_interes_anual,
                'tasa_interes_mensual' => (string) $descuento->tasa_interes_mensual,
                'fecha_primer_descuento' => $descuento->fecha_primer_descuento?->toDateString(),
                'observaciones' => $descuento->observaciones,
            ],
            'resolucion_pdf' => [
                'nombre' => $descuento->resolucion_pdf_nombre,
                'tamano' => $descuento->resolucion_pdf_tamano,
                'sha256' => $this->hashResolucion($descuento->resolucion_pdf_path),
            ],
            'cronograma' => collect($calculo['filas'])->map(fn (array $fila) => [
                'numero' => $fila['numero'],
                'periodo' => $fila['periodo']->format('Y-m'),
                'valor_utm' => $fila['valor_utm'],
                'saldo_inicial_utm' => $fila['saldo_inicial_utm'],
                'capital_utm' => $fila['capital_utm'],
                'saldo_final_utm' => $fila['saldo_final_utm'],
                'saldo_inicial_pesos' => $fila['saldo_inicial_pesos'],
                'capital_pesos' => $fila['capital_pesos'],
                'interes_pesos' => $fila['interes_pesos'],
                'descuento_pesos' => $fila['descuento_pesos'],
            ])->all(),
            'totales' => $calculo['totales'],
            'saldo_final_utm' => $calculo['saldo_final_utm'],
            'utm_faltantes' => $calculo['utm_faltantes'],
        ];

        return hash('sha256', json_encode(
            $contenido,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
    }

    private function huellaMensual(DescuentoCgr $descuento, array $fila): string
    {
        $contenido = [
            'registro' => [
                'id' => $descuento->id,
                'rut' => $descuento->rut,
                'nombre' => $descuento->nombre,
                'numero_resolucion' => $descuento->numero_resolucion,
                'fecha_resolucion' => $descuento->fecha_resolucion?->toDateString(),
                'deuda_definitiva_pesos' => (int) $descuento->deuda_definitiva_pesos,
                'deuda_equivalente_utm' => (string) $descuento->deuda_equivalente_utm,
                'cuota_utm' => (string) $descuento->cuota_utm,
                'numero_cuotas' => (int) $descuento->numero_cuotas,
                'tasa_interes_anual' => (string) $descuento->tasa_interes_anual,
                'tasa_interes_mensual' => (string) $descuento->tasa_interes_mensual,
                'fecha_primer_descuento' => $descuento->fecha_primer_descuento?->toDateString(),
            ],
            'resolucion_pdf' => [
                'nombre' => $descuento->resolucion_pdf_nombre,
                'tamano' => $descuento->resolucion_pdf_tamano,
                'sha256' => $this->hashResolucion($descuento->resolucion_pdf_path),
            ],
            'cuota' => [
                'numero' => $fila['numero'],
                'periodo' => $fila['periodo']->format('Y-m'),
                'valor_utm' => $fila['valor_utm'],
                'saldo_inicial_utm' => $fila['saldo_inicial_utm'],
                'capital_utm' => $fila['capital_utm'],
                'saldo_final_utm' => $fila['saldo_final_utm'],
                'saldo_inicial_pesos' => $fila['saldo_inicial_pesos'],
                'capital_pesos' => $fila['capital_pesos'],
                'interes_pesos' => $fila['interes_pesos'],
                'descuento_pesos' => $fila['descuento_pesos'],
                'pendiente_utm' => $fila['pendiente_utm'],
            ],
        ];

        return hash('sha256', json_encode(
            $contenido,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
    }

    private function filaMensual(DescuentoCgr $descuento, int $numeroCuota): ?array
    {
        if ($numeroCuota < 1) {
            return null;
        }

        return collect($this->cronograma->calcular($descuento)['filas'])
            ->firstWhere('numero', $numeroCuota);
    }

    private function hashResolucion(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $hash = hash_file('sha256', Storage::disk('local')->path($path));

        return $hash === false ? null : $hash;
    }

    private function nuevoCodigo(): string
    {
        do {
            $codigo = 'CGR-'.strtoupper(bin2hex(random_bytes(10)));
        } while (DescuentoCgr::query()->where('codigo_verificacion', $codigo)->exists());

        return $codigo;
    }

    private function nuevoCodigoMensual(): string
    {
        do {
            $codigo = 'CGR-M-'.strtoupper(bin2hex(random_bytes(10)));
        } while (DescuentoCgrDocumentoMensual::query()->where('codigo_verificacion', $codigo)->exists());

        return $codigo;
    }

    private function logoDataUri(): ?string
    {
        $path = resource_path('images/logo-gobierno-slep.png');
        if (! is_file($path)) {
            return null;
        }

        $contenido = file_get_contents($path);

        return $contenido === false ? null : 'data:image/png;base64,'.base64_encode($contenido);
    }
}

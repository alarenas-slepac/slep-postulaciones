<?php

namespace App\Services\Remuneraciones;

use App\Models\DescuentoCgr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class DescuentoCgrExpedienteService
{
    private const CARPETAS = [
        'liquidacion' => '01-liquidaciones',
        'sigfe' => '02-comprobantes-sigfe',
        'tgr' => '03-comprobantes-tgr',
        'transferencia_institucion' => '04-transferencias-otras-instituciones',
        'liquidacion_validada' => '05-liquidaciones-validadas',
    ];

    private const PREFIJOS = [
        'liquidacion' => 'liquidacion',
        'sigfe' => 'comprobante-sigfe',
        'tgr' => 'comprobante-tgr',
        'transferencia_institucion' => 'comprobante-transferencia',
        'liquidacion_validada' => 'liquidacion-validada',
    ];

    public function generar(DescuentoCgr $descuento): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión ZIP de PHP no está disponible.');
        }

        $temporal = tempnam(sys_get_temp_dir(), 'cgr_expediente_');
        if ($temporal === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal del expediente.');
        }

        $zip = new ZipArchive;
        if ($zip->open($temporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporal);
            throw new RuntimeException('No fue posible crear el ZIP del expediente.');
        }

        try {
            $rut = strtoupper(preg_replace('/[^0-9kK-]/', '', (string) $descuento->rut)) ?: 'sin-rut';
            $indice = fopen('php://temp', 'w+');
            if ($indice === false) {
                throw new RuntimeException('No fue posible crear el índice del expediente.');
            }

            try {
                fwrite($indice, "\xEF\xBB\xBF");
                fputcsv($indice, ['Tipo', 'Archivo', 'Cuotas', 'Meses', 'Folio', 'Fecha de reintegro', 'Monto de reintegro (pesos)', 'Última carga']);

                foreach (self::CARPETAS as $tipo => $carpeta) {
                    $zip->addEmptyDir($carpeta);
                    $archivos = $descuento->archivos()->where('tipo', $tipo)->orderBy('numero_cuota')->get()->groupBy('path');
                    $nombresUsados = [];

                    foreach ($archivos as $path => $asociaciones) {
                        if (! Storage::disk('local')->exists($path)) {
                            throw new RuntimeException('Falta un respaldo del cronograma. Revisa los archivos asociados antes de descargar el expediente.');
                        }

                        $cuotas = $asociaciones->pluck('numero_cuota')->unique()->sort()->values();
                        $periodos = $cuotas->map(fn (int $cuota) => $descuento->fecha_primer_descuento->copy()->startOfMonth()->addMonthsNoOverflow($cuota - 1)->format('m-Y'));
                        $periodoNombre = $periodos->count() === 1
                            ? $periodos->first()
                            : $periodos->first().'_a_'.$periodos->last().'-'.$periodos->count().'-meses';
                        $base = self::PREFIJOS[$tipo].'-'.$rut.'-'.$periodoNombre;
                        $nombre = $base.'.pdf';
                        for ($numero = 2; isset($nombresUsados[$nombre]); $numero++) {
                            $nombre = $base.'-'.$numero.'.pdf';
                        }
                        $nombresUsados[$nombre] = true;
                        $destino = $carpeta.'/'.$nombre;
                        if (! $zip->addFile(Storage::disk('local')->path($path), $destino)) {
                            throw new RuntimeException('No fue posible agregar un respaldo al ZIP del expediente.');
                        }

                        $primero = $asociaciones->first();
                        fputcsv($indice, [
                            $tipo,
                            $destino,
                            $cuotas->implode(', '),
                            $periodos->implode(', '),
                            $primero->folio ?? '',
                            $primero->fecha_reintegro?->format('d-m-Y') ?? '',
                            $primero->monto_reintegro_pesos ?? '',
                            $asociaciones->max('updated_at')?->format('d-m-Y H:i') ?? '',
                        ]);
                    }
                }

                rewind($indice);
                $zip->addFromString('indice-documentos.csv', stream_get_contents($indice));
            } finally {
                fclose($indice);
            }

            if (! $zip->close()) {
                throw new RuntimeException('No fue posible finalizar el ZIP del expediente.');
            }

            return $temporal;
        } catch (\Throwable $error) {
            $zip->close();
            @unlink($temporal);
            throw $error;
        }
    }
}

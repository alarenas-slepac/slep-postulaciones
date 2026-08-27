<?php

namespace App\Exports;

use App\Models\DescuentoCgr;
use App\Services\Remuneraciones\CronogramaDescuentoCgrService;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DescuentosCgrMensualExport
{
    public function __construct(private readonly CronogramaDescuentoCgrService $cronograma) {}

    public function download(CarbonImmutable $periodo, mixed $generadoPor = null): StreamedResponse
    {
        $this->prepareRuntime();
        $filas = $this->rowsForPeriod($periodo);
        $libro = $this->workbook($filas, $periodo, $generadoPor);
        $nombre = 'descuentos_cgr_'.$periodo->format('Y_m').'_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($libro): void {
            try {
                (new Xlsx($libro))->save('php://output');
            } finally {
                $libro->disconnectWorksheets();
            }
        }, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function rowsForPeriod(CarbonImmutable $periodo): Collection
    {
        $periodo = $periodo->startOfMonth();

        return DescuentoCgr::query()
            ->whereDate('fecha_primer_descuento', '<=', $periodo->endOfMonth()->toDateString())
            ->orderBy('nombre')
            ->orderBy('rut')
            ->get()
            ->map(function (DescuentoCgr $descuento) use ($periodo): ?array {
                $inicio = CarbonImmutable::parse($descuento->fecha_primer_descuento)->startOfMonth();
                $fin = $inicio->addMonthsNoOverflow(max(0, (int) $descuento->numero_cuotas - 1));

                if ($periodo->lessThan($inicio) || $periodo->greaterThan($fin)) {
                    return null;
                }

                $fila = collect($this->cronograma->calcular($descuento)['filas'])
                    ->first(fn (array $item) => $item['periodo']->format('Y-m') === $periodo->format('Y-m'));

                if (! $fila) {
                    return null;
                }

                return [
                    'rut' => Rut::format($descuento->rut) ?? $descuento->rut,
                    'nombre' => $descuento->nombre,
                    'numero_resolucion' => $descuento->numero_resolucion,
                    'mes' => $periodo->format('m-Y'),
                    'valor_utm' => $fila['valor_utm'],
                    'saldo_inicial_utm' => $fila['saldo_inicial_utm'],
                    'capital_utm' => $fila['capital_utm'],
                    'saldo_final_utm' => $fila['saldo_final_utm'],
                    'saldo_inicial_pesos' => $fila['saldo_inicial_pesos'],
                    'capital_pesos' => $fila['capital_pesos'],
                    'interes_pesos' => $fila['interes_pesos'],
                    'descuento_pesos' => $fila['descuento_pesos'],
                ];
            })
            ->filter()
            ->values();
    }

    /** @param Collection<int, array<string, mixed>> $filas */
    public function workbook(Collection $filas, CarbonImmutable $periodo, mixed $generadoPor = null): Spreadsheet
    {
        $libro = new Spreadsheet;
        $libro->getProperties()
            ->setCreator($this->generatedByName($generadoPor))
            ->setTitle('Descuentos CGR '.$periodo->format('m-Y'))
            ->setSubject('Descuentos aplicados en el mes seleccionado');

        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Descuentos '.$periodo->format('m-Y'));
        $encabezados = [
            'RUT',
            'Nombre completo',
            'N° resolución',
            'Mes',
            'Valor UTM',
            'Saldo inicial UTM',
            'Capital UTM',
            'Saldo final UTM',
            'Saldo inicial',
            'Capital',
            'Interés mes',
            'Descuento total',
        ];
        $hoja->fromArray([$encabezados], null, 'A1');
        $hoja->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9E2F3']],
            ],
        ]);
        $hoja->getRowDimension(1)->setRowHeight(30);

        foreach ($filas->values() as $indice => $fila) {
            $numeroFila = $indice + 2;
            $hoja->setCellValueExplicit("A{$numeroFila}", (string) $fila['rut'], DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("B{$numeroFila}", (string) $fila['nombre'], DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("C{$numeroFila}", (string) $fila['numero_resolucion'], DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("D{$numeroFila}", (string) $fila['mes'], DataType::TYPE_STRING);

            foreach (array_values(array_slice($fila, 4)) as $offset => $valor) {
                $columna = chr(ord('E') + $offset);
                $hoja->setCellValue("{$columna}{$numeroFila}", $valor);
            }
        }

        $ultimaFila = max(1, $filas->count() + 1);
        $hoja->setAutoFilter("A1:L{$ultimaFila}");
        $hoja->freezePane('A2');
        if ($ultimaFila >= 2) {
            $hoja->getStyle("A2:L{$ultimaFila}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            $hoja->getStyle("F2:H{$ultimaFila}")->getNumberFormat()->setFormatCode('0.0000');
            foreach (['E', 'I', 'J', 'K', 'L'] as $columna) {
                $hoja->getStyle("{$columna}2:{$columna}{$ultimaFila}")
                    ->getNumberFormat()->setFormatCode('$ #,##0');
            }
        }
        foreach ([
            'A' => 18,
            'B' => 38,
            'C' => 20,
            'D' => 13,
            'E' => 16,
            'F' => 19,
            'G' => 16,
            'H' => 18,
            'I' => 18,
            'J' => 18,
            'K' => 18,
            'L' => 20,
        ] as $columna => $ancho) {
            $hoja->getColumnDimension($columna)->setWidth($ancho);
        }

        return $libro;
    }

    private function generatedByName(mixed $usuario): string
    {
        if (! $usuario) {
            return 'Sistema SLEP';
        }

        $nombre = trim(implode(' ', array_filter([
            (string) ($usuario->nombres ?? ''),
            (string) ($usuario->apellido_paterno ?? ''),
            (string) ($usuario->apellido_materno ?? ''),
        ])));

        return $nombre !== '' ? $nombre : ((string) ($usuario->email ?? 'Sistema SLEP'));
    }

    private function prepareRuntime(): void
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        try {
            DB::connection()->disableQueryLog();
        } catch (\Throwable) {
            // La exportación puede continuar si la conexión no permite modificar el query log.
        }
    }
}

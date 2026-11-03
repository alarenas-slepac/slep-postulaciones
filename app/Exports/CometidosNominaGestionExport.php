<?php

namespace App\Exports;

use App\Models\CometidoFuncionario;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CometidosNominaGestionExport
{
    private const ANCHOS = ['A' => 9, 'B' => 26, 'C' => 18, 'D' => 18, 'E' => 38, 'F' => 23, 'G' => 32, 'H' => 26,
        'I' => 42, 'J' => 12, 'K' => 40, 'L' => 18, 'M' => 18, 'N' => 12, 'O' => 14, 'P' => 38,
        'Q' => 38, 'R' => 38, 'S' => 19, 'T' => 30];

    public function download(iterable $cometidos, string $bandeja, array $filtros): StreamedResponse
    {
        return response()->streamDownload(function () use ($cometidos, $bandeja, $filtros) {
            $book = $this->workbook($cometidos, $bandeja, $filtros);
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'nomina_cometidos_'.$bandeja.'_'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function workbook(iterable $cometidos, string $bandeja, array $filtros = []): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet = $book->getActiveSheet()->setTitle('Solicitudes pendientes');
        $text = static fn ($cell, $value) => $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);
        $origen = $bandeja === 'ac_por_autorizar' ? 'Administración Central' : 'Establecimientos';
        $resumen = ['Mes de solicitud: '.($filtros['mes'] ?? 'Todos')];
        foreach (['estado' => 'Estado', 'nombre' => 'Nombre', 'rut' => 'RUT', 'establecimiento_id' => 'ID establecimiento'] as $campo => $label) {
            if (filled($filtros[$campo] ?? null)) {
                $valor = $campo === 'estado' ? (CometidoFuncionario::ESTADOS[$filtros[$campo]] ?? $filtros[$campo]) : $filtros[$campo];
                $resumen[] = $label.': '.$valor;
            }
        }
        foreach ([
            1 => 'Cometidos funcionarios · Solicitudes por autorizar o gestionar',
            2 => $origen.' · Generado el '.now()->format('d-m-Y H:i'),
            3 => implode(' · ', $resumen),
            4 => 'Nómina completa de la bandeja y filtros aplicados, según el rol activo. Incluye todas las páginas; no modifica las solicitudes.',
        ] as $line => $value) {
            $sheet->mergeCells('A'.$line.':T'.$line);
            $text('A'.$line, $value);
        }

        $headers = ['ID', 'N° cometido', 'Fecha solicitud', 'RUT', 'Funcionario', 'Estamento', 'Cargo / función', 'Origen',
            'Establecimiento / dependencia', 'RBD', 'Destino', 'Fecha desde', 'Fecha hasta', 'Viático', 'Reembolso',
            'Estado general', 'Estado viático', 'Estado reembolso', 'Requiere pasaje aéreo', 'Estado pasaje aéreo'];
        foreach ($headers as $index => $header) {
            $text([$index + 1, 6], $header);
        }
        $line = 7;
        foreach ($cometidos as $cometido) {
            $esAc = $cometido->esAdministracionCentral();
            $dependencia = $cometido->subdireccion_dependencia_ac ?: $cometido->unidad_departamento_ac
                ?: ($cometido->funcionarioAcAutorizado?->subdireccion_dependencia ?: $cometido->funcionarioAcAutorizado?->unidad_departamento);
            $pasajes = $cometido->pasajeAereo->sortBy('id')->pluck('estado_pasaje')->unique()->map(fn ($estado) => match ($estado) {
                'pendiente_reserva' => 'Pendiente de reserva',
                'pendiente_cdp_pasaje' => 'Pendiente CDP de pasaje',
                'pendiente_compra' => 'Pendiente de compra',
                'boleto_disponible' => 'Boleto disponible',
                default => $estado,
            })->implode(' / ');
            $sheet->setCellValue('A'.$line, (int) $cometido->id);
            foreach ([
                2 => $cometido->numero_cometido_interno ?: '#'.$cometido->id,
                4 => $cometido->funcionario_rut, 5 => $cometido->funcionario_nombre,
                6 => $cometido->estamento, 7 => $cometido->cargo_funcion,
                8 => $esAc ? 'Administración Central' : 'Establecimiento',
                9 => $esAc ? $dependencia : $cometido->establecimiento?->nombre_establecimiento,
                10 => $esAc ? '' : ($cometido->rbd ?: $cometido->establecimiento?->rbd),
                11 => $cometido->destino ?: $cometido->comuna_destino_nombre,
                14 => $cometido->solicita_viatico ? 'Sí' : 'No', 15 => $cometido->solicita_reembolso ? 'Sí' : 'No',
                16 => $cometido->etiquetaEstado(),
                17 => $this->estado($cometido->estadoViaticoActual()),
                18 => $this->estado($cometido->estadoReembolsoActual()),
                19 => $cometido->requiere_pasaje_aereo ? 'Sí' : 'No', 20 => $pasajes,
            ] as $column => $value) {
                $text([$column, $line], $value);
            }
            foreach ([3 => 'fecha_solicitud', 12 => 'fecha_desde', 13 => 'fecha_hasta'] as $column => $campo) {
                if ($cometido->{$campo}) {
                    $sheet->setCellValue([$column, $line], Date::PHPToExcel($cometido->{$campo}));
                    $sheet->getStyle([$column, $line])->getNumberFormat()->setFormatCode('dd-mm-yyyy');
                }
            }
            $lineas = 1;
            foreach (self::ANCHOS as $column => $width) {
                $lineasCelda = 0;
                foreach (explode("\n", (string) $sheet->getCell($column.$line)->getFormattedValue()) as $parrafo) {
                    $lineasCelda += max(1, (int) ceil(mb_strwidth($parrafo) / max(1, $width - 4)));
                }
                $lineas = max($lineas, $lineasCelda);
            }
            $sheet->getRowDimension($line)->setRowHeight(min(409, $lineas * 16 + 8));
            $line++;
        }
        $count = $line - 7;
        if ($count === 0) {
            $sheet->mergeCells('A7:T7');
            $text('A7', 'No hay solicitudes por autorizar o gestionar con los filtros aplicados.');
            $line = 8;
        }
        $sheet->mergeCells('A'.$line.':T'.$line);
        $text('A'.$line, 'Total: '.$count.' solicitud(es)');
        $sheet->getStyle('A1:T'.$line)->getAlignment()->setWrapText(true)->setVertical('top');
        foreach (['A1:T1', 'A6:T6'] as $range) {
            $sheet->getStyle($range)->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B3D91']]]);
        }
        $sheet->getStyle('A1')->getFont()->setSize(16);
        $sheet->getStyle('A'.$line.':T'.$line)->applyFromArray(['font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF6FF']]]);
        foreach (self::ANCHOS as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        foreach ([1 => 32, 2 => 25, 3 => 35, 4 => 30, 6 => 38] as $row => $height) {
            $sheet->getRowDimension($row)->setRowHeight($height);
        }
        $sheet->setAutoFilter('A6:T'.max(6, 6 + $count));
        $sheet->freezePane('F7');

        return $book;
    }

    private function estado(?string $estado): string
    {
        return $estado ? (CometidoFuncionario::ESTADOS[$estado] ?? $estado) : '';
    }
}

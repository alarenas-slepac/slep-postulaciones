<?php

namespace App\Exports;

use App\Models\SolicitudReemplazoProrroga;
use App\Services\SolicitudReemplazoProrrogaService;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProrrogasReemplazosExport
{
    public function __construct(private readonly SolicitudReemplazoProrrogaService $servicio) {}

    public function download(iterable $casos, int $anio, string $estamento, array $vigencia): StreamedResponse
    {
        return response()->streamDownload(function () use ($casos, $anio, $estamento, $vigencia): void {
            $libro = $this->workbook($casos, $anio, $estamento, $vigencia);
            try {
                (new Xlsx($libro))->save('php://output');
            } finally {
                $libro->disconnectWorksheets();
            }
        }, "Nomina_Prorrogas_{$estamento}_{$anio}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function workbook(iterable $casos, int $anio, string $estamento, array $vigencia): Spreadsheet
    {
        $libro = new Spreadsheet;
        $etiqueta = SolicitudReemplazoProrroga::ESTAMENTOS[$estamento];
        $hoja = $libro->getActiveSheet()->setTitle($etiqueta);
        $texto = fn ($celda, $valor) => $hoja->setCellValueExplicit($celda, (string) $valor, DataType::TYPE_STRING);
        $hoja->setShowGridlines(false);
        $texto('A1', "Prórrogas Reemplazos · {$etiqueta} · Diciembre {$anio}");
        $cabeceras = ['RUT', 'Reemplazante', 'Último reemplazo', 'RBD', 'Establecimiento', 'Área de desempeño', 'Jornada (h)',
            'Financiamiento por horas', 'Inicio continuo', 'Corte diciembre', 'Inicio prórroga', 'Término prórroga', 'Estado', 'Resolución', 'N° orden de trabajo'];
        foreach ($cabeceras as $columna => $cabecera) {
            $texto([$columna + 1, 3], $cabecera);
        }
        $fila = 4;
        $anchos = [15, 36, 18, 10, 32, 27, 14, 47, 16, 17, 16, 17, 26, 18, 22];
        foreach ($casos as $caso) {
            $ultimo = $caso['ultimo_reemplazo'];
            $registrada = $caso['registrada'] ?? null;
            $expediente = $caso['expediente'] ?? null;
            foreach ([
                1 => Rut::format($caso['rut']), 2 => $caso['nombre'], 3 => $ultimo['solicitud'] ?? null, 4 => $ultimo['rbd'] ?? null,
                5 => $ultimo['establecimiento'] ?? null, 6 => $ultimo['cargo'] ?? null,
                8 => $this->servicio->financiamiento($ultimo), 13 => $registrada ? 'Registrada' : 'Pendiente de revisión',
                14 => $registrada?->numero_resolucion, 15 => $expediente?->correlativoOrdenTrabajo?->id,
            ] as $columna => $valor) {
                $texto([$columna, $fila], $valor);
            }
            if (isset($ultimo['horas'])) {
                $hoja->setCellValue([7, $fila], (float) $ultimo['horas']);
            }
            foreach ([9 => $caso['inicio_continuidad'], 10 => $caso['fecha_corte'], 11 => $vigencia['desde'], 12 => $vigencia['hasta']] as $columna => $fecha) {
                $hoja->setCellValue([$columna, $fila], Date::PHPToExcel(CarbonImmutable::parse($fecha)));
                $hoja->getStyle([$columna, $fila])->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            }
            $lineas = 1;
            foreach ([2, 5, 6, 8] as $columna) {
                $celda = [$columna, $fila];
                $hoja->getStyle($celda)->getAlignment()->setWrapText(true);
                $longitud = mb_strwidth((string) $hoja->getCell($celda)->getValue());
                $lineas = max($lineas, (int) ceil($longitud / ($anchos[$columna - 1] - 3)));
            }
            $hoja->getRowDimension($fila)->setRowHeight(16 * $lineas + 5);
            $fila++;
        }
        foreach ($anchos as $columna => $ancho) {
            $hoja->getColumnDimensionByColumn($columna + 1)->setWidth($ancho);
        }
        foreach (['A1:O1', 'A3:O3'] as $rango) {
            $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('17365D');
            $hoja->getStyle($rango)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        }
        $hoja->getStyle('A1:O1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER_CONTINUOUS);
        $hoja->getStyle('A3:O3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $hoja->getRowDimension(3)->setRowHeight(31);
        $hoja->setAutoFilter('A3:O'.max(3, $fila - 1));
        $hoja->freezePane('C4');

        return $libro;
    }
}

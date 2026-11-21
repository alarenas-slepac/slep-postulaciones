<?php

namespace App\Exports;

use App\Support\DotacionDocenteProporcional;
use App\Support\DotacionInformeFinanciamiento;
use App\Support\DotacionInformePlanesNiveles;
use App\Support\DotacionLecturaCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionInformeFinanciamientoExport
{
    public function __construct(
        private readonly DotacionInformeFinanciamiento $calculador,
        private readonly DotacionDocenteProporcional $dotacionDocente,
    ) {}

    public function download(Builder $establecimientos, int $anio): StreamedResponse
    {
        @set_time_limit(240);
        $book = DotacionLecturaCache::ejecutar(function () use ($establecimientos, $anio): Spreadsheet {
            $periodo = $this->calculador->periodoAsistentes();
            $dotacion = $this->dotacionDocente->resumir($anio);
            $filas = [];
            foreach ((clone $establecimientos)->lazy(10) as $ee) {
                try {
                    $filas[] = $this->calculador->fila($ee, $anio, $periodo) + ['dotacion_docente' => $dotacion[$ee->id] ?? []];
                } catch (\Throwable $exception) {
                    report($exception);
                    throw ValidationException::withMessages([
                        'export_financiamiento' => 'No se pudo calcular el financiamiento del RBD '.$ee->rbd.'.',
                    ]);
                }
            }

            return $this->workbook($filas, $anio, $periodo);
        });

        return response()->streamDownload(function () use ($book): void {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'horas_financiamiento_por_rbd_'.$anio.'_'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function workbook(array $filas, int $anio, ?array $periodo): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('SGA SLEP Andalién Costa')->setTitle('Horas por financiamiento y RBD '.$anio);
        $detallePadron = $periodo
            ? sprintf('Padrón vigente de AAEE %02d/%d.', $periodo['mes'], $periodo['anio'])
            : 'Padrón de AAEE sin período disponible.';
        $nota = 'Docentes: asignaciones de dotación '.$anio.'. '.$detallePadron;

        foreach (DotacionInformeFinanciamiento::FUENTES as $clave => $nombre) {
            $sheet = $clave === 'general' ? $book->getActiveSheet() : $book->createSheet();
            $horas = array_map(fn ($fila) => [
                'rbd' => $fila['rbd'], 'nombre' => $fila['nombre'], 'horas' => $fila['horas'][$clave],
            ], $filas);
            $this->escribirHoja($sheet, $nombre, $anio, $nota, $horas);
        }

        $otras = [];
        foreach ($filas as $fila) {
            foreach ($fila['otras_aaee'] as $fuente) {
                $otras[] = ['rbd' => $fila['rbd'], 'nombre' => $fila['nombre']] + $fuente;
            }
        }
        if ($otras) {
            $this->escribirHoja($book->createSheet(), 'Otras fuentes AAEE', $anio, $detallePadron.' Incluye fuentes mixtas sin desglose.', $otras, true);
        }
        $this->escribirProyeccionPie($book->createSheet(), $filas, $anio);
        $this->escribirOfertaEducativa($book->createSheet(), $filas, $anio);
        $this->escribirNivelesModalidades($book->createSheet(), $filas, $anio);
        $this->escribirDetalleOfertaEducativa($book->createSheet(), $filas, $anio);
        $this->escribirDotacionDocente($book->createSheet(), $filas, $anio);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    private function escribirHoja(Worksheet $sheet, string $nombre, int $anio, string $nota, array $filas, bool $detalle = false): void
    {
        $horasCol = $detalle ? 'E' : 'D';
        $encabezados = ['N.º', 'RBD', 'Nombre del establecimiento educacional', ...($detalle ? ['Fuente de financiamiento'] : []), 'Total, horas'];
        $this->prepararHoja($sheet, $nombre, 'Horas de '.$nombre.' proyectadas por RBD · '.$anio, $nota, $horasCol, $encabezados);

        $row = 5;
        foreach ($filas as $fila) {
            $this->escribirRbd($sheet, $row, $fila);
            if ($detalle) {
                $sheet->setCellValueExplicit('D'.$row, $fila['fuente'], DataType::TYPE_STRING);
            }
            $sheet->setCellValue($horasCol.$row, round($fila['horas'], 2));
            $row++;
        }
        $this->cerrarTabla($sheet, $row, $horasCol, [$horasCol], ['D' => $detalle ? 38 : 20, 'E' => 20],
            'Sin establecimientos para los filtros aplicados.');
        $sheet->getStyle($horasCol.'5:'.$horasCol.max(6, $row))->getNumberFormat()->setFormatCode('#,##0.##');
    }

    private function escribirProyeccionPie(Worksheet $sheet, array $filas, int $anio): void
    {
        $this->prepararHoja($sheet, 'Proyección PIE', 'Matrícula y horas de apoyo PIE proyectadas por RBD · '.$anio,
            'Estudiantes PIE por curso · Apoyo docente en horas de contrato y Apoyo A.E. según PAEC.',
            'H', ['N.º', 'RBD', 'Nombre del establecimiento educacional', 'N.º cursos', 'Matrícula NEET',
                'Matrícula NEEP', 'Horas Apoyo Docente', 'Horas Apoyo A.E.']);

        $row = 5;
        foreach ($filas as $fila) {
            $pie = $fila['pie_proyectado'];
            if ($pie['cursos_nee'] === 0) {
                continue;
            }
            $this->escribirRbd($sheet, $row, $fila);
            $sheet->fromArray([
                $pie['cursos_nee'], $pie['neet'], $pie['neep'],
                (int) $pie['educadoras_diferenciales_horas'], (int) ceil($pie['pae_minutos'] / 60),
            ], null, 'D'.$row, true);
            $row++;
        }
        $this->cerrarTabla($sheet, $row, 'H', range('D', 'H'), ['D' => 14, 'E' => 17, 'F' => 17, 'G' => 24, 'H' => 21],
            'Sin cursos activos con estudiantes PIE para los filtros aplicados.');
    }

    private function escribirOfertaEducativa(Worksheet $sheet, array $filas, int $anio): void
    {
        $this->prepararHoja($sheet, 'Oferta educativa', 'Oferta educativa y horas de contratación docente proyectadas por RBD · '.$anio,
            'Cursos activos con matrícula. Cada grupo combinado cuenta como un curso en el total después de combinar.',
            'L', ['RBD', 'Nombre del establecimiento', 'Comuna', 'Modalidad JEC', 'Matrícula', 'Cursos totales',
                'Cursos combinados (grupos)', 'Total cursos después de combinar', 'Horas plan de estudio', 'Contrato equivalente del plan',
                'Trabajo colaborativo PIE (contrato)', 'Contrato plan + trabajo colaborativo PIE']);
        $sheet->getStyle('A4:L4')->getAlignment()->setWrapText(true)->setVertical('center');
        $sheet->getRowDimension(4)->setRowHeight(45);
        $row = 5;
        foreach ($filas as $fila) {
            $oferta = $fila['oferta_educativa'];
            $sheet->setCellValueExplicit('A'.$row, $fila['rbd'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$row, $fila['nombre'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$row, $oferta['comuna'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$row, $oferta['modalidad_jec'], DataType::TYPE_STRING);
            $sheet->fromArray([
                $oferta['matricula'], $oferta['cursos'], $oferta['grupos_combinados'], $oferta['cursos_despues_de_combinar'],
                $oferta['horas_plan'], $oferta['contrato_equivalente'], $oferta['trabajo_colaborativo_pie'], $oferta['contrato_mas_pie'],
            ], null, 'E'.$row, true);
            $row++;
        }
        $this->cerrarTabla($sheet, $row, 'L', range('E', 'L'), ['A' => 11, 'B' => 52, 'C' => 22, 'D' => 24,
            'E' => 13, 'F' => 14, 'G' => 16, 'H' => 19, 'I' => 16, 'J' => 19, 'K' => 19, 'L' => 24],
            'Sin establecimientos para los filtros aplicados.');
        $sheet->getStyle('I5:L'.max(6, $row))->getNumberFormat()->setFormatCode('#,##0.##');
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    }

    private function escribirNivelesModalidades(Worksheet $sheet, array $filas, int $anio): void
    {
        $niveles = DotacionInformePlanesNiveles::NIVELES;
        $this->prepararHoja($sheet, 'Niveles y modalidades', 'Establecimientos educacionales por nivel educativo · '.$anio,
            'Fuente: ficha del establecimiento y cursos activos con matrícula del año consultado.',
            'I', ['RBD', 'Nombre del establecimiento', ...array_values($niveles)]);
        $row = 5;
        foreach ($filas as $fila) {
            $sheet->setCellValueExplicit('A'.$row, $fila['rbd'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$row, $fila['nombre'], DataType::TYPE_STRING);
            $sheet->fromArray(array_map(fn ($nivel) => $fila['niveles_modalidades'][$nivel] ? 'Sí' : 'No',
                array_keys($niveles)), null, 'C'.$row);
            $row++;
        }
        $this->cerrarTabla($sheet, $row, 'I', [], ['A' => 12, 'B' => 55] + array_fill_keys(range('C', 'I'), 14),
            'Sin establecimientos para los filtros aplicados.');
    }

    private function escribirDetalleOfertaEducativa(Worksheet $sheet, array $filas, int $anio): void
    {
        $this->prepararHoja($sheet, 'Detalle oferta educativa', 'Detalle de oferta educativa por RBD, nivel y modalidad · '.$anio,
            'Fuente: Cursos y planes de Dotación. Las horas compartidas entre niveles se consignan en Sin desglose.',
            'L', ['RBD', 'Nombre del establecimiento', 'Comuna', 'Nivel', 'Modalidad JEC', 'Matrícula', 'Cursos',
                'Horas plan de estudio', 'Contrato equivalente del plan', 'Trabajo colaborativo PIE (contrato)',
                'Contrato plan + trabajo colaborativo PIE', 'Observaciones']);
        $sheet->getStyle('A4:L4')->getAlignment()->setWrapText(true)->setVertical('center');
        $sheet->getRowDimension(4)->setRowHeight(45);
        $niveles = DotacionInformePlanesNiveles::NIVELES + ['sin_desglose' => 'Sin desglose'];
        $row = 5;
        foreach ($filas as $fila) {
            foreach ($fila['detalle_oferta_educativa'] as $clave => $nivel) {
                foreach (['A' => $nivel['rbd'], 'B' => $nivel['nombre'], 'C' => $nivel['comuna'],
                    'D' => $niveles[$clave], 'E' => $nivel['modalidad_jec'], 'L' => $nivel['observaciones']] as $columna => $valor) {
                    $sheet->setCellValueExplicit($columna.$row, $valor, DataType::TYPE_STRING);
                }
                $sheet->fromArray([$nivel['matricula'], $nivel['cursos'], $nivel['horas_plan'], $nivel['contrato_equivalente'],
                    $nivel['trabajo_colaborativo_pie'], $nivel['contrato_mas_pie']], null, 'F'.$row, true);
                $lineas = max(1, ceil(mb_strlen($nivel['observaciones']) / 42), ceil(mb_strlen($nivel['nombre']) / 37));
                $sheet->getRowDimension($row)->setRowHeight(min(409, $lineas * 18));
                $row++;
            }
        }
        $this->cerrarTabla($sheet, $row, 'L', range('F', 'K'), ['A' => 11, 'B' => 42, 'C' => 20, 'D' => 17, 'E' => 24,
            'F' => 13, 'G' => 10, 'H' => 16, 'I' => 19, 'J' => 19, 'K' => 24, 'L' => 48],
            'Sin niveles con oferta educativa para los filtros aplicados.');
        $sheet->getStyle('B5:B'.max(5, $row - 1))->getAlignment()->setWrapText(true);
        $sheet->getStyle('L5:L'.max(5, $row - 1))->getAlignment()->setWrapText(true);
        $sheet->getStyle('H5:K'.max(6, $row))->getNumberFormat()->setFormatCode('#,##0.##');
        $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    }

    private function escribirDotacionDocente(Worksheet $sheet, array $filas, int $anio): void
    {
        $this->prepararHoja($sheet, 'Dotación docente', 'Resumen de dotación docente por establecimiento · '.$anio,
            'Incluye Dirección ADP por cubrir, horas de AAEE y horas por situaciones docentes elegibles.',
            'J', ['RBD', 'Establecimiento', 'N.º Docentes Función Directiva', 'Horas Función Directiva',
                'N.º Docentes Función Técnico-Pedagógica', 'Horas Función Técnico-Pedagógica', 'N.º Docentes Aula',
                'Horas Aula', 'N.º Total Docentes', 'Total de Horas Asignadas']);
        $sheet->getStyle('A4:J4')->getAlignment()->setWrapText(true)->setVertical('center');
        $sheet->getRowDimension(4)->setRowHeight(46);
        $row = 5;
        foreach ($filas as $fila) {
            $sheet->setCellValueExplicit('A'.$row, $fila['rbd'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$row, $fila['nombre'], DataType::TYPE_STRING);
            $valores = [];
            foreach (DotacionDocenteProporcional::GRUPOS as $grupo) {
                $datos = $fila['dotacion_docente'][$grupo] ?? ['docentes' => 0, 'horas' => 0];
                $valores[] = $datos['docentes'];
                $valores[] = $datos['horas'];
            }
            $sheet->fromArray([...$valores,
                $valores[0] + $valores[2] + $valores[4], $valores[1] + $valores[3] + $valores[5],
            ], null, 'C'.$row, true);
            $row++;
        }
        $this->cerrarTabla($sheet, $row, 'J', range('C', 'J'), ['A' => 12, 'B' => 52] + array_fill_keys(range('C', 'J'), 21),
            'Sin establecimientos para los filtros aplicados.');
        foreach (['C', 'E', 'G', 'I'] as $columna) {
            $sheet->getStyle($columna.'5:'.$columna.max(6, $row))->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }

    private function prepararHoja(Worksheet $sheet, string $nombre, string $titulo, string $nota, string $ultimaCol, array $encabezados): void
    {
        $sheet->setTitle($nombre)->setShowGridlines(false);
        $sheet->mergeCells('A1:'.$ultimaCol.'1')->setCellValue('A1', $titulo);
        $sheet->mergeCells('A2:'.$ultimaCol.'2')->setCellValue('A2', $nota);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('0B3D91');
        $sheet->getStyle('A2')->getFont()->setItalic(true);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->fromArray($encabezados, null, 'A4');
    }

    private function escribirRbd(Worksheet $sheet, int $row, array $fila): void
    {
        $sheet->setCellValue('A'.$row, $row - 4);
        $sheet->setCellValueExplicit('B'.$row, $fila['rbd'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C'.$row, $fila['nombre'], DataType::TYPE_STRING);
    }

    private function cerrarTabla(Worksheet $sheet, int $row, string $ultimaCol, array $columnasTotales, array $anchos, string $vacio): void
    {
        if ($row === 5) {
            $sheet->mergeCells('A5:'.$ultimaCol.'5')->setCellValue('A5', $vacio);
            $row++;
        }
        $ultimaFila = $row - 1;
        if ($columnasTotales) {
            $sheet->mergeCells('A'.$row.':'.chr(ord($columnasTotales[0]) - 1).$row)->setCellValue('A'.$row, 'Total');
            foreach ($columnasTotales as $columna) {
                $sheet->setCellValue($columna.$row, '=SUM('.$columna.'5:'.$columna.$ultimaFila.')');
            }
        }
        $tabla = $sheet->getStyle('A4:'.$ultimaCol.($columnasTotales ? $row : $ultimaFila));
        $tabla->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DEE6F4');
        $tabla->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('424C61');
        foreach ($columnasTotales ? [4, $row] : [4] as $filaDestacada) {
            $style = $sheet->getStyle('A'.$filaDestacada.':'.$ultimaCol.$filaDestacada);
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6D3E8');
            $style->getFont()->setBold(true);
        }
        foreach ($anchos + ['A' => 8, 'B' => 13, 'C' => 61] as $col => $ancho) {
            $sheet->getColumnDimension($col)->setWidth($ancho);
        }
        $sheet->setAutoFilter('A4:'.$ultimaCol.$ultimaFila);
        $sheet->freezePane('A5');
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(4, 4);
    }
}

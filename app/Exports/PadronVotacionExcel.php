<?php

namespace App\Exports;

use App\Models\JornadaVotacion;
use App\Services\Votaciones\ActualizarVotantesExcel;
use App\Services\Votaciones\PadronConsultaExcel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PadronVotacionExcel
{
    public function plantilla(JornadaVotacion $jornada, bool $actualizacion, array $campos, bool $precargar): Spreadsheet
    {
        $book = new Spreadsheet;
        $headers = $actualizacion ? ActualizarVotantesExcel::columnas($campos) : PadronConsultaExcel::PADRON;
        $sheet = $book->getActiveSheet()->setTitle($actualizacion ? 'ACTUALIZAR PADRON' : 'PADRON VOTACIÓN');
        $this->encabezado($sheet, $headers);
        $locales = $jornada->localesConsulta()->orderBy('rbd_origen')->orderBy('rbd_local')->get();
        if ($actualizacion && $precargar) {
            $porId = $locales->keyBy('id');
            $row = 2;
            foreach ($jornada->padronConsulta()->orderBy('id')->cursor() as $voter) {
                $values = ['ID_REGISTRO' => $voter->id, 'RUT' => $voter->rut, 'NOMBRE' => $voter->nombre,
                    'TIPO_DE_CONTRATO' => $voter->tipo_contrato, 'GRUPO' => $voter->grupo,
                    'RBD' => $voter->rbd, 'RBD_LUGAR_DE_VOTACION' => $porId->get($voter->local_consulta_id)?->rbd_local];
                foreach ($headers as $i => $header) { $sheet->setCellValueExplicit([$i + 1, $row], (string) ($values[$header] ?? ''), DataType::TYPE_STRING); }
                $row++;
            }
        }
        $localSheet = $book->createSheet()->setTitle('LOCALES VOTACION');
        $this->encabezado($localSheet, PadronConsultaExcel::LOCALES);
        foreach ($locales as $i => $local) {
            foreach ([$local->rbd_origen, $local->establecimiento, $local->rbd_local, $local->lugar_votacion, substr($local->hora_inicio, 0, 5), $local->dia->format('Y-m-d')] as $j => $value) {
                $localSheet->setCellValueExplicit([$j + 1, $i + 2], (string) $value, DataType::TYPE_STRING);
            }
        }
        $help = $book->createSheet()->setTitle('INSTRUCCIONES');
        $this->encabezado($help, ['INDICACIÓN', 'DETALLE']);
        $instructions = [
            ['Jornada', $jornada->nombre],
            ['Operación', $actualizacion ? 'Actualiza sólo las filas del archivo y los campos seleccionados; conserva a los demás votantes. Puede agregar RUT nuevos si habilita esa opción.' : 'Importación completa: sustituye el padrón y los locales de la jornada al confirmar su reemplazo.'],
            ['Identificación', 'RUT completo con DV correcto, con o sin puntos/guion. No modifique ID_REGISTRO ni el RUT de filas precargadas. Para nuevos votantes, deje ID_REGISTRO vacío.'],
            ['Varias asignaciones', 'Use ID_REGISTRO para elegir una asignación concreta. Sin ID sólo se actualiza si existe una única asignación para ese RUT en esta jornada.'],
            ['Campos', 'Sólo se leen los campos seleccionados al cargar. Descargue otra plantilla si cambia la selección. Grupo vacío elimina el grupo; los demás campos seleccionados son obligatorios.'],
            ['Nuevos votantes', 'Requieren Nombre, Tipo de contrato y RBD de origen/local. Seleccione esos tres campos al actualizar. Grupo puede quedar vacío. No se crean locales con la actualización parcial.'],
            ['Locales', $actualizacion ? 'Hoja de referencia: no se importa al actualizar votantes. El día, hora y nombres proceden de los locales ya cargados en la jornada.' : 'Debe existir una combinación RBD + RBD_LUGAR_DE_VOTACION por cada asignación del padrón. DIA: AAAA-MM-DD. HORARIO: HH:MM.'],
            ['Filas y publicación', 'Conserve sólo las filas que desea tratar. No use fórmulas. Máximo 10 MB y 20.000 filas. Los cambios efectivos retiran la consulta pública hasta que la revise y publique otra vez.'],
        ];
        foreach ($instructions as $i => $values) {
            foreach ($values as $j => $value) { $help->setCellValueExplicit([$j + 1, $i + 2], $value, DataType::TYPE_STRING); }
            $help->getRowDimension($i + 2)->setRowHeight(60);
        }
        $help->getColumnDimension('A')->setWidth(25);
        $help->getColumnDimension('B')->setWidth(110);
        $book->setActiveSheetIndex(0);
        return $book;
    }

    public function reporte(array $resultado): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('Resultado actualización');
        $this->encabezado($sheet, ['FILA EXCEL', 'ID_REGISTRO', 'RUT', 'RESULTADO', 'DETALLE']);
        foreach ($resultado['reporte'] as $i => $fila) {
            foreach ([$fila['fila'], $fila['id'], $fila['rut'], $fila['resultado'], $fila['detalle']] as $j => $value) {
                $sheet->setCellValueExplicit([$j + 1, $i + 2], (string) $value, DataType::TYPE_STRING);
            }
        }
        $sheet->getColumnDimension('E')->setWidth(95);
        return $book;
    }

    public function descargar(Spreadsheet $book, string $nombre): StreamedResponse
    {
        return response()->streamDownload(function () use ($book) {
            try { (new Xlsx($book))->save('php://output'); }
            finally { $book->disconnectWorksheets(); }
        }, $nombre.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function encabezado($sheet, array $headers): void
    {
        $last = Coordinate::stringFromColumnIndex(count($headers));
        foreach ($headers as $i => $header) {
            $sheet->setCellValueExplicit([$i + 1, 1], $header, DataType::TYPE_STRING);
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth(32);
        }
        $sheet->getStyle('A1:'.$last.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0B3D91']],
        ]);
        $sheet->getStyle('A:'.$last)->getAlignment()->setWrapText(true)->setVertical('top');
        $sheet->getStyle('A:'.$last)->getNumberFormat()->setFormatCode('@');
        $sheet->getRowDimension(1)->setRowHeight(38);
        $sheet->freezePane('A2');
    }
}

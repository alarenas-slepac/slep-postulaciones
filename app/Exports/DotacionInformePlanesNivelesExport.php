<?php

namespace App\Exports;

use App\Support\DotacionInformePlanesNiveles;
use App\Support\DotacionLecturaCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DotacionInformePlanesNivelesExport
{
    public function __construct(private readonly DotacionInformePlanesNiveles $calculador,
        private readonly DotacionInformePlanesExport $formato) {}

    public function download(Builder $establecimientos, int $anio, array $filters = []): StreamedResponse
    {
        @set_time_limit(240);
        $book = $this->workbook($this->filas($establecimientos, $anio), $anio, $filters);

        return response()->streamDownload(function () use ($book): void {
            try { (new Xlsx($book))->save('php://output'); }
            finally { $book->disconnectWorksheets(); }
        }, 'dotacion_planes_por_niveles_'.$anio.'_'.now()->format('Ymd_His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function filas(Builder $establecimientos, int $anio): \Generator
    {
        foreach ((clone $establecimientos)->lazy(10) as $ee) {
            try {
                $fila = DotacionLecturaCache::ejecutar(fn () => $this->calculador->fila($ee, $anio));
            } catch (\Throwable $exception) {
                report($exception);
                throw ValidationException::withMessages(['export_planes_niveles' => 'No se pudo desglosar el plan del RBD '
                    .$ee->rbd.'. No se generó el Excel; revise el establecimiento y vuelva a descargar.']);
            } finally { $ee->unsetRelations(); }
            yield $fila;
        }
    }

    public function workbook(iterable $filas, int $anio, array $filters = []): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('SGA SLEP Andalién Costa')->setTitle('Planes por niveles de enseñanza '.$anio);
        $titulos = ['global' => 'Global'] + DotacionInformePlanesNiveles::NIVELES;
        $counts = array_fill_keys(array_keys($titulos), 0);
        $sheets = [];
        foreach ($titulos as $key => $titulo) {
            $sheet = $key === 'global' ? $book->getActiveSheet() : $book->createSheet();
            $sheets[$key] = $sheet->setTitle($titulo);
            $nota = $key === 'global'
                ? 'Total completo por establecimiento. Las demás hojas muestran sólo su nivel; Media comprende 1.º y 2.º medio. Horas sin clasificación o compartidas entre niveles se identifican en Sin desglose cuando corresponda.'
                : 'Sólo cursos y horas de '.$titulo.'. Media: 1.º y 2.º medio; HC/TP: modalidades diferenciadas. EPJA y Especial se informan por separado. Los grupos del mismo nivel y sus refuerzos se cuentan una vez.';
            $this->formato->prepararHoja($sheet, $anio, 'PLANES DE ESTUDIO · '.mb_strtoupper($titulo), $nota);
        }
        try {
            foreach ($filas as $fila) {
                $this->formato->escribirFila($sheets['global'], 7 + $counts['global']++, $fila['global']);
                foreach ($fila['niveles'] as $key => $nivel) {
                    if ($key === 'sin_desglose' && ! isset($sheets[$key])) {
                        $sheets[$key] = $book->createSheet()->setTitle('Sin desglose');
                        $counts[$key] = 0;
                        $this->formato->prepararHoja($sheets[$key], $anio, 'PLANES DE ESTUDIO · SIN DESGLOSE',
                            'Incluido en Global. Horas compartidas entre niveles y cursos sin clasificación. En grupos entre niveles, matrícula y cursos se informan en las hojas de sus integrantes, sin repartir sus horas.');
                    }
                    $this->formato->escribirFila($sheets[$key], 7 + $counts[$key]++, $nivel);
                }
            }
            foreach ($sheets as $key => $sheet) {
                $this->formato->finalizarHoja($sheet, $counts[$key], $anio, $filters);
            }
            $book->setActiveSheetIndex(0);

            return $book;
        } catch (\Throwable $exception) {
            $book->disconnectWorksheets();
            throw $exception;
        }
    }
}

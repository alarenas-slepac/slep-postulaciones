<?php

namespace App\Console\Commands;

use App\Models\Mencion;
use App\Models\Subsector;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportMenciones extends Command
{
    protected $signature = 'menciones:import {path : Ruta del archivo Excel (.xlsx)} {--sheet= : Nombre o índice de hoja}';
    protected $description = 'Importa menciones y subsectores desde un Excel (columnas: subsector, nombre, universidad, anio)';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (!is_file($path)) {
            $this->error("Archivo no encontrado: {$path}");
            return self::FAILURE;
        }

        $this->info("Leyendo {$path} ...");
        $spreadsheet = IOFactory::load($path);
        $sheetOpt = $this->option('sheet');

        $sheet = $sheetOpt !== null
            ? (is_numeric($sheetOpt) ? $spreadsheet->getSheet((int)$sheetOpt) : $spreadsheet->getSheetByName($sheetOpt))
            : $spreadsheet->getActiveSheet();

        if (!$sheet) {
            $this->error('Hoja no encontrada.');
            return self::FAILURE;
        }

        // Detecta encabezados en la primera fila
        $rows = $sheet->toArray(null, true, true, true);
        if (empty($rows)) {
            $this->warn('Hoja vacía.');
            return self::SUCCESS;
        }

        $headers = array_map(fn($h) => mb_strtolower(trim((string)$h)), array_values(reset($rows)));
        $idx = [
            'subsector'   => array_search('subsector', $headers, true),
            'nombre'      => array_search('nombre', $headers, true),
            'universidad' => array_search('universidad', $headers, true),
            'anio'        => array_search('año', $headers, true),
        ];

        foreach (['subsector', 'nombre'] as $k) {
            if ($idx[$k] === false) {
                $this->error("Columna requerida no encontrada: {$k}");
                return self::FAILURE;
            }
        }

        $n = 0;
        foreach (array_slice($rows, 1) as $row) {
            $vals = array_values($row);
            $subsectorName = trim((string)($vals[$idx['subsector']] ?? ''));
            $nombre        = trim((string)($vals[$idx['nombre']] ?? ''));
            $universidad   = trim((string)($vals[$idx['universidad']] ?? ''));
            $anioRaw       = $idx['anio'] !== false ? trim((string)($vals[$idx['anio']] ?? '')) : null;

            if ($subsectorName === '' || $nombre === '') continue;

            $anio = null;
            if ($anioRaw !== '' && ctype_digit($anioRaw)) {
                $anio = (int) $anioRaw;
                if ($anio < 1900 || $anio > 2100) $anio = null;
            }

            $subsector = Subsector::firstOrCreate(['subsector' => $subsectorName]);
            Mencion::firstOrCreate([
                'nombre'       => $nombre,
                'universidad'  => $universidad ?: null,
                'anio'         => $anio,
                'subsector_id' => $subsector->id,
            ]);

            $n++;
        }

        $this->info("Importadas {$n} filas.");
        return self::SUCCESS;
    }
}

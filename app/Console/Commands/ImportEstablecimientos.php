<?php

namespace App\Console\Commands;

use App\Services\EstablecimientoImportService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportEstablecimientos extends Command
{
    protected $signature = 'establecimientos:import
        {path : Ruta del archivo Excel (.xlsx)}
        {--sheet= : Nombre o índice de hoja}
        {--truncate : Vacía la tabla antes de importar}';

    protected $description = 'Importa establecimientos desde un Excel con la nómina oficial (encabezados fijos).';

    public function __construct(private readonly EstablecimientoImportService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $sheet = $this->option('sheet');
        $truncate = (bool) $this->option('truncate');

        try {
            $summary = $this->service->importFromPath($path, $sheet, $truncate);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Hoja procesada: ' . $summary['sheet_name']);
        $this->info('Procesadas: ' . $summary['processed']);
        $this->info('Creadas: ' . $summary['created']);
        $this->info('Actualizadas: ' . $summary['updated']);
        $this->info('Omitidas: ' . $summary['skipped']);

        foreach ($summary['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}

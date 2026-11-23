<?php

use App\Jobs\GenerarExcelFinanciamiento;
use App\Models\DotacionFinanciamientoExportacion;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('incidencias:escalar-tickets')->hourly()->withoutOverlapping();

Schedule::command('queue:work '.GenerarExcelFinanciamiento::CONEXION, [
    '--queue' => GenerarExcelFinanciamiento::COLA,
    '--timeout' => GenerarExcelFinanciamiento::TIMEOUT,
    '--tries' => 1,
    '--max-jobs' => 1,
    '--stop-when-empty',
])->everyMinute()->withoutOverlapping(12)->runInBackground()
    ->when(fn () => DotacionFinanciamientoExportacion::disponible());

Schedule::command('model:prune', ['--model' => [DotacionFinanciamientoExportacion::class]])
    ->dailyAt('03:30')->when(fn () => DotacionFinanciamientoExportacion::disponible());

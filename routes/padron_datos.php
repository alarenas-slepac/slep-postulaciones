<?php

use App\Http\Controllers\Reemplazos\PersonalDatosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'ensure.role:admin', 'ensure.active-role:admin', 'ensure.module'])
    ->prefix('reemplazos/personal/actualizar-datos')->name('reemplazos.personal.datos.')
    ->group(function (): void {
        Route::get('/plantilla', [PersonalDatosController::class, 'plantilla'])->name('plantilla');
        Route::post('/', [PersonalDatosController::class, 'actualizar'])->name('actualizar');
        Route::get('/omitidos/{reporte}', [PersonalDatosController::class, 'omitidos'])->whereUuid('reporte')->name('omitidos');
    });

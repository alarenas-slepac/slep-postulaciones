<?php

use App\Http\Controllers\Admin\DotacionFinanciamientoExportacionController;
use App\Support\DotacionInformeFunciones;
use Illuminate\Support\Facades\Route;

$roles = implode('|', DotacionInformeFunciones::ROLES);

Route::prefix('admin/dotacion-establecimiento/financiamiento')->name('admin.dotacion-establecimiento.financiamiento.')
    ->middleware(['auth', 'verified', 'ensure.role:'.$roles, 'ensure.active-role:'.$roles, 'ensure.module'])
    ->controller(DotacionFinanciamientoExportacionController::class)->group(function (): void {
        Route::get('/', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:6,1')->name('store');
        Route::get('/{exportacion}', 'show')->whereUuid('exportacion')->name('show');
        Route::get('/{exportacion}/descargar', 'download')->whereUuid('exportacion')->name('download');
    });

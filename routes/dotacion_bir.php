<?php

use App\Http\Controllers\Admin\DotacionContrataHabilitacionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/dotacion-establecimiento/{establecimiento}/cupos-bir')->whereNumber('establecimiento')
    ->name('admin.dotacion-establecimiento.bir.')
    ->middleware(['auth', 'verified', 'ensure.role:admin|coordinador_uatp|coordinador_gdp|supervisor_plani', 'ensure.module'])
    ->group(function (): void {
        Route::get('/', [DotacionContrataHabilitacionController::class, 'bir'])->name('index');
        Route::post('/', [DotacionContrataHabilitacionController::class, 'generarBir'])->name('store');
    });

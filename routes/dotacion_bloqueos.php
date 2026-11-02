<?php

use App\Http\Controllers\Admin\DotacionEdicionBloqueoController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/dotacion-establecimiento')->name('admin.dotacion-establecimiento.bloqueos.')
    ->middleware(['auth', 'verified', 'ensure.role:admin|supervisor_plani|coordinador_gdp|coordinador_uatp', 'ensure.active-role:admin|supervisor_plani|coordinador_gdp|coordinador_uatp', 'ensure.module'])
    ->group(function (): void {
        Route::put('/bloqueos/general', [DotacionEdicionBloqueoController::class, 'general'])->name('general.update');
        Route::put('/{establecimiento}/bloqueo-edicion', [DotacionEdicionBloqueoController::class, 'establecimiento'])
            ->whereNumber('establecimiento')->name('establecimiento.update');
    });

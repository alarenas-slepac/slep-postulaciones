<?php

use App\Http\Controllers\Admin\DotacionPal2027Controller;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/dotacion-establecimiento')->name('admin.dotacion-establecimiento.')
    ->middleware(['auth', 'verified', 'ensure.role:admin|coordinador_uatp|coordinador_gdp|supervisor_plani', 'ensure.module'])
    ->group(function (): void {
        Route::get('/{establecimiento}/pal-2027', DotacionPal2027Controller::class)
            ->whereNumber('establecimiento')->name('pal-2027');
    });

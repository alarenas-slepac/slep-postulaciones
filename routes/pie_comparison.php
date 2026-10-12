<?php

use App\Http\Controllers\Admin\PieComparisonController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'ensure.role:admin|coordinador_uatp', 'ensure.active-role:admin|coordinador_uatp', 'ensure.module'])
    ->prefix('admin/establecimiento-curso-pie-comparacion')->name('admin.establecimiento-curso-pie.')->group(function () {
        Route::get('/', [PieComparisonController::class, 'index'])->name('comparison');
        Route::put('/', [PieComparisonController::class, 'update'])->name('comparison.update');
    });

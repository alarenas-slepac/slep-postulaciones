<?php

use App\Http\Controllers\PadronIndividualController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'ensure.role:admin', 'ensure.module'])
    ->prefix('reemplazos/padron-individual')->name('reemplazos.individual.')
    ->group(function (): void {
        Route::get('/', [PadronIndividualController::class, 'index'])->name('index');
        Route::post('/', [PadronIndividualController::class, 'store'])->name('store');
    });

<?php

use App\Http\Controllers\Admin\DotacionDisposicionController;
use App\Http\Controllers\Admin\DotacionRevisionController;
use App\Services\Dotacion\DocentesDisposicionService;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/dotacion-establecimiento/disposicion')->name('admin.dotacion-establecimiento.disposicion.')
    ->middleware(['auth', 'verified', 'ensure.role:'.implode('|', DocentesDisposicionService::ROLES),
        'ensure.active-role:'.implode('|', DocentesDisposicionService::ROLES), 'ensure.module'])
    ->controller(DotacionDisposicionController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/gestionar', 'gestionar')->name('gestionar');
        Route::post('/', 'store')->name('store');
        Route::put('/{distribucion}', 'update')->whereNumber('distribucion')->name('update');
        Route::post('/{distribucion}/anular', 'anular')->whereNumber('distribucion')->name('anular');
    });

Route::prefix('admin/dotacion-establecimiento/{establecimiento}/revision')->name('admin.dotacion-establecimiento.revision.')
    ->whereNumber('establecimiento')
    ->middleware(['auth', 'verified', 'ensure.role:'.implode('|', DocentesDisposicionService::ROLES),
        'ensure.active-role:'.implode('|', DocentesDisposicionService::ROLES), 'ensure.module'])->group(function (): void {
        Route::post('/finalizar', [DotacionRevisionController::class, 'finalizar'])->name('finalizar');
        Route::post('/reabrir', [DotacionRevisionController::class, 'reabrir'])->name('reabrir');
    });

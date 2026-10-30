<?php

use App\Http\Controllers\Tramites\MaternidadController;
use Illuminate\Support\Facades\Route;

$rolesMaternidad = implode('|', config('maternidad.roles'));
Route::middleware(['auth', 'verified', 'ensure.role:'.$rolesMaternidad, 'ensure.active-role:'.$rolesMaternidad])
    ->prefix('tramites/maternidad')->name('tramites.maternidad.')->group(function (): void {
        Route::get('/', [MaternidadController::class, 'index'])->name('index');
        Route::get('/buscar-personal', [MaternidadController::class, 'buscar'])->middleware('throttle:60,1')->name('buscar');
        Route::get('/crear', [MaternidadController::class, 'create'])->name('create');
        Route::post('/', [MaternidadController::class, 'store'])->name('store');
        Route::get('/importar', [MaternidadController::class, 'importar'])->name('importar');
        Route::get('/plantilla', [MaternidadController::class, 'plantilla'])->name('plantilla');
        Route::post('/importar', [MaternidadController::class, 'previsualizar'])->name('previsualizar');
        Route::get('/importaciones/{importacion}', [MaternidadController::class, 'revisar'])->whereUuid('importacion')->name('importacion');
        Route::post('/importaciones/{importacion}/confirmar', [MaternidadController::class, 'confirmar'])->whereUuid('importacion')->name('confirmar');
        Route::get('/{caso}', [MaternidadController::class, 'show'])->whereUuid('caso')->name('show');
        Route::get('/{caso}/editar', [MaternidadController::class, 'edit'])->whereUuid('caso')->name('edit');
        Route::put('/{caso}', [MaternidadController::class, 'update'])->whereUuid('caso')->name('update');
        Route::post('/{caso}/observaciones', [MaternidadController::class, 'observar'])->whereUuid('caso')->name('observar');
        Route::get('/{caso}/documentos/{documento}', [MaternidadController::class, 'documento'])->whereUuid('caso')->whereNumber('documento')->name('documento');
    });

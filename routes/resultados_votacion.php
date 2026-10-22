<?php

use App\Http\Controllers\Votaciones\ResultadoDiarioVotacionController;
use App\Http\Middleware\ConsultaVotacionPrivada;
use Illuminate\Support\Facades\Route;

Route::get('resultados-votacion', [ResultadoDiarioVotacionController::class, 'publico'])
    ->middleware([ConsultaVotacionPrivada::class, 'throttle:60,1'])->name('public.resultados-votacion.index');

Route::prefix('gestion/votaciones/admin/resultados')->name('votaciones.admin.resultados.')
    ->middleware(['auth', 'verified', 'ensure.role:admin', 'ensure.module', ConsultaVotacionPrivada::class])
    ->group(function (): void {
        Route::get('/', [ResultadoDiarioVotacionController::class, 'index'])->name('index');
        Route::post('/{jornada:slug}', [ResultadoDiarioVotacionController::class, 'guardar'])->name('guardar');
        Route::patch('/{jornada:slug}/{resultado}/publicacion', [ResultadoDiarioVotacionController::class, 'publicacion'])->name('publicacion');
    });

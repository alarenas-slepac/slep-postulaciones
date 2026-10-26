<?php

use App\Http\Controllers\Votaciones\ConsultaLocalVotacionController;
use App\Http\Controllers\Votaciones\PadronConsultaVotacionController;
use App\Http\Controllers\Votaciones\ActualizarVotantesController;
use App\Http\Middleware\ConsultaVotacionPrivada;
use Illuminate\Support\Facades\Route;

Route::prefix('consulta-local-votacion')->name('public.consulta-votacion.')
    ->middleware([ConsultaVotacionPrivada::class, 'throttle:60,1'])->group(function (): void {
        Route::get('/', [ConsultaLocalVotacionController::class, 'index'])->name('index');
        Route::post('/', [ConsultaLocalVotacionController::class, 'consultar'])->middleware('throttle:15,1,consulta-rut')->name('consultar');
    });

require __DIR__.'/resultados_votacion.php';

Route::prefix('gestion/votaciones/admin/padrones')->name('votaciones.admin.padrones.')
    ->middleware(['auth', 'verified', 'ensure.role:admin', 'ensure.module', ConsultaVotacionPrivada::class])
    ->group(function (): void {
        Route::get('/', [PadronConsultaVotacionController::class, 'index'])->name('index');
        Route::match(['GET', 'POST'], '/{jornada:slug}/plantilla', [ActualizarVotantesController::class, 'plantilla'])->name('plantilla');
        Route::post('/{jornada:slug}/actualizar', [ActualizarVotantesController::class, 'actualizar'])->middleware('throttle:6,1,padron-actualizacion')->name('actualizar');
        Route::post('/{jornada:slug}/importar', [PadronConsultaVotacionController::class, 'importar'])->middleware('throttle:6,1,padron-importacion')->name('importar');
        Route::patch('/{jornada:slug}/publicacion', [PadronConsultaVotacionController::class, 'publicacion'])->name('publicacion');
    });

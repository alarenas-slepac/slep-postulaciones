<?php

use App\Http\Controllers\Votaciones\ConvocadosLocalVotacionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'ensure.module', 'permission:votaciones.manage-jornadas', 'permission:votaciones.manage-rutas'])
    ->put('/gestion/votaciones/admin/rutas/{ruta}/establecimientos', [ConvocadosLocalVotacionController::class, 'update'])
    ->name('votaciones.admin.rutas.convocados.update');

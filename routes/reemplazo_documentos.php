<?php

use App\Http\Controllers\Gestion\ReemplazoDocumentosAcademicosController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'ensure.role:admin', 'ensure.active-role:admin', 'ensure.module'])
    ->prefix('gestion/solicitudes-reemplazo/documentos-academicos')
    ->name('gestion.solicitudes-reemplazo.documentos-academicos.')
    ->group(function (): void {
        // Dos segmentos evitan colisionar con la ruta histórica /{solicitud}.
        Route::get('/resumen', [ReemplazoDocumentosAcademicosController::class, 'index'])->name('index');
        Route::get('/descargar', [ReemplazoDocumentosAcademicosController::class, 'download'])->name('download');
    });

<?php

use App\Http\Controllers\Admin\WhatsAppController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/whatsapp')->name('admin.whatsapp.')
    ->middleware(['auth', 'verified', 'ensure.role:admin', 'permission:whatsapp.manage'])
    ->group(function (): void {
        Route::get('/', [WhatsAppController::class, 'index'])->name('index');
        Route::get('/status', [WhatsAppController::class, 'status'])->middleware('throttle:30,1,whatsapp-status:')->name('status');
        Route::post('/qr', [WhatsAppController::class, 'renewQr'])->middleware('throttle:6,1,whatsapp-qr:')->name('qr');
        Route::post('/unlink', [WhatsAppController::class, 'unlink'])->middleware('throttle:3,1,whatsapp-unlink:')->name('unlink');
        Route::post('/test', [WhatsAppController::class, 'test'])->middleware('throttle:5,1,whatsapp-test:')->name('test');
    });

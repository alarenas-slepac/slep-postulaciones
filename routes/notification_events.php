<?php

use App\Http\Controllers\Admin\NotificationEventController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified', 'ensure.role:admin', 'permission:notifications.manage'])
    ->prefix('admin/eventos-notificaciones')->name('admin.notification-events.')->group(function (): void {
        Route::get('/', [NotificationEventController::class, 'index'])->name('index');
        Route::get('/{event}/editar', [NotificationEventController::class, 'edit'])->name('edit');
        Route::put('/{event}/{channel}', [NotificationEventController::class, 'update'])->name('update');
        Route::match(['POST', 'PUT'], '/{event}/{channel}/preview', [NotificationEventController::class, 'preview'])->middleware('throttle:30,1')->name('preview');
        Route::post('/{event}/{channel}/restablecer', [NotificationEventController::class, 'reset'])->name('reset');
    });

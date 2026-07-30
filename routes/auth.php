<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\RegisterRutLookupController;
use App\Http\Controllers\FuncionarioAcController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Auth Routes (Login, Register, Password, Verification)
|--------------------------------------------------------------------------
*/

// Registro
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::get('/register/lookup-rut', RegisterRutLookupController::class)
        ->middleware('throttle:30,1')
        ->name('register.lookup-rut');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('register.store');

    Route::get('/funcionario-ac/login', [FuncionarioAcController::class, 'loginForm'])->name('funcionario-ac.login');
    Route::get('/registro/funcionario-ac', [FuncionarioAcController::class, 'registerForm'])->name('funcionario-ac.register');
    Route::post('/registro/funcionario-ac', [FuncionarioAcController::class, 'registerStore'])
        ->middleware('throttle:6,1')
        ->name('funcionario-ac.register.store');

    // Login (RUT o email)
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    // Forgot password
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    // Reset password
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

// Logout
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

// Verificación de email
Route::middleware('auth')->group(function () {

    // Vista de aviso
    Route::get('/verify-email', function () {
        return view('auth.verify-email');
    })->name('verification.notice');

    // Enlace de verificación (firmado) -> redirige al dashboard
    Route::get('/verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();
        return redirect()->route('dashboard');
    })->middleware(['signed'])->name('verification.verify');

    // Reenviar enlace (con throttle)
    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }
        $request->user()->sendEmailVerificationNotification();
        return back()->with('status', 'verification-link-sent');
    })->middleware(['throttle:6,1'])->name('verification.send');
});

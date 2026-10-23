<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => ['ok' => true, 'time' => now()])->name('api.ping');

Route::post('/v1/funcionarios/consultar', [\App\Http\Controllers\Funcionarios\ConsultaFuncionarioController::class, 'consultar'])
    ->middleware([
        \App\Http\Middleware\FuncionarioConsultaPrivacy::class,
        'auth:sanctum',
        \App\Http\Middleware\AuthorizeFuncionarioIntegration::class,
        'throttle:funcionario-consulta',
    ])->name('api.v1.funcionarios.consultar');

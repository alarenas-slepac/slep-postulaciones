<?php

use App\Http\Controllers\Admin\FuncionarioApiIntegrationController;
use App\Http\Controllers\Funcionarios\ConsultaFuncionarioController;
use App\Http\Middleware\FuncionarioConsultaPrivacy;
use Illuminate\Support\Facades\Route;

$roles = ['auth', 'verified', 'ensure.active-role:admin|funcionario_estab|funcionario_establecimiento', 'permission:consultar_funcionarios'];
Route::get('/consultas/funcionarios', [ConsultaFuncionarioController::class, 'index'])
    ->middleware($roles)->name('funcionarios.consulta.index');
Route::post('/consultas/funcionarios', [ConsultaFuncionarioController::class, 'consultar'])
    ->middleware([FuncionarioConsultaPrivacy::class, ...$roles, 'throttle:funcionario-consulta'])
    ->name('funcionarios.consulta.consultar');

Route::prefix('admin/integraciones-funcionarios')->name('admin.funcionarios-api.')
    ->middleware(['auth', 'verified', 'ensure.active-role:admin', 'permission:funcionarios_api.manage'])
    ->group(function (): void {
        Route::get('/', [FuncionarioApiIntegrationController::class, 'index'])->name('index');
        Route::post('/', [FuncionarioApiIntegrationController::class, 'store'])->middleware('throttle:6,1,funcionarios-api-create:')->name('store');
        Route::patch('/{integration}', [FuncionarioApiIntegrationController::class, 'update'])->name('update');
        Route::post('/{integration}/token', [FuncionarioApiIntegrationController::class, 'renew'])->middleware('throttle:6,1,funcionarios-api-token:')->name('token');
        Route::delete('/{integration}/token', [FuncionarioApiIntegrationController::class, 'revoke'])->name('revoke');
    });

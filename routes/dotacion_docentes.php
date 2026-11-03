<?php

use App\Exports\DotacionNominaDocentesExport;
use App\Http\Controllers\Admin\DotacionNominaDocentesController;
use Illuminate\Support\Facades\Route;

Route::get('admin/dotacion-establecimiento/{establecimiento}/docentes/nomina.xlsx', DotacionNominaDocentesController::class)
    ->middleware(['auth', 'verified', 'ensure.role:'.implode('|', DotacionNominaDocentesExport::ROLES), 'ensure.module'])
    ->whereNumber('establecimiento')->name('admin.dotacion-establecimiento.docentes.export');

Route::get('admin/dotacion-establecimiento/{establecimiento}/docentes/nomina.pdf', [DotacionNominaDocentesController::class, 'pdf'])
    ->middleware(['auth', 'verified', 'ensure.role:'.implode('|', DotacionNominaDocentesExport::ROLES), 'ensure.module'])
    ->whereNumber('establecimiento')->name('admin.dotacion-establecimiento.docentes.pdf');

Route::get('admin/dotacion-establecimiento/{establecimiento}/sobredotacion/nomina.xlsx', [DotacionNominaDocentesController::class, 'sobredotacion'])
    ->middleware(['auth', 'verified', 'ensure.role:'.implode('|', DotacionNominaDocentesExport::ROLES), 'ensure.module'])
    ->whereNumber('establecimiento')->name('admin.dotacion-establecimiento.sobredotacion.export');

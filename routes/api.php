<?php

use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => ['ok' => true, 'time' => now()])->name('api.ping');

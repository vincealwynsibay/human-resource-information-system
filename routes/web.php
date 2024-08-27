<?php

use App\Http\Controllers\PayrollController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/exportPayroll', [PayrollController::class, 'export']);

require __DIR__ . '/auth.php';

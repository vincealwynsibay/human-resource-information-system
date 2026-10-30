<?php

// The hris web app (Inertia + Vue). Pages load their data from the /api routes with the session cookie.

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    if (!$request->user()) {
        return redirect('/login');
    }
    return redirect($request->user()->hasRole('admin') ? '/dashboard' : '/me/payslips');
});

Route::get('/login', fn () => Inertia::render('Auth/Login'))->middleware('guest');

Route::middleware('auth')->group(function () {
    Route::get('/me/payslips', fn () => Inertia::render('Me/Payslips'));
    Route::get('/me/attendance', fn () => Inertia::render('Me/Attendance'));
    Route::get('/leave', fn () => Inertia::render('Leave'));
    Route::get('/overtime', fn () => Inertia::render('Overtime'));

    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', fn () => Inertia::render('Dashboard'));
        Route::get('/people', fn () => Inertia::render('People/Index'));
        Route::get('/people/{id}', fn (string $id) => Inertia::render('People/Show', ['id' => $id]))->whereNumber('id');
        Route::get('/payroll/runs', fn () => Inertia::render('Payroll/Runs'));
        Route::get('/payroll/runs/{id}', fn (string $id) => Inertia::render('Payroll/RunShow', ['id' => $id]))->whereUuid('id');
        Route::get('/payroll/employees', fn () => Inertia::render('Payroll/Employees'));
        Route::get('/payroll/loans', fn () => Inertia::render('Payroll/Loans'));
        Route::get('/payroll/filings', fn () => Inertia::render('Payroll/Filings'));
        Route::get('/payroll/year-end', fn () => Inertia::render('Payroll/YearEnd'));
    });
});

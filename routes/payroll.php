<?php

// Philippine payroll engine (app/Payroll). Required at the top of api.php, before the older /payroll routes,
// whose /payroll/{id} catch-alls would otherwise answer these paths.

use App\Http\Controllers\Payroll\AttendanceController;
use App\Http\Controllers\Payroll\DashboardController;
use App\Http\Controllers\Payroll\DocumentController;
use App\Http\Controllers\Payroll\FilingController;
use App\Http\Controllers\Payroll\LoanController;
use App\Http\Controllers\Payroll\ProfileController;
use App\Http\Controllers\Payroll\RunController;
use App\Http\Controllers\Payroll\YearEndController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'payroll', 'middleware' => ['auth:sanctum']], function () {
    Route::middleware('role:employee')->get('/my/payslips', [RunController::class, 'mine']);
    Route::middleware('role:employee')->get('/my/attendance', [AttendanceController::class, 'mine']);
    Route::middleware('role:employee')->get('/my/payslips/{payslip}/pdf', [DocumentController::class, 'myPayslip'])->whereNumber('payslip');

    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', DashboardController::class);
        Route::get('/inbox', [DashboardController::class, 'inbox']);
        Route::get('/profiles', [ProfileController::class, 'index']);
        Route::get('/profiles/options', [ProfileController::class, 'options']);
        Route::post('/profiles/import', [ProfileController::class, 'import']);
        Route::get('/profiles/{employeeId}', [ProfileController::class, 'show']);
        Route::put('/profiles/{employeeId}', [ProfileController::class, 'update']);
        Route::delete('/profiles/{employeeId}', [ProfileController::class, 'destroy']);

        Route::get('/runs', [RunController::class, 'index']);
        Route::post('/runs', [RunController::class, 'store']);
        Route::post('/runs/preview', [RunController::class, 'preview']);
        Route::get('/runs/{run}', [RunController::class, 'show']);
        Route::get('/runs/{run}/summary', [RunController::class, 'summary']);
        Route::put('/runs/{run}/recompute', [RunController::class, 'recompute']);
        Route::put('/runs/{run}/employees/{employeeId}/adjustments', [RunController::class, 'adjust']);
        Route::put('/runs/{run}/submit', [RunController::class, 'submit']);
        Route::put('/runs/{run}/approve', [RunController::class, 'approve']);
        Route::put('/runs/{run}/pay', [RunController::class, 'pay']);
        Route::put('/runs/{run}/cancel', [RunController::class, 'cancel']);
        Route::get('/runs/{run}/payslips/pdf', [DocumentController::class, 'runPayslips']);
        Route::get('/runs/{run}/payslips/{employeeId}/pdf', [DocumentController::class, 'runPayslip']);
        Route::get('/runs/{run}/bank', [DocumentController::class, 'bank']);
        Route::get('/runs/{run}/bank/{format}', [DocumentController::class, 'bankDownload']);

        Route::get('/filings/1601c/{year}/{month}', [FilingController::class, 'form1601c'])->whereNumber(['year', 'month']);
        Route::get('/filings/1601c/{year}/{month}/pdf', [FilingController::class, 'form1601cPdf'])->whereNumber(['year', 'month']);

        Route::get('/attendance/{employeeId}', [AttendanceController::class, 'summary']);

        Route::get('/loans', [LoanController::class, 'index']);
        Route::post('/loans', [LoanController::class, 'store']);
        Route::delete('/loans/{id}', [LoanController::class, 'destroy']);

        Route::post('/final-pay/{employeeId}', [YearEndController::class, 'finalPay']);
        Route::get('/year-end/{year}/annual', [YearEndController::class, 'annual'])->whereNumber('year');
        Route::get('/year-end/{year}/2316', [YearEndController::class, 'form2316'])->whereNumber('year');
        Route::post('/year-end/{year}/thirteenth', [YearEndController::class, 'thirteenth'])->whereNumber('year');
        Route::post('/year-end/{year}/adjustment', [YearEndController::class, 'adjustment'])->whereNumber('year');
        Route::get('/year-end/{year}/alphalist', [YearEndController::class, 'alphalist'])->whereNumber('year');
        Route::get('/year-end/{year}/alphalist/download', [YearEndController::class, 'alphalistDownload'])->whereNumber('year');
        Route::post('/alphalist/check', [YearEndController::class, 'check']);
    });
});

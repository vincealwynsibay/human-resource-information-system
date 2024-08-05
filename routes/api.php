<?php

use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmploymentStatusController;
use App\Http\Controllers\JobTitleController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});


Route::group(['prefix' => "user", 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::delete('/{user_id}', [UserController::class, 'destroy']);
});

Route::group(['prefix' => "user", 'middleware' => ['auth:sanctum']], function () {
    Route::put('/', [UserController::class, 'changePassword']);
});

Route::group(['prefix' => 'department', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [DepartmentController::class, 'index']);
    Route::post('/', [DepartmentController::class, 'store']);
    Route::put('/{id}', [DepartmentController::class, 'update']);
    Route::delete('/{id}', [DepartmentController::class, 'destroy']);
});

Route::group(['prefix' => 'employmentstatus', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [EmploymentStatusController::class, 'index']);
    Route::post('/', [EmploymentStatusController::class, 'store']);
    Route::put('/{id}', [EmploymentStatusController::class, 'update']);
    Route::delete('/{id}', [EmploymentStatusController::class, 'destroy']);
});

Route::get('/admin', function (Request $request) {
    return response()->json([
        'message' => 'Admin Page',
        'user' => $request->user()
    ]);
})->middleware(['auth:sanctum', 'role:admin']);


Route::get('/employee', function (Request $request) {
    return response()->json([
        'message' => 'Employee Page',
        'user' => $request->user()
    ]);
})->middleware(['auth:sanctum', 'role:employee']);
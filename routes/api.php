<?php

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
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
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
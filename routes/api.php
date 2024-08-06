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
Route::group(['prefix' => 'jobtitle', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [JobTitleController::class, 'index']);
    Route::post('/', [JobTitleController::class, 'store']);
    Route::put('/{id}', [JobTitleController::class, 'update']);
    Route::delete('/{id}', [JobTitleController::class, 'destroy']);
});

Route::group(['prefix' => 'admin/employee', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [EmployeeController::class, 'index']);
    Route::post('/', [EmployeeController::class, 'store']);
    Route::get('/{id}', [EmployeeController::class, 'show']);
    Route::put('/{id}', [EmployeeController::class, 'update']);
    Route::put('/{id}/image', [EmployeeController::class, 'updateImage']);
    Route::put('/{id}/personalDetails', [EmployeeController::class, 'updatePersonalDetails']);
    Route::put('/{id}/contactDetails', [EmployeeController::class, 'updateContactDetails']);
    Route::put('/{id}/jobDetails', [EmployeeController::class, 'updateJobDetails']);
    Route::delete('/{id}', [EmployeeController::class, 'destroy']);
});

Route::group(['prefix' => '/employee', 'middleware' => ['auth:sanctum', 'role:employee']], function () {
    Route::get('/{id}', [EmployeeController::class, 'show']);
    Route::put('/{id}/image', [EmployeeController::class, 'updateImage']);
    Route::put('/{id}/personalDetails', [EmployeeController::class, 'updatePersonalDetails']);
    Route::put('/{id}/contactDetails', [EmployeeController::class, 'updateContactDetails']);
});


Route::group(['prefix' => 'role', 'middleware' => ['auth:sanctum', 'role:admin']], function () {

    Route::get('/admin', function (Request $request) {
        return response()->json([
            'message' => 'Admin Page',
            'user' => $request->user()
        ]);
    });

});



Route::get('/admin', function (Request $request) {
    return response()->json([
        'message' => 'Admin Page',
        'user' => $request->user()
    ]);
})->middleware(['auth:sanctum', 'role:admin']);


// Route::get('/employee', function (Request $request) {
//     return response()->json([
//         'message' => 'Employee Page',
//         'user' => $request->user()
//     ]);
// })->middleware(['auth:sanctum', 'role:employee']);
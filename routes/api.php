<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DownloadDTRController;
use App\Http\Controllers\EducationController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeEducationController;
use App\Http\Controllers\EmploymentStatusController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\JobTitleController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LeaveEntitlementController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\OvertimeController;
use App\Http\Controllers\PayGradeController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PayrollItemController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\WorkExperienceController;
use App\Http\Controllers\WorkShiftController;
use App\Http\Controllers\EmergencyContactController;
use App\Http\Controllers\WorkWeekController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/dtr', [DownloadDTRController::class, '__invoke']);


Route::group(['prefix' => "user", 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::delete('/{user_id}', [UserController::class, 'destroy']);
});

Route::group(['prefix' => "user", 'middleware' => ['auth:sanctum']], function () {
    Route::put('/', [UserController::class, 'changePassword']);
});



Route::group(['prefix' => 'workweek', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [WorkWeekController::class, 'index']);
    Route::get('/nice', function () {
        return response()->json([
            'message' => 'Work Week'
        ]);
    });
    Route::put('/', [WorkWeekController::class, 'update']);
});


Route::group(['prefix' => 'paygrade', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [PayGradeController::class, 'index']);
    Route::post('/', [PayGradeController::class, 'store']);
    Route::put('/{id}', [PayGradeController::class, 'update']);
    Route::delete('/{id}', [PayGradeController::class, 'destroy']);
});

Route::group(['prefix' => 'department', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [DepartmentController::class, 'index']);
    Route::post('/', [DepartmentController::class, 'store']);
    Route::put('/{id}', [DepartmentController::class, 'update']);
    Route::delete('/{id}', [DepartmentController::class, 'destroy']);
});

Route::group(['prefix' => 'education', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [EducationController::class, 'index']);
    Route::post('/', [EducationController::class, 'store']);
    Route::put('/{id}', [EducationController::class, 'update']);
    Route::delete('/{id}', [EducationController::class, 'destroy']);
});

Route::group(['prefix' => 'holiday', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [HolidayController::class, 'index']);
    Route::post('/', [HolidayController::class, 'store']);
    Route::put('/{id}', [HolidayController::class, 'update']);
    Route::delete('/{id}', [HolidayController::class, 'destroy']);
});

Route::group(['prefix' => 'payroll', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [PayrollController::class, 'index']);
    Route::get('/{employeeId}', [PayrollController::class, 'getEmployeePayroll']);
    Route::post('/{employeeId}', [PayrollController::class, 'store']);
    Route::delete('/{employeeId}', [PayrollController::class, 'destroy']);
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

Route::group(['prefix' => 'workshift', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [WorkShiftController::class, 'index']);
    Route::post('/', [WorkShiftController::class, 'store']);
    Route::put('/{id}', [WorkShiftController::class, 'update']);
    Route::delete('/{id}', [WorkShiftController::class, 'destroy']);
});

Route::group(['prefix' => 'leavetype', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [LeaveTypeController::class, 'index']);
    Route::post('/', [LeaveTypeController::class, 'store']);
    Route::put('/{id}', [LeaveTypeController::class, 'update']);
    Route::delete('/{id}', [LeaveTypeController::class, 'destroy']);
});



Route::group(['prefix' => "admin/payrollitem", 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [PayrollItemController::class, 'index']);
    Route::post('/', [PayrollItemController::class, 'store']);
    Route::put('/{id}', [PayrollItemController::class, 'update']);
    Route::delete('/{id}', [PayrollItemController::class, 'destroy']);
    Route::post('/bonus', [PayrollItemController::class, 'addBonus']);
    Route::post('/deduction', [PayrollItemController::class, 'addDeduction']);
    Route::post('/commission', [PayrollItemController::class, 'addCommission']);
    Route::post('/allowance', [PayrollItemController::class, 'addAllowance']);
});

Route::group(['prefix' => "payrollitem", 'middleware' => ['auth:sanctum', 'role:employee']], function () {
    Route::get('/me', [PayrollItemController::class, 'getUserPayrollItems']);
});

Route::group(['prefix' => "leaveentitlement", 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    // get leave entitlements by user
    Route::get('/', [LeaveEntitlementController::class, 'index']);
    Route::get('/me', [LeaveEntitlementController::class, 'getUserLeaveEntitlements']);
    Route::post('/', [LeaveEntitlementController::class, 'store']);
    Route::post('/storeMany', [LeaveEntitlementController::class, 'storeMany']);
    Route::post('/employees', [LeaveEntitlementController::class, 'assignLeaveEntitlements']);
    Route::put('/{id}', [LeaveEntitlementController::class, 'update']);
    Route::put('/{id}/daysUsed', [LeaveEntitlementController::class, 'updateDaysUsed']);
    Route::delete('/{id}', [LeaveEntitlementController::class, 'destroy']);
});

Route::group(['prefix' => 'leave', 'middleware' => ['auth:sanctum', 'role:employee']], function () {
    // get own leaves
    Route::get('/', [LeaveController::class, 'index']);
    Route::get('/me', [LeaveController::class, 'getOwnLeave']);
    // apply leave
    Route::post('/', [LeaveController::class, 'applyForLeave']);
    // update leave
    Route::put('/{id}', [LeaveController::class, 'update']);
    // delete leave
    Route::delete('/{id}', [LeaveController::class, 'destroy']);
});

Route::group(['prefix' => 'leave', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [LeaveController::class, 'index']);
    Route::post('/approve', [LeaveController::class, 'approveLeave']);
    Route::post('/reject', [LeaveController::class, 'rejectLeave']);
});


Route::group(['prefix' => 'overtime', 'middleware' => ['auth:sanctum', 'role:employee']], function () {
    Route::get('/', [OvertimeController::class, 'index']);
    Route::get('/me', [OvertimeController::class, 'getOwnOvertime']);
    Route::post('/', [OvertimeController::class, 'applyForOvertime']);
    Route::put('/{id}', [OvertimeController::class, 'update']);
    Route::delete('/{id}', [OvertimeController::class, 'destroy']);
});

Route::group(['prefix' => 'overtime', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::post('/approve', [OvertimeController::class, 'approveOvertime']);
    Route::post('/reject', [OvertimeController::class, 'rejectOvertime']);
});

Route::group(['prefix' => 'admin/employee', 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [EmployeeController::class, 'index']);
    Route::post('/', [EmployeeController::class, 'store']);
    Route::get('/{id}', [EmployeeController::class, 'show']);
    Route::put('/{id}', [EmployeeController::class, 'update']);
    Route::put('/{id}/assignSupervisor', [EmployeeController::class, 'assignSupervisor']);
    Route::put('/{id}/removeSupervisor', [EmployeeController::class, 'removeSupervisor']);
    Route::put('/{id}/assignWorkShift', [EmployeeController::class, 'assignWorkShift']);
    Route::put('/{id}/removeWorkShift', [EmployeeController::class, 'removeWorkShift']);

    Route::put('/{id}/contributions', [EmployeeController::class, 'updateContributions']);
    Route::put('/{id}/image', [EmployeeController::class, 'updateImage']);
    Route::put('/{id}/personalDetails', [EmployeeController::class, 'updatePersonalDetails']);
    Route::put('/{id}/contactDetails', [EmployeeController::class, 'updateContactDetails']);
    Route::put('/{id}/jobDetails', [EmployeeController::class, 'updateJobDetails']);
    Route::delete('/{id}', [EmployeeController::class, 'destroy']);
    Route::get('/{employeeId}/attendance', [AttendanceController::class, 'getEmployeeAttendanceRecords']);
    Route::get('/{employeeId}/attendanceSummary', [AttendanceController::class, 'getAttendanceEmployeeSummary']);

    Route::get('/{id}/salary', [SalaryController::class, 'getSalaryByEmployee']);
    Route::post('/{id}/salary', [SalaryController::class, 'store']);
    Route::put('/{id}/salary/{salaryId}', [SalaryController::class, 'update']);
    Route::delete('/{id}/salary/{salaryId}', [SalaryController::class, 'destroy']);

});

// Route::group(['prefix' => 'supervisor/employee', 'middleware' => ['auth:sanctum', 'role:supervisor']], function () {
//     Route::get('/{id}', [EmployeeController::class, 'show']);
//     Route::put('/{id}', [EmployeeController::class, 'update']);
//     Route::put('/{id}/image', [EmployeeController::class, 'updateImage']);
//     Route::put('/{id}/personalDetails', [EmployeeController::class, 'updatePersonalDetails']);
//     Route::put('/{id}/contactDetails', [EmployeeController::class, 'updateContactDetails']);
//     Route::put('/{id}/jobDetails', [EmployeeController::class, 'updateJobDetails']);
//     Route::delete('/{id}', [EmployeeController::class, 'destroy']);
// });


Route::group(['prefix' => '/employee', 'middleware' => ['auth:sanctum', 'role:employee']], function () {
    Route::get('/', [EmployeeController::class, 'index']);
    Route::get('/{id}', [EmployeeController::class, 'show']);
    Route::put('/{id}/image', [EmployeeController::class, 'updateImage']);
    Route::put('/{id}/personalDetails', [EmployeeController::class, 'updatePersonalDetails']);
    Route::put('/{id}/contactDetails', [EmployeeController::class, 'updateContactDetails']);

    Route::get('/{id}/emergencyContacts', [EmergencyContactController::class, 'index']);
    Route::post('/{id}/emergencyContacts', [EmergencyContactController::class, 'store']);
    Route::put('/{id}/emergencyContacts/{emergencyContactId}', [EmergencyContactController::class, 'update']);
    Route::delete('/{id}/emergencyContacts/{emergencyContactId}', [EmergencyContactController::class, 'destroy']);

    Route::get('/{id}/workExperiences', [WorkExperienceController::class, 'index']);
    Route::post('/{id}/workExperiences', [WorkExperienceController::class, 'store']);
    Route::put('/{id}/workExperiences/{workExperienceId}', [WorkExperienceController::class, 'update']);
    Route::delete('/{id}/workExperiences/{workExperienceId}', [WorkExperienceController::class, 'destroy']);

    Route::get('/{id}/education', [EmployeeEducationController::class, 'index']);
    Route::post('/{id}/education', [EmployeeEducationController::class, 'store']);
    Route::put('/{id}/education/{educationId}', [EmployeeEducationController::class, 'update']);
    Route::delete('/{id}/education/{educationId}', [EmployeeEducationController::class, 'destroy']);

    Route::get('/{id}/certificates', [CertificateController::class, 'index']);
    Route::post('/{id}/certificates', [CertificateController::class, 'store']);
    Route::put('/{id}/certificates/{certificateId}', [CertificateController::class, 'update']);
    Route::delete('/{id}/certificates/{certificateId}', [CertificateController::class, 'destroy']);
    Route::get('/{employeeId}/attendance', [AttendanceController::class, 'getEmployeeAttendanceRecords']);
    Route::get('/{employeeId}/attendanceSummary', [AttendanceController::class, 'getAttendanceEmployeeSummary']);

    Route::get('/{employeeId}/downloadDtr', [DownloadDTRController::class, '__invoke']);


    // salary
    Route::get('/{id}/salary', [SalaryController::class, 'getSalaryByEmployee']);
});


Route::group(['prefix' => "/admin/attendance", 'middleware' => ['auth:sanctum', 'role:admin']], function () {
    Route::get('/', [AttendanceController::class, 'index']);
    Route::get('/{id}', [AttendanceController::class, 'show']);
    Route::put('/{id}', [AttendanceController::class, 'update']);
    Route::delete('/{id}', [AttendanceController::class, 'destroy']);
});


Route::group(['prefix' => "/employee/attendance", 'middleware' => ['auth:sanctum', 'role:employee']], function () {
    Route::get('/me', [AttendanceController::class, 'getOwnAttendanceRecords']);
    Route::get('/{id}', [AttendanceController::class, 'show']);
    Route::post('/', [AttendanceController::class, 'attendance']);
    Route::post('/timeIn', [AttendanceController::class, 'timeIn']);
    Route::post('/timeOut', [AttendanceController::class, 'timeOut']);
    Route::put('/{id}', [AttendanceController::class, 'update']);
    Route::delete('/{id}', [AttendanceController::class, 'destroy']);
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
<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Payroll\Holidays;
use App\Payroll\HrAttendance;
use App\Payroll\PayrollService;
use Illuminate\Http\Request;

/** Attendance as payroll counts it, for any date range: what a run with "use time records" would take. */
class AttendanceController extends Controller
{
    public function summary(Request $request, string $employeeId)
    {
        $q = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        if (!HrAttendance::available()) {
            return response()->json(['message' => 'There are no HR time records in this database.'], 404);
        }
        $profile = PayrollService::profiles([$employeeId])[0] ?? ['id' => $employeeId, 'payBasis' => 'monthly'];
        $period = ['start' => $q['from'], 'end' => $q['to']];
        $out = HrAttendance::forPeriod([$profile], $period, Holidays::calendar((int) substr($q['from'], 0, 4)))[$employeeId] ?? null;
        return response()->json(['employeeId' => $employeeId, 'period' => $period, 'summary' => $out['summary'] ?? null, 'workLogs' => $out['input']['workLogs'] ?? []]);
    }

    public function mine(Request $request)
    {
        return $this->summary($request, (string) $request->user()->EmployeeID);
    }
}

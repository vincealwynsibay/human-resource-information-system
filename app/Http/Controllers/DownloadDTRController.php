<?php

namespace App\Http\Controllers;

use App\Enum\DayLengthEnum;
use App\Enum\LeaveRequestEnum;
use App\Services\AttendanceReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelPdf\Facades\Pdf;
use function Spatie\LaravelPdf\Support\pdf;

class DownloadDTRController extends Controller
{
    public function __invoke(Request $request, AttendanceReportService $attendanceReportService, $employee_id)
    {
        $employee = DB::select('CALL procGetEmployee(?)', [$employee_id])[0] ?? null;
        if ($employee == null) {
            return response()->json([
                'message' => ['Employee does not exist']
            ], 404);
        }

        $attendance = $this->generateDTR($attendanceReportService, $employee_id);

        return Pdf::view('pdf.dtr', [
            'employee' => $employee,
            'month' => Date::now()->format('F'),
            'year' => Date::now()->format('Y'),
            'attendances' => $attendance['attendances'],
            'total_work_duration' => $attendance['total_work_duration'],
            'total_overtime_duration' => $attendance['total_overtime_duration'],
            'total_undertime_duration' => $attendance['total_undertime_duration'],
            'total_late_duration' => $attendance['total_late_duration']
        ])
            ->download('dtr-' . Date::today()->format('Y-m-d'));
    }

    public function generateDTR(AttendanceReportService $attendanceReportService, $employee_id)
    {
        // get all attendance records within the month
        $employee = DB::select('CALL procGetEmployee(?)', [$employee_id])[0] ?? null;
        if ($employee == null) {
            return response()->json([
                'message' => ['Employee does not exist']
            ], 404);
        }

        $attendances = DB::select('CALL procGetEmployeeAttendanceByDateRange(?, ?, ?)', [
            $employee_id,
            Date::now()->startOfMonth(),
            Date::now()->endOfMonth()
        ]);
        $employeeWorkShift = DB::select('CALL procGetEmployeeWorkShifts(?)', [$employee->id])[0] ?? null;
        $workShift = DB::select('CALL procGetWorkShift(?)', [$employeeWorkShift->WorkShiftID])[0] ?? null;

        if ($workShift == null) {
            return response()->json([
                'message' => ['Employee does not have a work shift']
            ], 404);
        }

        $attendanceSummary = $attendanceReportService->getAttendanceSummary($employee->id, $attendances, $workShift, Date::now()->startOfMonth(), Date::now()->endOfMonth());
        return [
            "attendances" => $attendanceSummary['attendances'],
            "total_work_duration" => $attendanceSummary['totalWorkDuration'],
            "total_overtime_duration" => $attendanceSummary['totalOvertimeDuration'],
            "total_undertime_duration" => $attendanceSummary['totalUndertimeDuration'],
            "total_late_duration" => $attendanceSummary['totalLateDuration']
        ];
    }
}

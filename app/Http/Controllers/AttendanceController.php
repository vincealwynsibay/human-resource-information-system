<?php

namespace App\Http\Controllers;

use App\Enum\DayLengthEnum;
use App\Enum\LeaveRequestEnum;
use App\Enum\OvertimeRequestEnum;
use App\Enum\RoleEnum;
use App\Services\AttendanceReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            "name" => "nullable|string|max:200",
            "id" => "nullable|string",
            'fromDate' => 'required|date',
            'toDate' => 'required|date',
        ]);

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            $attendances = DB::select('CALL procGetEmployeeAttendancesAsSupervisor(?, ?, ?, ?, ?)', [
                $request->user()->EmployeeID,
                $request->name,
                $request->id,
                $request->fromDate,
                $request->toDate
            ]);
        } else {
            $attendances = DB::select('CALL procGetEmployeeAttendancesAsAdmin(?, ?, ?)', [
                $request->id,
                $request->fromDate,
                $request->toDate
            ]);
        }

        return response()->json($attendances);
    }

    public function getOwnAttendanceRecords(Request $request)
    {
        $validated = $request->validate([
            'fromDate' => 'required|date',
            'toDate' => 'required|date',
        ]);

        $attendances = DB::select('CALL procGetEmployeeAttendancesAsEmployee(?, ?)', [
            $request->user()->EmployeeID,
            $validated['fromDate'],
            $validated['toDate']
        ]);

        return response()->json($attendances);
    }

    public function getEmployeeAttendanceRecords(Request $request, AttendanceReportService $attendanceReportService, $employeeId)
    {
        $validated = $request->validate([
            "range" => 'nullable|string|in:TODAY,WEEK,MONTH,HALFYEAR,YEAR,ALL',
        ]);

        $employee = DB::select('CALL procGetEmployee(?)', [$employeeId])[0] ?? null;
        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employee->id
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($request->range === "ALL") {
            $attendances = DB::select('CALL procGetEmployeeAttendanceByDateRange(?, ?, ?)', [
                $employeeId,
                null,
                null
            ]);
        } else if ($request->range === "TODAY") {
            $attendances = DB::select('CALL procGetEmployeeAttendanceByDate(?, ?)', [
                $employeeId,
                Date::today()
            ]);
        } else if ($request->range === "WEEK") {
            $attendances = DB::select('CALL procGetEmployeeAttendanceByDateRange(?, ?, ?)', [
                $employeeId,
                Date::now()->startOfWeek(),
                Date::today()
            ]);
        } else if ($request->range === "MONTH") {
            $attendances = DB::select('CALL procGetEmployeeAttendanceByDateRange(?, ?, ?)', [
                $employeeId,
                Date::now()->startOfMonth(),
                Date::today()
            ]);
        } else if ($request->range === "HALFYEAR") {
            $attendances = DB::select('CALL procGetEmployeeAttendanceByDateRange(?, ?, ?)', [
                $employeeId,
                Date::now()->subMonths(6),
                Date::today()
            ]);
        } else if ($request->range === "YEAR") {
            $attendances = DB::select('CALL procGetEmployeeAttendanceByDateRange(?, ?, ?)', [
                $employeeId,
                Date::now()->startOfYear(),
                Date::today()
            ]);
        } else {
            return response()->json(['message' => 'Invalid range'], 400);
        }

        $employeeWorkShift = DB::select('CALL procGetEmployeeWorkShifts(?)', [$employee->id])[0] ?? null;
        $workShift = DB::select('CALL procGetWorkShift(?)', [$employeeWorkShift->WorkShiftID])[0] ?? null;
        $employeeAttendances = $attendanceReportService->getAllEmployeeAttendance($employee->id, $attendances, $workShift);

        return response()->json([
            'attendances' => $employeeAttendances
        ]);
    }

    public function getAttendanceEmployeeSummary(Request $request, AttendanceReportService $attendanceReportService, $employeeId)
    {
        $validated = $request->validate([
            "range" => 'nullable|string|in:TODAY,WEEK,MONTH,HALFYEAR,YEAR,ALL',
        ]);

        $employee = DB::select('CALL procGetEmployee(?)', [$employeeId])[0] ?? null;
        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employee->id
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($request->range === "ALL") {
            $attendances = DB::select('CALL procGetEmployeeAttendanceByDateRange(?, ?, ?)', [
                $employeeId,
                null,
                null
            ]);
        } else if ($request->range === "TODAY") {
            $attendances = DB::select('CALL procGetEmployeeAttendanceByDate(?, ?)', [
                $employeeId,
                Date::today()
            ]);
        } else if ($request->range === "WEEK") {
            $attendances = DB::select('CALL procGetEmployeeAttendanceByDateRange(?, ?, ?)', [
                $employeeId,
                Date::now()->startOfWeek(),
                Date::today()
            ]);
        } else if ($request->range === "MONTH") {
            $attendances = DB::select('CALL procGetEmployeeAttendanceByDateRange(?, ?, ?)', [
                $employeeId,
                Date::now()->startOfMonth(),
                Date::today()
            ]);
        } else if ($request->range === "HALFYEAR") {
            $attendances = DB::select('CALL procGetEmployeeAttendanceByDateRange(?, ?, ?)', [
                $employeeId,
                Date::now()->subMonths(6),
                Date::today()
            ]);
        } else if ($request->range === "YEAR") {
            $attendances = DB::select('CALL procGetEmployeeAttendanceByDateRange(?, ?, ?)', [
                $employeeId,
                Date::now()->startOfYear(),
                Date::today()
            ]);
        } else {
            return response()->json(['message' => 'Invalid range'], 400);
        }

        $employeeWorkShift = DB::select('CALL procGetEmployeeWorkShifts(?)', [$employee->id])[0] ?? null;
        $workShift = DB::select('CALL procGetWorkShift(?)', [$employeeWorkShift->WorkShiftID])[0] ?? null;
        $employeeAttendanceSummary = $attendanceReportService->getAttendanceSummary($employee->id, $attendances, $workShift, Date::now()->startOfMonth(), Date::now());

        return response()->json($employeeAttendanceSummary);
    }

    public function attendance(Request $request)
    {
        $user = $request->user();
        $today = DB::select('CALL procGetEmployeeAttendanceByDate(?, ?)', [
            $user->EmployeeID,
            Date::today()
        ]);

        if (empty($today)) {
            DB::select('CALL procInsertEmployeeAttendance(?, ?)', [
                $user->EmployeeID,
                Date::today()
            ]);
            $today = DB::select('CALL procGetEmployeeAttendanceByDate(?, ?)', [
                $user->EmployeeID,
                Date::today()
            ])[0];
        }

        $employeeWorkShift = DB::select('CALL procGetEmployeeWorkShifts(?)', [$user->EmployeeID])[0] ?? null;
        $workShift = DB::select('CALL procGetWorkShift(?)', [$employeeWorkShift->WorkShiftID])[0] ?? null;
        $currentDate = Date::now();
        $overtime = DB::select('CALL procGetEmployeeOvertimeByDate(?, ?, ?)', [
            $user->EmployeeID,
            $currentDate,
            OvertimeRequestEnum::APPROVED->value
        ])[0] ?? null;

        if ($currentDate > $workShift->start_time2 && $overtime != null) {
            if ($today->time_in_overtime == null) {
                DB::select('CALL procUpdateTimeInOvertime(?, ?, ?)', [
                    $user->EmployeeID,
                    Date::today(),
                    $currentDate
                ]);
            } else {
                DB::select('CALL procUpdateTimeOutOvertime(?, ?, ?)', [
                    $user->EmployeeID,
                    Date::today(),
                    $currentDate
                ]);

            }
        } else if ($currentDate <= $workShift->start_time2) {
            if ($today->time_in_session1 == null) {
                // if not timed in yet
                DB::select('CALL procUpdateTimeInSession1(?, ?, ?)', [
                    $user->EmployeeID,
                    Date::today(),
                    $currentDate
                ]);
            } else {
                // if already timed in
                DB::select('CALL procUpdateTimeOutSession1(?, ?, ?)', [
                    $user->EmployeeID,
                    Date::today(),
                    $currentDate
                ]);
            }
        } else if ($currentDate > $workShift->start_time2) {
            if ($today->time_in_session2 == null) {
                // if not timed in yet
                DB::select('CALL procUpdateTimeInSession2(?, ?, ?)', [
                    $user->EmployeeID,
                    Date::today(),
                    $currentDate
                ]);
            } else {
                // if already timed in
                DB::select('CALL procUpdateTimeOutSession2(?, ?, ?)', [
                    $user->EmployeeID,
                    Date::today(),
                    $currentDate
                ]);
            }
        }

        return response()->json([
            'Message' => "Time in successful"
        ]);
    }

    public function timeIn(Request $request)
    {
        $user = $request->user();
        $today = DB::select('CALL procGetEmployeeAttendanceByDate(?, ?)', [
            $user->EmployeeID,
            Date::today()
        ]);

        if (empty($today)) {
            DB::select('CALL procInsertEmployeeAttendance(?, ?)', [
                $user->EmployeeID,
                Date::today()
            ]);
            $today = DB::select('CALL procGetEmployeeAttendanceByDate(?, ?)', [
                $user->EmployeeID,
                Date::today()
            ])[0];
        }

        $employeeWorkShift = DB::select('CALL procGetEmployeeWorkShifts(?)', [$user->EmployeeID])[0] ?? null;
        $workShift = DB::select('CALL procGetWorkShift(?)', [$employeeWorkShift->WorkShiftID])[0] ?? null;

        if ($workShift == null) {
            return response()->json([
                'errors' => [
                    'WorkShift' => ['Employee does not have a work shift']
                ]
            ], 404);
        }

        $currentDate = Date::now();
        $overtime = DB::select('CALL procGetEmployeeOvertimeByDate(?, ?, ?)', [
            $user->EmployeeID,
            $currentDate,
            OvertimeRequestEnum::APPROVED->value

        ])[0] ?? null;

        if ($workShift->start_time2 == null) {
            if ($workShift->start_time1 != null && $currentDate >= $workShift->start_time1 && $currentDate <= Date::now()->setHour(23)->setMinute(59)->setSecond(59) && $overtime != null) {
                DB::select('CALL procUpdateTimeInOvertime(?, ?, ?)', [
                    $user->EmployeeID,
                    Date::today(),
                    $currentDate
                ]);
            } else if ($workShift->start_time1 != null && $currentDate <= Date::now()->setHour(23)->setMinute(59)->setSecond(59)) {
                DB::select('CALL procUpdateTimeInSession1(?, ?, ?)', [
                    $user->EmployeeID,
                    Date::today(),
                    $currentDate
                ]);
            }
        } else {
            if ($workShift->start_time2 != null && $currentDate > $workShift->start_time2 && $overtime != null) {
                // check if after end time and has overtime
                DB::select('CALL procUpdateTimeInOvertime(?, ?, ?)', [
                    $user->EmployeeID,
                    Date::today(),
                    $currentDate
                ]);
            } else if ($workShift->start_time2 != null && $currentDate <= $workShift->start_time2) {
                // time in/time out before start of second segment (ex. needs to time in/time out before end of lunch break)
                DB::select('CALL procUpdateTimeInSession1(?, ?, ?)', [
                    $user->EmployeeID,
                    Date::today(),
                    $currentDate
                ]);

            } else if ($currentDate >= $workShift->start_time2 && $currentDate <= Date::now()->setHour(23)->setMinute(59)->setSecond(59)) {
                // time in/time out after start of second segment
                DB::select('CALL procUpdateTimeInSession2(?, ?, ?)', [
                    $user->EmployeeID,
                    Date::today(),
                    $currentDate
                ]);
            }
        }

        return response()->json([
            'Message' => "Time in successful",
            201
        ]);
    }

    public function timeOut(Request $request)
    {
        $user = $request->user();
        $today = DB::select('CALL procGetEmployeeAttendanceByDate(?, ?)', [
            $user->EmployeeID,
            Date::today()
        ]);
        $currentDate = Date::now();
        $employeeWorkShift = DB::select('CALL procGetEmployeeWorkShifts(?)', [$user->EmployeeID])[0] ?? null;
        $workShift = DB::select('CALL procGetWorkShift(?)', [$employeeWorkShift->WorkShiftID])[0] ?? null;
        $overtime = DB::select('CALL procGetEmployeeOvertimeByDate(?, ?, ?)', [
            $user->EmployeeID,
            $currentDate->subDay(),
            OvertimeRequestEnum::APPROVED->value
        ])[0] ?? null;

        if ($overtime != null) {
            $yesterdayAttendance = DB::select('CALL procGetEmployeeAttendanceByDate(?, ?)', [
                $user->EmployeeID,
                Date::yesterday()
            ])[0];

            if ($yesterdayAttendance != null && $yesterdayAttendance->time_out_overtime == null) { {
                    DB::select('CALL procUpdateTimeOutOvertime(?, ?, ?)', [
                        $user->EmployeeID,
                        Date::today(),
                        $currentDate
                    ]);
                }

            } else if (empty($today)) {
                return response()->json([
                    'errors' => [
                        'Attendance' => ['No attendance record for today']
                    ]
                ], 404);
            }

            if ($workShift->start_time2 == null) {
                if ($currentDate > $workShift->start_time1 && $overtime != null) {
                    DB::select('CALL procUpdateTimeOutOvertime(?, ?, ?)', [
                        $user->EmployeeID,
                        Date::today(),
                        $currentDate
                    ]);
                } else if ($currentDate <= $workShift->start_time1) {
                    DB::select('CALL procUpdateTimeOutSession1(?, ?, ?)', [
                        $user->EmployeeID,
                        Date::today(),
                        $currentDate
                    ]);
                }
            } else {
                if ($currentDate > $workShift->start_time2 && $overtime != null) {
                    DB::select('CALL procUpdateTimeOutOvertime(?, ?, ?)', [
                        $user->EmployeeID,
                        Date::today(),
                        $currentDate
                    ]);
                } else if ($currentDate <= $workShift->start_time2) {
                    DB::select('CALL procUpdateTimeOutSession1(?, ?, ?)', [
                        $user->EmployeeID,
                        Date::today(),
                        $currentDate
                    ]);
                } else if ($currentDate > $workShift->start_time2) {
                    DB::select('CALL procUpdateTimeOutSession2(?, ?, ?)', [
                        $user->EmployeeID,
                        Date::today(),
                        $currentDate
                    ]);
                }
            }

            return response()->json([
                'Message' => "Time out successful",
                201
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $attendance = DB::select('CALL procGetEmployeeAttendanceByID(?)', [$id])[0] ?? null;

        if ($attendance == null) {
            return response()->json(['message' => 'Attendance not found'], 404);
        }

        return response()->json([
            'Attendance' => $attendance
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            "time_in_session1" => "nullable|date",
            "time_out_session1" => "nullable|date",
            "time_in_session2" => "nullable|date",
            "time_out_session2" => "nullable|date",
            "time_in_overtime" => "nullable|date",
            "time_out_overtime" => "nullable|date",
        ]);

        $attendance = DB::select('CALL procGetEmployeeAttendanceByID(?)', [$id])[0] ?? null;
        $employee = DB::select('CALL procGetEmployee(?)', [$attendance->EmployeeID])[0] ?? null;
        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employee->id
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employee->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($attendance == null) {
            return response()->json(['message' => 'Attendance not found'], 404);
        }


        DB::select('CALL procUpdateEmployeeAttendanceByID(?, ?, ?, ?, ?, ?, ?)', [
            $id,
            $request->time_in_session1,
            $request->time_out_session1,
            $request->time_in_session2,
            $request->time_out_session2,
            $request->time_in_overtime,
            $request->time_out_overtime
        ]);

        return response()->json(['message' => 'Attendance updated successfully'], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $attendance = DB::select('CALL procGetEmployeeAttendanceByID(?)', [$id])[0] ?? null;

        if ($attendance == null) {
            return response()->json(['message' => 'Attendance not found'], 404);
        }

        DB::select('CALL procDeleteEmployeeAttendanceByID(?)', [$id]);

        return response()->json(['message' => 'Attendance deleted successfully'], 201);
    }

    protected function getEmployeeAbsencesByDateRange($employeeID, $startDate, $endDate)
    {
        // date range
        $attendances = DB::select("CALL procGetEmployeeAttendanceByDateRange(?, ?, ?)", [$employeeID, $startDate, $endDate]);
        // get days with no attendance record
        $absences = [];
        $left = 0;
        $right = 1;

        while ($right < count($attendances)) {
            $leftDate = $attendances[$left];
            $rightDate = $attendances[$right];


            // compare if there is a gap between attendance records (later date - earlier date > 1), this means that the gap could be an absence.
            if (Date::createFromFormat("Y-m-d", $rightDate->date)->diffInDays(Date::createFromFormat("Y-m-d", $leftDate->date)) > 1) {
                // start from the day after the earlier attendance record
                $date = Date::parse($leftDate->date)->addDay();

                // get all dates between the two attendance records gap
                while ($date->lt(Date::parse($rightDate->date))) {
                    $currentDate = $date->format('Y-m-d');

                    // check if the day is not a work day, if it is, do not record as absence
                    $day = strtolower(Date::parse($currentDate)->format('D'));
                    $workWeek = DB::select('CALL procGetWorkWeek()')[0];
                    $dayLength = $workWeek->$day;

                    if (DayLengthEnum::from($dayLength)->isFullDay()) {
                        $date = $date->addDay();
                        continue;
                    }

                    // check if there is a holiday on the date, if there is, do not record as absence
                    $holiday = DB::select('CALL procGetHolidayByDate(?)', [$currentDate])[0] ?? null;

                    $recurringHoliday = DB::select('CALL procGetRecurringHolidays(?, ?)', [Date::parse($currentDate)->format('m'), Date::parse($currentDate)->format('d')])[0] ?? null;


                    if ($holiday != null || $recurringHoliday) {
                        $date = $date->addDay();
                        continue;
                    }

                    // check if there is a leave record on the date, if there is, do not record as absence
                    $leave = DB::table('tblleave')->where('status', LeaveRequestEnum::APPROVED->value)->where('EmployeeID', $employeeID)->where(function ($query) use ($currentDate) {
                        $query->where('start_date', "<=", $currentDate);
                        $query->where('end_date', ">=", $currentDate);
                    })->first();

                    if ($leave != null) {
                        $date = $date->addDay();
                        continue;
                    }

                    // if no work day, holiday, or leave record, record as absence
                    $absences[] = $date->format('Y-m-d');
                    $date = $date->addDay();
                }
            }
            $left = $right;
            $right++;
        }

        return $absences;
    }
}
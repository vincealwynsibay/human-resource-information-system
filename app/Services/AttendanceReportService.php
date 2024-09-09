<?php

namespace App\Services;

use App\Enum\AttendanceStatusEnum;
use App\Enum\DayLengthEnum;
use App\Enum\LeaveRequestEnum;
use App\Enum\OvertimeRequestEnum;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class AttendanceReportService
{

    public function getAttendanceSummary($employeeId, $attendances, $workShift, $dateFrom, $dateTo)
    {
        $parsedAttendances = [];
        $totalWorkDuration = 0;
        $totalOvertimeRegularDay = 0;
        $totalOvertimeRestDay = 0;
        $totalOvertimeHoliday = 0;
        $totalUndertimeDuration = 0;
        $totalOvertimeDuration = 0;
        $totalLateDuration = 0;
        $absences = 0;
        $leave = 0;
        $left = 0;

        $workWeek = DB::select('CALL procGetWorkWeek()')[0];
        $dayNo = $dateFrom->day;
        $endDay = $dateTo->day;

        // beginning of the month to the first attendance record
        while ($dayNo <= $endDay && Date::today()->setDay($dayNo)->lt(Date::now())) {

            if (count($attendances) > 0 && Date::today()->setDay($dayNo)->eq($attendances[0]->date)) {
                break;
            }

            $currentDate = Date::today()->setDay($dayNo);
            array_push($parsedAttendances, $this->parseAttendance($employeeId, $currentDate));
            $dayNo++;
        }

        // first attendance record to last attendance record

        do {
            if (count($attendances) == 0) {
                break;
            }

            $attendance = $attendances[$left];
            $leftDate = $attendances[$left];
            $lateDuration = 0;
            $overtimeDuration = 0;
            $workDuration = 0;
            $undertimeDuration = 0;

            // current attendance
            $timeIn1 = $attendance->time_in_session1;
            $timeOut1 = $attendance->time_out_session1;
            $timeIn2 = $attendance->time_in_session2;
            $timeOut2 = $attendance->time_out_session2;
            $workDuration = 0;
            // tracks total hours counter per day (maximum of work shift horurs per day)
            $countedWorkDuration = 0;

            // hours worked in am
            if ($timeIn1 != null && $timeOut1 != null) {
                $workDuration += Date::parse($timeIn1)->diffInMinutes(Date::parse($timeOut1)) / 60;
            }

            // hours worked in pm
            if ($timeIn2 != null && $timeOut2 != null) {
                $workDuration += Date::parse($timeIn2)->diffInMinutes(Date::parse($timeOut2)) / 60;
            }

            $countedWorkDuration = min($workDuration, $workShift->hours_per_day);
            $totalWorkDuration += $countedWorkDuration;

            if ($countedWorkDuration < $workShift->hours_per_day) {
                $undertimeDuration += $workShift->hours_per_day - $countedWorkDuration;
                $totalUndertimeDuration += $workShift->hours_per_day - $countedWorkDuration;
            }

            //     // check if either holiday or rest day, because there is a different rate for that, 
            //     // if also holiday or rest day, it is impossible to overtime because all work hours is an overtime
            $day = strtolower(Date::createFromFormat('Y-m-d', $attendance->date)->format('D'));
            $dayLength = $workWeek->$day;
            // CHECK IF VALID (IF is_recurring CHECK MONTH AND DAY, IF NOT CHECK VALIDITY DATE)

            $holiday = DB::select('CALL procGetHolidayByDate(?)', [$attendance->date])[0] ?? null;
            $recurringHoliday = DB::select('CALL procGetRecurringHolidays(?, ?)', [Date::parse($attendance->date)->format('m'), Date::parse($attendance->date)->format('d')])[0] ?? null;
            $overtime = DB::select('CALL procGetEmployeeOvertimeByDate(?, ?, ?)', [
                $employeeId,
                $attendance->date,
                OvertimeRequestEnum::APPROVED->value
            ])[0] ?? null;

            if ($overtime != null) {
                if (DayLengthEnum::from($dayLength)->isNoWork()) {
                    $overtimeDuration = $countedWorkDuration;
                    $totalOvertimeRestDay += $countedWorkDuration;
                    $totalOvertimeDuration += $countedWorkDuration;
                } else if ($holiday != null || $recurringHoliday != null) {
                    $overtimeDuration = $countedWorkDuration;
                    $totalOvertimeHoliday += $countedWorkDuration;
                    $totalOvertimeDuration += $countedWorkDuration;
                } else {
                    $overtimeIn = Date::parse($attendance->time_in_overtime);
                    $overtimeOut = Date::parse($attendance->time_out_overtime);
                    $overtimeDuration = $overtimeIn->diffInMinutes($overtimeOut);
                    $allowedOvertimeMinutes = $overtime->duration * 60;

                    // overtime duration should be within requested overtime duration
                    $countedOvertimeDuration = min($overtimeDuration, $allowedOvertimeMinutes) / 60;
                    $overtimeDuration = $countedOvertimeDuration;
                    $totalOvertimeDuration += $countedOvertimeDuration;
                    $totalOvertimeRegularDay += $countedOvertimeDuration;
                }
            }

            if ($timeIn1 != null && $timeIn1 > $workShift->start_time1) {
                $lateDuration = Date::parse($timeIn1)->setTimeFromTimeString($workShift->start_time1)->diffInMinutes(Date::parse($timeIn1)) / 60;
                $totalLateDuration += Date::parse($timeIn1)->setTimeFromTimeString($workShift->start_time1)->diffInMinutes(Date::parse($timeIn1)) / 60;
            }

            if ($timeIn2 != null && $timeIn2 > $workShift->start_time2) {
                $lateDuration = Date::parse($timeIn2)->setTimeFromTimeString($workShift->start_time2)->diffInMinutes(Date::parse($timeIn2)) / 60;
                $totalLateDuration += Date::parse($timeIn2)->setTimeFromTimeString($workShift->start_time2)->diffInMinutes(Date::parse($timeIn2)) / 60;
            }


            $parsedAttendances[] = [
                'date' => $attendances[$left]->date,
                'time_in_session1' => $attendances[$left]->time_in_session1,
                'time_out_session1' => $attendances[$left]->time_out_session1,
                'time_in_session2' => $attendances[$left]->time_in_session2,
                'time_out_session2' => $attendances[$left]->time_out_session2,
                'time_in_overtime' => $attendances[$left]->time_in_overtime,
                'time_out_overtime' => $attendances[$left]->time_out_overtime,
                'work_duration' => $countedWorkDuration,
                'overtime_duration' => $overtimeDuration,
                'undertime_duration' => $undertimeDuration,
                'late_duration' => $lateDuration,
                'status' => AttendanceStatusEnum::PRESENT->value,
            ];
            $dayNo++;


            // check if there's more than one (1) attendance
            if (count($attendances) == 1) {
                break;
            }

            // check if $left + 1 is out of bounds
            if ($left + 1 >= count($attendances)) {
                break;
            }


            $rightDate = $attendances[$left + 1];
            // get gap between two (2) attendances
            if (Date::parse($leftDate->date)->diffInDays(Date::parse($rightDate->date)) > 1) {
                $date = Date::parse($leftDate->date)->addDay();

                // left date + 1 to right before the right date, check if there are absences
                while ($date->lt(Date::parse($rightDate->date))) {
                    $currentDate = $date->format('Y-m-d');
                    $dayNo++;
                    $parsedAttendance = $this->parseAttendance($employeeId, $date);
                    array_push($parsedAttendances, $parsedAttendance);
                    $date = $date->addDay();

                    if ($parsedAttendance['status'] == AttendanceStatusEnum::ABSENT->value) {
                        $absences++;
                    }
                }
            }

            $left++;
        } while ($left < count($attendances));


        // days between last attendance and today + 1 should be absent
        while ($dayNo <= Date::today()->format("d")) {
            $parsedAttendances[] = [
                'date' => Date::today()->format('Y-m-d'),
                'time_in_session1' => null,
                'time_out_session1' => null,
                'time_in_session2' => null,
                'time_out_session2' => null,
                'work_duration' => 0,
                'overtime_duration' => 0,
                'undertime_duration' => 0,
                'late_duration' => 0,
                'status' => "ABSENOT",
            ];
            $dayNo++;
        }

        // days between today + 1 and end of month should be empty
        while ($dayNo <= $endDay) {
            $parsedAttendances[] = [
                'date' => Date::today()->setDay($dayNo)->format('Y-m-d'),
                'time_in_session1' => null,
                'time_out_session1' => null,
                'time_in_session2' => null,
                'time_out_session2' => null,
                'work_duration' => 0,
                'overtime_duration' => 0,
                'undertime_duration' => 0,
                'late_duration' => 0,
                'status' => AttendanceStatusEnum::FUTURE->value,
            ];
            $dayNo++;
        }



        return [
            'attendances' => $parsedAttendances,
            "totalWorkDuration" => $totalWorkDuration,
            "totalOvertimeRegularDay" => $totalOvertimeRegularDay,
            "totalOvertimeRestDay" => $totalOvertimeRestDay,
            "totalOvertimeHoliday" => $totalOvertimeHoliday,
            "totalOvertimeDuration" => $totalOvertimeDuration,
            "totalUndertimeDuration" => $totalUndertimeDuration,
            "totalLateDuration" => $totalLateDuration,
            "absences" => $absences,
            "leave" => $leave,
        ];
    }

    public function getAllEmployeeAttendance($employeeId, $attendances, $workShift)
    {
        $parsedAttendances = [];
        $totalWorkDuration = 0;
        $totalOvertimeRegularDay = 0;
        $totalOvertimeRestDay = 0;
        $totalOvertimeHoliday = 0;
        $totalUndertimeDuration = 0;
        $totalOvertimeDuration = 0;
        $totalLateDuration = 0;
        $left = 0;
        $workWeek = DB::select('CALL procGetWorkWeek()')[0];


        do {
            if (count($attendances) == 0) {
                break;
            }
            $attendance = $attendances[$left];
            $lateDuration = 0;
            $overtimeDuration = 0;
            $workDuration = 0;
            $undertimeDuration = 0;

            // current attendance
            $timeIn1 = $attendance->time_in_session1;
            $timeOut1 = $attendance->time_out_session1;
            $timeIn2 = $attendance->time_in_session2;
            $timeOut2 = $attendance->time_out_session2;
            $workDuration = 0;
            // tracks total hours counter per day (maximum of work shift horurs per day)
            $countedWorkDuration = 0;

            // hours worked in am
            if ($timeIn1 != null && $timeOut1 != null) {
                $workDuration += Date::parse($timeOut1)->diffInMinutes(Date::parse($timeIn1)) / 60;
            }

            // hours worked in pm
            if ($timeIn2 != null && $timeOut2 != null) {
                $workDuration += Date::parse($timeOut1)->diffInMinutes(Date::parse($timeIn1)) / 60;
            }

            $countedWorkDuration = min($workDuration, $workShift->hours_per_day);
            $totalWorkDuration += $countedWorkDuration;

            if ($workDuration < $workShift->hours_per_day) {
                $undertimeDuration += $workShift->hours_per_day - $workDuration;
                $totalUndertimeDuration += $workShift->hours_per_day - $workDuration;
            }

            //     // check if either holiday or rest day, because there is a different rate for that, 
            //     // if also holiday or rest day, it is impossible to overtime because all work hours is an overtime
            $day = strtolower(Date::createFromFormat('Y-m-d', $attendance->date)->format('D'));
            $dayLength = $workWeek->$day;

            $holiday = DB::select('CALL procGetHolidayByDate(?)', [$attendance->date])[0] ?? null;
            $recurringHoliday = DB::select('CALL procGetRecurringHolidays(?, ?)', [Date::parse($attendance->date)->format('m'), Date::parse($attendance->date)->format('d')])[0] ?? null;
            $overtime = DB::select('CALL procGetEmployeeOvertimeByDate(?, ?, ?)', [
                $employeeId,
                $attendance->date,
                OvertimeRequestEnum::APPROVED->value
            ])[0] ?? null;

            if ($overtime != null) {
                if (DayLengthEnum::from($dayLength)->isNoWork()) {
                    $totalOvertimeRestDay += $countedWorkDuration;
                    $overtimeDuration += $countedWorkDuration;
                    $totalOvertimeDuration += $countedWorkDuration;
                } else if ($holiday != null || $recurringHoliday != null) {
                    $totalOvertimeHoliday += $countedWorkDuration;
                    $overtimeDuration += $countedWorkDuration;
                    $totalOvertimeDuration += $countedWorkDuration;
                } else {
                    $overtimeIn = Date::parse($attendance->time_in_overtime);
                    $overtimeOut = Date::parse($attendance->time_out_overtime);
                    $overtimeDurationInMinutes = $overtimeOut->diffInMinutes($overtimeIn);
                    $allowedOvertimeMinutes = $overtime->duration * 60;

                    // overtime duration should be within requested overtime duration
                    $countedOvertimeDuration = min($overtimeDurationInMinutes, $allowedOvertimeMinutes) / 60;

                    $overtimeDuration = $countedOvertimeDuration;
                    $totalOvertimeDuration += $countedWorkDuration;
                    $totalOvertimeRegularDay += $countedOvertimeDuration;
                }
            }

            if ($timeIn1 != null && $timeIn1 > $workShift->start_time1) {
                $lateDuration += Date::parse($timeIn1)->diffInMinutes(Date::parse($workShift->start_time1)) / 60;
                $totalLateDuration += Date::parse($timeIn1)->diffInMinutes(Date::parse($workShift->start_time1)) / 60;
            }

            if ($timeIn2 != null && $timeIn2 > $workShift->start_time1) {
                $lateDuration += Date::parse($timeIn2)->diffInMinutes(Date::parse($workShift->start_time1)) / 60;
                $totalLateDuration += Date::parse($timeIn2)->diffInMinutes(Date::parse($workShift->start_time1)) / 60;
            }

            $parsedAttendances[] = [
                'date' => $attendances[$left]->date,
                'time_in_session1' => $attendances[$left]->time_in_session1,
                'time_out_session1' => $attendances[$left]->time_out_session1,
                'time_in_session2' => $attendances[$left]->time_in_session2,
                'time_out_session2' => $attendances[$left]->time_out_session2,
                'work_duration' => $countedWorkDuration,
                'overtime_duration' => $overtimeDuration,
                'undertime_duration' => $undertimeDuration,
                'late_duration' => $lateDuration,
                'status' => AttendanceStatusEnum::PRESENT->value,
            ];

            $left++;
        } while ($left < (count($attendances) - 1));

        return $parsedAttendances;
    }

    protected function parseAttendance($employeeId, $date)
    {
        $formattedDate = $date->format('Y-m-d');
        $workWeek = DB::select('CALL procGetWorkWeek()')[0];
        $day = strtolower(Date::parse($formattedDate)->format('D'));
        $dayLength = $workWeek->$day;

        $holiday = DB::select('CALL procGetHolidayByDate(?)', [$formattedDate])[0] ?? null;
        $recurringHoliday = DB::select('CALL procGetRecurringHolidays(?, ?)', [Date::parse($date)->format('m'), Date::parse($date)->format('d')])[0] ?? null;

        $leave = DB::select('CALL procGetEmployeeLeaves(?, ?, ?, ?, ?)', [
            $employeeId,
            null,
            $formattedDate,
            $formattedDate,
            LeaveRequestEnum::APPROVED->value
        ])[0] ?? null;

        if (DayLengthEnum::from($dayLength)->isNoWork()) {
            return [
                'date' => $formattedDate,
                'time_in_session1' => null,
                'time_out_session1' => null,
                'time_in_session2' => null,
                'time_out_session2' => null,
                'work_duration' => 0,
                'overtime_duration' => 0,
                'undertime_duration' => 0,
                'late_duration' => 0,
                'status' => AttendanceStatusEnum::NONE->value,
            ];
        } else if ($holiday != null || $recurringHoliday != null) {
            return [
                'date' => $formattedDate,
                'time_in_session1' => null,
                'time_out_session1' => null,
                'time_in_session2' => null,
                'time_out_session2' => null,
                'work_duration' => 0,
                'overtime_duration' => 0,
                'undertime_duration' => 0,
                'late_duration' => 0,
                'status' => AttendanceStatusEnum::HOLIDAY->value,
            ];
        } else if ($leave != null) {
            return [
                'date' => $formattedDate,
                'time_in_session1' => null,
                'time_out_session1' => null,
                'time_in_session2' => null,
                'time_out_session2' => null,
                'work_duration' => 0,
                'overtime_duration' => 0,
                'undertime_duration' => 0,
                'late_duration' => 0,
                'leave_type' => $leave->LeaveTypeID,
                'status' => AttendanceStatusEnum::LEAVE->value,
            ];
        }
        return [
            'date' => $formattedDate,
            'time_in_session1' => null,
            'time_out_session1' => null,
            'time_in_session2' => null,
            'time_out_session2' => null,
            'work_duration' => 0,
            'overtime_duration' => 0,
            'undertime_duration' => 0,
            'late_duration' => 0,
            'status' => AttendanceStatusEnum::ABSENT->value,
        ];
    }
}
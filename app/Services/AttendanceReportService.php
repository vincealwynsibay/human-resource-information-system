<?php

namespace App\Services;

use App\Enum\AttendanceStatusEnum;
use App\Enum\DayLengthEnum;
use App\Enum\LeaveRequestEnum;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class AttendanceReportService
{

    public function getAttendanceSummary($attendances, $workShift)
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
        $employeeId = $attendances[0]->EmployeeId;

        $workWeek = DB::table('tblworkweek')->first();
        $dayNo = count($parsedAttendances);
        $numOfDaysInMonth = Date::now()->daysInMonth;

        // beginning of the month to the first attendance record
        while ($dayNo <= $numOfDaysInMonth && Date::today()->setDay($dayNo)->lt(Date::now())) {

            if (count($attendances) > 0 && Date::today()->setDay($dayNo)->eq($attendances[0]->date)) {
                break;
            }

            $currentDate = Date::today()->setDay($dayNo);
            array_push($parsedAttendance, $this->parseAttendance($employeeId, $currentDate));
            $dayNo++;
        }

        // first attendance record to last attendance record
        do {
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
                $workDuration += Date::parse($timeOut1)->diffInMinutes(Date::parse($timeIn1)) / 60;
            }

            // hours worked in pm
            if ($timeIn2 != null && $timeOut2 != null) {
                $workDuration += Date::parse($timeOut1)->diffInMinutes(Date::parse($timeIn1)) / 60;
            }

            $countedWorkDuration = min($workDuration, $workShift->hoursPerDay);
            $totalWorkDuration += $countedWorkDuration;

            if ($workDuration < $workShift->hours_per_day) {
                $undertimeDuration += $workShift->hours_per_day - $workDuration;
                $totalUndertimeDuration += $workShift->hours_per_day - $workDuration;
            }

            //     // check if either holiday or rest day, because there is a different rate for that, 
            //     // if also holiday or rest day, it is impossible to overtime because all work hours is an overtime
            $day = strtolower(Date::createFromFormat('Y-m-d', $attendance->date)->format('D'));
            $dayLength = $workWeek->$day;
            $holiday = DB::table('tblholiday')->where('date', $attendance->date)->first();
            $overtime = DB::table('tblovertime')->where('date', $attendance->date)->first();

            if ($overtime != null) {
                if (DayLengthEnum::from($dayLength)->isNoWork()) {
                    $totalOvertimeRestDay += $countedWorkDuration;
                    $overtimeDuration += $countedWorkDuration;
                    $totalOvertimeDuration += $countedWorkDuration;
                } else if ($holiday != null) {
                    $totalOvertimeHoliday += $countedWorkDuration;
                    $overtimeDuration += $countedWorkDuration;
                    $totalOvertimeDuration += $countedWorkDuration;
                } else {
                    $overtimeIn = Date::parse($attendance->time_in_overtime);
                    $overtimeOut = Date::parse($attendance->time_out_overtime);
                    $overtimeDuration = $overtimeOut->diffInMinutes($overtimeIn);
                    $allowedOvertimeMinutes = $overtime->duration * 60;

                    // overtime duration should be within requested overtime duration
                    $countedOvertimeDuration = min($overtimeDuration, $allowedOvertimeMinutes);
                    $overtimeDuration += $countedOvertimeDuration;
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

            $parsedAttendance[] = [
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
            $dayNo++;

            // check if there's more than one (1) attendance
            if (count($attendances) == 1) {
                break;
            }

            $rightDate = $attendances[$left + 1];
            // get gap between two attendances
            if (Date::parse($leftDate->date)->diffInDays(Date::parse($rightDate->date)) > 1) {
                $date = Date::parse($leftDate->date)->addDay();

                // left date + 1 to right before the right date, check if there are absences
                while ($date->lt(Date::parse($rightDate->date))) {
                    $currentDate = $date->format('Y-m-d');
                    $dayNo++;
                    $parsedAttendance = $this->parseAttendance($employeeId, $currentDate);
                    array_push($parsedAttendances, $parsedAttendance);
                    $date = $date->addDay();

                    if ($parsedAttendance['status'] == AttendanceStatusEnum::ABSENT->value) {
                        $absences++;
                    }
                }
            }

            $left++;
        } while ($left < (count($attendances) - 1));

        // days between last attendance and today + 1 should be absent
        while ($dayNo <= Date::today()->format("d")) {
            $parsedAttendance[] = [
                'date' => Date::today()->format('Y-m-d'),
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
            $dayNo++;
        }

        // days between today + 1 and end of month should be empty
        while ($dayNo <= $numOfDaysInMonth) {
            $parsedAttendance[] = [
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
            'attendances' => $parsedAttendance,
            "totalWorkDuration" => $totalWorkDuration,
            "totalOvertimeRegularDay" => $totalOvertimeRegularDay,
            "totalOvertimeRestDay" => $totalOvertimeRestDay,
            "totalOvertimeHoliday" => $totalOvertimeHoliday,
            "totalOvertimDuration" => $totalOvertimeDuration,
            "totalUndertimeDuration" => $totalUndertimeDuration,
            "totalLateDuration" => $totalLateDuration,
            "absences" => $absences,
            "leave" => $leave,
        ];
    }

    public function getAllEmployeeAttendance($attendances, $workShift)
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
        $workWeek = DB::table('tblworkweek')->first();

        do {
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

            $countedWorkDuration = min($workDuration, $workShift->hoursPerDay);
            $totalWorkDuration += $countedWorkDuration;

            if ($workDuration < $workShift->hours_per_day) {
                $undertimeDuration += $workShift->hours_per_day - $workDuration;
                $totalUndertimeDuration += $workShift->hours_per_day - $workDuration;
            }

            //     // check if either holiday or rest day, because there is a different rate for that, 
            //     // if also holiday or rest day, it is impossible to overtime because all work hours is an overtime
            $day = strtolower(Date::createFromFormat('Y-m-d', $attendance->date)->format('D'));
            $dayLength = $workWeek->$day;
            $holiday = DB::table('tblholiday')->where('date', $attendance->date)->first();
            $overtime = DB::table('tblovertime')->where('date', $attendance->date)->first();

            if ($overtime != null) {
                if (DayLengthEnum::from($dayLength)->isNoWork()) {
                    $totalOvertimeRestDay += $countedWorkDuration;
                    $overtimeDuration += $countedWorkDuration;
                    $totalOvertimeDuration += $countedWorkDuration;
                } else if ($holiday != null) {
                    $totalOvertimeHoliday += $countedWorkDuration;
                    $overtimeDuration += $countedWorkDuration;
                    $totalOvertimeDuration += $countedWorkDuration;
                } else {
                    $overtimeIn = Date::parse($attendance->time_in_overtime);
                    $overtimeOut = Date::parse($attendance->time_out_overtime);
                    $overtimeDuration = $overtimeOut->diffInMinutes($overtimeIn);
                    $allowedOvertimeMinutes = $overtime->duration * 60;

                    // overtime duration should be within requested overtime duration
                    $countedOvertimeDuration = min($overtimeDuration, $allowedOvertimeMinutes);
                    $overtimeDuration += $countedOvertimeDuration;
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

            $parsedAttendance[] = [
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
        $day = strtolower(Date::parse($formattedDate)->format('D'));
        $workWeek = DB::table('tblworkweek')->first();
        $dayLength = $workWeek->$day;
        $holiday = DB::table('tblholiday')->where('date', $formattedDate)->first();
        $leave = DB::table('tblleave')
            ->where('status', LeaveRequestEnum::APPROVED)
            // if unpaid leave add to absences
            ->whereNot('leaveTypeID', null)
            ->where('EmployeeID', $employeeId)
            ->where(function ($query) use ($formattedDate) {
                $query->where('start_date', "<=", $formattedDate);
                $query->where('end_date', ">=", $formattedDate);
            })
            ->first();

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
        } else if ($holiday != null) {
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
<?php

namespace App\Services;

use App\Enum\DayLengthEnum;
use App\Enum\LeaveRequestEnum;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class AttendanceReportService
{

    public function getEmployeeAbsencesByDateRange($employeeID, $startDate, $endDate)
    {

        // date range
        $attendances = DB::table('tblattendance')->where($employeeID)->where('date', ">=", $startDate)->where('date', "<=", $endDate)->get();

        // get days with no attendance record
        $absences = [];
        $left = 0;
        $right = 1;

        while ($right < count($attendances)) {
            $leftDate = $attendances[$left];
            $rightDate = $attendances[$right];


            // compare if there is a gap between attendance records (later date - earlier date > 1), this means that the gap could be an absence.
            if (Date::format("Y-m-d", $rightDate->date)->diffInDays(Date::format("Y-m-d", $leftDate->date)) > 1) {
                // start from the day after the earlier attendance record
                $date = Date::parse($leftDate->date)->addDay();

                // get all dates between the two attendance records gap
                while ($date->lt(Date::parse($rightDate->date))) {
                    $currentDate = $date->format('Y-m-d');

                    // check if the day is not a work day, if it is, do not record as absence
                    $day = strtolower(Date::parse($currentDate)->format('D'));
                    $workWeek = DB::table('tblworkweek')->first();
                    $dayLength = $workWeek->$day;

                    if (DayLengthEnum::from($dayLength)->isFullDay()) {
                        $date = $date->addDay();
                        continue;
                    }

                    // check if there is a holiday on the date, if there is, do not record as absence
                    $holiday = DB::table('tblholiday')->where('date', $currentDate)->first();

                    if ($holiday != null) {
                        $date = $date->addDay();
                        continue;
                    }

                    // check if there is a leave record on the date, if there is, do not record as absence
                    $leave = DB::table('tblleave')->where('status', LeaveRequestEnum::APPROVED)->where('EmployeeID', $employeeID)->where(function ($query) use ($currentDate) {
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
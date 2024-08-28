<?php

namespace App\Http\Controllers;

use App\Enum\DayLengthEnum;
use App\Enum\LeaveRequestEnum;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelPdf\Facades\Pdf;
use function Spatie\LaravelPdf\Support\pdf;

class DownloadDTRController extends Controller
{
    public function __invoke(Request $request)
    {

        $validated = $request->validate([
            'employee_id' => 'required|exists:tblemployee,id',
        ]);
        $attendance = $this->generateDTR($validated['employee_id']);
        $employee = DB::table('tblemployee')->where('id', $validated['employee_id'])->first();

        return Pdf::view('pdf.dtr', [
            'employee' => $employee,
            'month' => Date::now()->format('F'),
            'year' => Date::now()->format('Y'),
            'attendances' => $attendance['attendances'],
            'total_hours_worked' => $attendance['total_hours_worked'],
            'total_overtime_hours' => $attendance['total_overtime_hours'],
            'total_undertime_hours' => $attendance['total_undertime_hours'],
            'total_late_hours' => $attendance['total_late_hours']
        ])
            ->download('dtr.pdf');
    }

    public function generateDTR($employee_id)
    {
        // get all attendance records within the month
        $employee = DB::table('tblemployee')->where('id', $employee_id)->first();
        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        $attendances = DB::table('tblattendancerecord')->where('EmployeeID', $employee_id)->where('date', '>=', Date::now()->startOfMonth())->where('date', '<=', Date::now()->endOfMonth())->orderBy('date', 'asc')->get();
        $employeeID = $employee_id;

        $parsedAttendance = [];
        $numOfDaysInMonth = Date::now()->daysInMonth;

        // beginning of the month to the first attendance record or the current date
        // while ($left < count($attendances) && Date::parse($attendances[$left]->date)->lt(Date::now())) {
        $dayNo = 1;

        while ($dayNo < $numOfDaysInMonth && Date::today()->setDay($dayNo)->lt(Date::now())) {

            if (count($attendances) > 0 && Date::today()->setDay($dayNo)->eq($attendances[0]->date)) {
                break;
            }

            $currentDate = Date::today()->setDay($dayNo)->format('Y-m-d');
            $day = strtolower(Date::parse($currentDate)->format('D'));
            $workWeek = DB::table('tblworkweek')->first();
            $dayLength = $workWeek->$day;

            $holiday = DB::table('tblholiday')->where('date', $currentDate)->first();
            $leave = DB::table('tblleave')->where('status', LeaveRequestEnum::APPROVED)->where('EmployeeID', $employeeID)->where(function ($query) use ($currentDate) {
                $query->where('start_date', "<=", $currentDate);
                $query->where('end_date', ">=", $currentDate);
            })->first();

            if (DayLengthEnum::from($dayLength)->isNoWork()) {
                $parsedAttendance[] = [
                    'date' => Date::today()->setDay($dayNo)->format('Y-m-d'),
                    'time_in_session1' => null,
                    'time_out_session1' => null,
                    'time_in_session2' => null,
                    'time_out_session2' => null,
                    'hours_worked' => 0,
                    'overtime_hours' => 0,
                    'undertime_hours' => 0,
                    'late_hours' => 0,
                    'status' => 'NONE',
                ];
            } else if ($holiday != null) {
                $parsedAttendance[] = [
                    'date' => Date::today()->setDay($dayNo)->format('Y-m-d'),
                    'time_in_session1' => null,
                    'time_out_session1' => null,
                    'time_in_session2' => null,
                    'time_out_session2' => null,
                    'hours_worked' => 0,
                    'overtime_hours' => 0,
                    'undertime_hours' => 0,
                    'late_hours' => 0,
                    'status' => 'HOLIDAY',
                ];
            } else if ($leave != null) {
                $parsedAttendance[] = [
                    'date' => Date::today()->setDay($dayNo)->format('Y-m-d'),
                    'time_in_session1' => null,
                    'time_out_session1' => null,
                    'time_in_session2' => null,
                    'time_out_session2' => null,
                    'hours_worked' => 0,
                    'overtime_hours' => 0,
                    'undertime_hours' => 0,
                    'late_hours' => 0,
                    'status' => 'LEAVE',
                    'leave_type' => $leave->LeaveTypeID
                ];
            } else {
                $parsedAttendance[] = [
                    'date' => Date::today()->setDay($dayNo)->format('Y-m-d'),
                    'time_in_session1' => null,
                    'time_out_session1' => null,
                    'time_in_session2' => null,
                    'time_out_session2' => null,
                    'hours_worked' => 0,
                    'overtime_hours' => 0,
                    'undertime_hours' => 0,
                    'late_hours' => 0,
                    'status' => "ABSENT",
                ];
            }

            $dayNo++;
        }


        $totalHoursWorked = 0;
        $totalOvertimeHours = 0;
        $totalUndertimeHours = 0;
        $totalLateHours = 0;

        $employeeWorkShift = DB::table('tblemployeeworkshift')->where('EmployeeID', $employee->id)->first();
        $workShift = DB::table('tblworkshift')->where('id', $employeeWorkShift->WorkShiftID)->first();
        $left = 0;


        $day = strtolower(Date::today()->format('D'));
        $workWeek = DB::table('tblworkweek')->first();
        $dayLength = $workWeek->$day;

        while ($left < count($attendances)) {
            $leftDate = $attendances[$left];
            $lateHours = 0;
            $hoursWorked = 0;
            $undertimeHours = 0;
            // $overtime = DB::table('tblovertime')->where('EmployeeID', $employee_id)->whereDate('requested_date', $leftDate->date);
            // TODO: OVERTIME LENGTH

            $employeeWorkShift = DB::table('tblemployeeworkshift')->where('EmployeeID', $employee->id)->first();
            $workShift = DB::table('tblworkshift')->where('id', $employeeWorkShift->WorkShiftID)->first();

            if ($leftDate->time_in_session1 != null && $leftDate->time_out_session1 != null) {
                $hoursWorked += Date::parse($leftDate->time_in_session1)->diffInMinutes(Date::parse($leftDate->time_out_session1)) / 60;
            }

            if ($leftDate->time_in_session2 != null && $leftDate->time_out_session2 != null) {
                $hoursWorked += Date::parse($leftDate->time_in_session2)->diffInMinutes(Date::parse($leftDate->time_out_session2)) / 60;
            }

            if ($leftDate->time_in_session1 != null && $leftDate->time_in_session1 > $workShift->start_time) {
                $lateHours += Date::parse(Date::parse($workShift->start_time))->diffInMinutes($leftDate->time_in_session1) / 60;
            }

            if ($leftDate->time_in_session2 != null && $leftDate->time_in_session2 > $workShift->start_time) {
                $lateHours += Date::parse(Date::parse($workShift->end_break_time))->diffInMinutes($leftDate->time_in_session2) / 60;
            }

            $undertimeHours = $workShift->hours_per_day - $hoursWorked;

            $parsedAttendance[] = [
                'date' => $attendances[$left]->date,
                'time_in_session1' => $attendances[$left]->time_in_session1,
                'time_out_session1' => $attendances[$left]->time_out_session1,
                'time_in_session2' => $attendances[$left]->time_in_session2,
                'time_out_session2' => $attendances[$left]->time_out_session2,
                'hours_worked' => $hoursWorked,
                // TODO: CALCULATE OVERTIME WITH TBLOVERTIME
                'overtime_hours' => 0,
                'undertime_hours' => $undertimeHours,
                'late_hours' => $lateHours,
                'status' => 'PRESENT',
            ];


            $dayNo++;
            $totalHoursWorked += $hoursWorked;
            $totalOvertimeHours += 0;
            $totalUndertimeHours += $undertimeHours;
            $totalLateHours += $lateHours;

            if (count($attendances) == 1) {
                break;
            }


            if (($left + 1) == count($attendances)) {
                break;
            }

            $rightDate = $attendances[$left + 1];
            // compare if there is a gap between attendance records (later date - earlier date > 1), this means that the gap could be an absence.
            if (Date::createFromFormat("Y-m-d", $leftDate->date)->diffInDays(Date::createFromFormat("Y-m-d", $rightDate->date)) > 1) {
                // start from the day after the earlier attendance record
                $date = Date::createFromFormat("Y-m-d", $leftDate->date)->addDay();
                // get all dates between the two attendance records gap
                while ($date->lt(Date::createFromFormat("Y-m-d", $rightDate->date))) {
                    $currentDate = $date->format('Y-m-d');
                    $dayNo++;

                    // check if the day is not a work day, if it is, do not record as absence
                    $day = strtolower(Date::parse($currentDate)->format('D'));
                    $workWeek = DB::table('tblworkweek')->first();
                    $dayLength = $workWeek->$day;

                    if (DayLengthEnum::from($dayLength)->isNoWork()) {
                        $parsedAttendance[] = [
                            'date' => $currentDate,
                            'time_in_session1' => null,
                            'time_out_session1' => null,
                            'time_in_session2' => null,
                            'time_out_session2' => null,
                            'hours_worked' => 0,
                            'overtime_hours' => 0,
                            'undertime_hours' => 0,
                            'late_hours' => 0,
                            'status' => 'NONE',
                        ];

                        $date = $date->addDay();
                        continue;
                    }

                    // check if there is a holiday on the date, if there is, do not record as absence
                    $holiday = DB::table('tblholiday')->where('date', $currentDate)->first();

                    if ($holiday != null) {
                        $parsedAttendance[] = [
                            'date' => $currentDate,
                            'time_in_session1' => null,
                            'time_out_session1' => null,
                            'time_in_session2' => null,
                            'time_out_session2' => null,
                            'hours_worked' => 0,
                            'overtime_hours' => 0,
                            'undertime_hours' => 0,
                            'late_hours' => 0,
                            'status' => 'HOLIDAY',
                        ];
                        $date = $date->addDay();
                        continue;
                    }

                    // check if there is a leave record on the date, if there is, do not record as absence
                    $leave = DB::table('tblleave')->where('status', LeaveRequestEnum::APPROVED)->where('EmployeeID', $employeeID)->where(function ($query) use ($currentDate) {
                        $query->where('start_date', "<=", $currentDate);
                        $query->where('end_date', ">=", $currentDate);
                    })->first();

                    if ($leave != null) {
                        $parsedAttendance[] = [
                            'date' => $currentDate,
                            'time_in_session1' => null,
                            'time_out_session1' => null,
                            'time_in_session2' => null,
                            'time_out_session2' => null,
                            'hours_worked' => 0,
                            'overtime_hours' => 0,
                            'undertime_hours' => 0,
                            'late_hours' => 0,
                            'status' => 'LEAVE',
                            'leave_type' => $leave->LeaveTypeID
                        ];
                        $date = $date->addDay();
                        continue;
                    }

                    // if no work day, holiday, or leave record, record as absence
                    $parsedAttendance[] = [
                        'date' => $currentDate,
                        'time_in_session1' => null,
                        'time_out_session1' => null,
                        'time_in_session2' => null,
                        'time_out_session2' => null,
                        'hours_worked' => 0,
                        'overtime_hours' => 0,
                        'undertime_hours' => 0,
                        'late_hours' => 0,
                        'status' => 'ABSENT'
                    ];
                    $date = $date->addDay();
                }
            }

            $left++;
        }



        while ($dayNo <= Date::today()->format("d")) {
            $parsedAttendance[] = [
                'date' => Date::today()->format('Y-m-d'),
                'time_in_session1' => null,
                'time_out_session1' => null,
                'time_in_session2' => null,
                'time_out_session2' => null,
                'hours_worked' => 0,
                'overtime_hours' => 0,
                'undertime_hours' => 0,
                'late_hours' => 0,
                'status' => 'ABSENT',
            ];
            $dayNo++;
        }


        // check if right is less than number of days in the month
        while ($dayNo <= $numOfDaysInMonth) {
            $parsedAttendance[] = [
                'date' => Date::today()->setDay($dayNo)->format('Y-m-d'),
                'time_in_session1' => null,
                'time_out_session1' => null,
                'time_in_session2' => null,
                'time_out_session2' => null,
                'hours_worked' => 0,
                'overtime_hours' => 0,
                'undertime_hours' => 0,
                'late_hours' => 0,
                'status' => '',
            ];
            $dayNo++;
        }

        return [
            "attendances" => $parsedAttendance,
            "total_hours_worked" => $totalHoursWorked,
            "total_overtime_hours" => $totalOvertimeHours,
            "total_undertime_hours" => $totalUndertimeHours,
            "total_late_hours" => $totalLateHours
        ];
    }
    // public function generateDTR($employee_id)
    // {
    //     // get all attendance records within the month
    //     $employee = DB::table('tblemployee')->where('id', $employee_id)->first();
    //     if ($employee == null) {
    //         return response()->json([
    //             'errors' => [
    //                 'Employee' => ['Employee does not exist']
    //             ]
    //         ], 404);
    //     }

    //     $attendances = DB::table('tblattendancerecord')->where('EmployeeID', $employee_id)->where('date', '>=', Date::now()->startOfMonth())->where('date', '<=', Date::now()->endOfMonth())->get();
    //     $employeeID = $employee_id;

    //     $parsedAttendance = [];
    //     $numOfDaysInMonth = Date::now()->daysInMonth;

    //     // beginning of the month to the first attendance record or the current date
    //     // while ($left < count($attendances) && Date::parse($attendances[$left]->date)->lt(Date::now())) {
    //     $dayNo = 1;
    //     while ($dayNo < $numOfDaysInMonth && Date::today()->setDay($dayNo)->lt(Date::now())) {

    //         if (count($attendances) > 0 && Date::today()->setDay($dayNo)->eq($attendances[0]->date)) {
    //             break;
    //         }


    //         $parsedAttendance[] = [
    //             'date' => Date::today()->setDay($dayNo)->format('Y-m-d'),
    //             'time_in_session1' => null,
    //             'time_out_session1' => null,
    //             'time_in_session2' => null,
    //             'time_out_session2' => null,
    //             'hours_worked' => 0,
    //             'overtime_hours' => 0,
    //             'undertime_hours' => 0,
    //             'late_hours' => 0,
    //             'status' => "ABSENT",
    //         ];
    //         $dayNo++;
    //     }


    //     $totalHoursWorked = 0;
    //     $totalOvertimeHours = 0;
    //     $totalUndertimeHours = 0;
    //     $totalLateHours = 0;

    //     $employeeWorkShift = DB::table('tblemployeeworkshift')->where('EmployeeID', $employee->id)->first();
    //     $workShift = DB::table('tblworkshift')->where('id', $employeeWorkShift->WorkShiftID)->first();
    //     $left = 0;
    //     $right = 1;

    //     while ($right <= count($attendances)) {
    //         $leftDate = $attendances[$left];
    //         $lateHours = 0;
    //         $hoursWorked = 0;
    //         $undertimeHours = 0;
    //         // $overtime = DB::table('tblovertime')->where('EmployeeID', $employee_id)->whereDate('requested_date', $leftDate->date);
    //         // TODO: OVERTIME LENGTH

    //         $employeeWorkShift = DB::table('tblemployeeworkshift')->where('EmployeeID', $employee->id)->first();
    //         $workShift = DB::table('tblworkshift')->where('id', $employeeWorkShift->WorkShiftID)->first();

    //         if ($leftDate->time_in_session1 != null && $leftDate->time_out_session1 != null) {
    //             $hoursWorked += Date::parse($leftDate->time_out_session1)->diffInMinutes(Date::parse($leftDate->time_in_session1)) / 60;
    //         }

    //         if ($leftDate->time_in_session2 != null && $leftDate->time_out_session2 != null) {
    //             $hoursWorked += Date::parse($leftDate->time_out_session2)->diffInMinutes(Date::parse($leftDate->time_in_session2)) / 60;
    //         }

    //         if ($leftDate->time_in_session1 != null && $leftDate->time_in_session1 > $workShift->start_time) {
    //             $lateHours += Date::parse($leftDate->time_in_session1)->diffInMinutes(Date::parse($workShift->start_time)) / 60;
    //         }

    //         if ($leftDate->time_in_session2 != null && $leftDate->time_in_session2 > $workShift->start_time) {
    //             $lateHours += Date::parse($leftDate->time_in_session2)->diffInMinutes(Date::parse($workShift->start_time)) / 60;
    //         }

    //         $undertimeHours = ($workShift->hours_per_day / 60) - $hoursWorked;

    //         $parsedAttendance[] = [
    //             'date' => $attendances[$left]->date,
    //             'time_in_session1' => $attendances[$left]->time_in_session1,
    //             'time_out_session1' => $attendances[$left]->time_out_session1,
    //             'time_in_session2' => $attendances[$left]->time_in_session2,
    //             'time_out_session2' => $attendances[$left]->time_out_session2,
    //             // TODO: CALCULATE OVERTIME WITH TBLOVERTIME
    //             'overtime_hours' => 0,
    //             'undertime_hours' => $undertimeHours,
    //             'late_hours' => $lateHours,
    //             'status' => 'PRESENT',
    //         ];

    //         if (count($attendances) == 1) {
    //             break;
    //         }

    //         if (count($attendances) == $right) {
    //             break;
    //         }

    //         $rightDate = $attendances[$right];

    //         // compare if there is a gap between attendance records (later date - earlier date > 1), this means that the gap could be an absence.
    //         if (Date::createFromFormat("Y-m-d", $rightDate->date)->diffInDays(Date::createFromFormat("Y-m-d", $leftDate->date)) > 1) {
    //             // start from the day after the earlier attendance record
    //             $date = Date::parse($leftDate->date)->addDay();

    //             // get all dates between the two attendance records gap
    //             while ($date->lt(Date::parse($rightDate->date))) {
    //                 $currentDate = $date->format('Y-m-d');

    //                 // check if the day is not a work day, if it is, do not record as absence
    //                 $day = strtolower(Date::parse($currentDate)->format('D'));
    //                 $workWeek = DB::table('tblworkweek')->first();
    //                 $dayLength = $workWeek->$day;

    //                 if (DayLengthEnum::from($dayLength)->isNoWork()) {
    //                     $parsedAttendance[] = [
    //                         'date' => $currentDate,
    //                         'time_in_session1' => null,
    //                         'time_out_session1' => null,
    //                         'time_in_session2' => null,
    //                         'time_out_session2' => null,
    //                         'hours_worked' => 0,
    //                         'overtime_hours' => 0,
    //                         'undertime_hours' => 0,
    //                         'late_hours' => 0,
    //                         'status' => 'NO WORK DAY',
    //                     ];

    //                     $date = $date->addDay();
    //                     continue;
    //                 }

    //                 // check if there is a holiday on the date, if there is, do not record as absence
    //                 $holiday = DB::table('tblholiday')->where('date', $currentDate)->first();

    //                 if ($holiday != null) {
    //                     $parsedAttendance[] = [
    //                         'date' => $currentDate,
    //                         'time_in_session1' => null,
    //                         'time_out_session1' => null,
    //                         'time_in_session2' => null,
    //                         'time_out_session2' => null,
    //                         'hours_worked' => 0,
    //                         'overtime_hours' => 0,
    //                         'undertime_hours' => 0,
    //                         'late_hours' => 0,
    //                         'status' => 'HOLIDAY',
    //                     ];
    //                     $date = $date->addDay();
    //                     continue;
    //                 }

    //                 // check if there is a leave record on the date, if there is, do not record as absence
    //                 $leave = DB::table('tblleave')->where('status', LeaveRequestEnum::APPROVED)->where('EmployeeID', $employeeID)->where(function ($query) use ($currentDate) {
    //                     $query->where('start_date', "<=", $currentDate);
    //                     $query->where('end_date', ">=", $currentDate);
    //                 })->first();

    //                 if ($leave != null) {
    //                     $parsedAttendance[] = [
    //                         'date' => $currentDate,
    //                         'time_in_session1' => null,
    //                         'time_out_session1' => null,
    //                         'time_in_session2' => null,
    //                         'time_out_session2' => null,
    //                         'hours_worked' => 0,
    //                         'overtime_hours' => 0,
    //                         'undertime_hours' => 0,
    //                         'late_hours' => 0,
    //                         'status' => 'LEAVE',
    //                         'leave_type' => $leave->LeaveTypeID
    //                     ];
    //                     $date = $date->addDay();
    //                     continue;
    //                 }

    //                 // if no work day, holiday, or leave record, record as absence
    //                 $parsedAttendance[] = [
    //                     'date' => $currentDate,
    //                     'time_in_session1' => null,
    //                     'time_out_session1' => null,
    //                     'time_in_session2' => null,
    //                     'time_out_session2' => null,
    //                     'hours_worked' => 0,
    //                     'overtime_hours' => 0,
    //                     'undertime_hours' => 0,
    //                     'late_hours' => 0,
    //                     'status' => 'ABSENT',
    //                 ];
    //                 $date = $date->addDay();
    //                 $dayNo++;
    //             }
    //         }

    //         $dayNo++;
    //         $totalHoursWorked += $hoursWorked;
    //         $totalOvertimeHours += 0;
    //         $totalUndertimeHours += $undertimeHours;
    //         $totalLateHours += $lateHours;
    //         $left = $right;
    //         $right++;
    //     }

    //     // check if right is less than number of days in the month
    //     while ($dayNo <= $numOfDaysInMonth) {
    //         $parsedAttendance[] = [
    //             'date' => Date::today()->setDay($dayNo)->format('Y-m-d'),
    //             'time_in_session1' => null,
    //             'time_out_session1' => null,
    //             'time_in_session2' => null,
    //             'time_out_session2' => null,
    //             'hours_worked' => 0,
    //             'overtime_hours' => 0,
    //             'undertime_hours' => 0,
    //             'late_hours' => 0,
    //             'status' => '',
    //         ];
    //         $dayNo++;
    //     }


    //     return [
    //         "attendances" => $parsedAttendance,
    //         "total_hours_worked" => $totalHoursWorked,
    //         "total_overtime_hours" => $totalOvertimeHours,
    //         "total_undertime_hours" => $totalUndertimeHours,
    //         "total_late_hours" => $totalLateHours
    //     ];
    // }
}

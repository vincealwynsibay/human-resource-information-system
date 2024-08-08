<?php

namespace App\Http\Controllers;

use App\Enum\RoleEnum;
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
            $attendances = DB::table('tblattendancerecord')->where('EmployeeID', $request->user()->EmployeeID)
                ->when($request->id, function ($query, $id) {
                    return $query->where('id', $id);
                })
                ->when($request->name, function ($query, $name) {
                    return $query->whereLike(DB::raw("CONCAT(`firstname`, ' ', `lastname`)"), $name);
                })
                ->whereBetween('date', [Date::format("Y-m-d", $request->fromDate), Date::format("Y-m-d", $request->toDate)])
                ->get();
            return response()->json($attendances);
        } else if ($request->user()->roleID == RoleEnum::SUPERVISOR->value) {
            $assignedEmployees = DB::table('tblemployee')->where('SupervisorID', $request->user()->EmployeeID)->get();
            $employeeIDs = $assignedEmployees->pluck('id')->toArray();
            $attendances = DB::table('tblattendancerecord')->whereIn('EmployeeID', $employeeIDs)
                ->when($request->id, function ($query, $id) {
                    return $query->where('id', $id);
                })
                ->when($request->name, function ($query, $name) {
                    return $query->whereLike(DB::raw("CONCAT(`firstname`, ' ', `lastname`)"), $name);
                })
                ->whereBetween('date', [Date::format("Y-m-d", $request->fromDate), Date::format("Y-m-d", $request->toDate)])
                ->get();
        } else {
            $attendances = DB::table('tblattendancerecord')
                ->when($request->id, function ($query, $id) {
                    return $query->where('id', $id);
                })
                ->when($request->name, function ($query, $name) {
                    return $query->whereLike(DB::raw("CONCAT(`firstname`, ' ', `lastname`)"), $name);
                })
                ->whereBetween('date', [Date::format("Y-m-d", $request->fromDate), Date::format("Y-m-d", $request->toDate)])
                ->get();
        }

        return response()->json($attendances);
    }


    public function getEmployeeAttendanceRecords(Request $request, $employeeId)
    {
        $validated = $request->validate([
            "range" => 'nullable|string|in:TODAY,WEEK,MONTH,HALFYEAR,YEAR,ALL',
        ]);
        $employee = DB::table('tblemployee')->where('id', $employeeId)->first();

        if ($request->user()->roleID == RoleEnum::SUPERVISOR->value) {
            if ($employee->SupervisorID != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['Access not allowed']
                    ]
                ], 404);
            }
        }

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($employeeId != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['You view other\'s attendance records']
                    ]
                ], 404);
            }
        }

        if ($request->range === "ALL") {
            $attendances = DB::table('tblattendancerecord')->where('EmployeeID', $employeeId)->orderBy('date', 'DESC')->get();
        } else if ($request->range === "TODAY") {
            $attendances = DB::table('tblattendancerecord')->where('EmployeeID', $employeeId)->where('date', Date::today())->orderBy('date', 'DESC')->get();
        } else if ($request->range === "WEEK") {
            $attendances = DB::table('tblattendancerecord')->where('EmployeeID', $employeeId)->where('date', [Date::now()->startOfWeek(), Date::today()])->orderBy('date', 'DESC')->get();
        } else if ($request->range === "MONTH") {
            $attendances = DB::table('tblattendancerecord')->where('EmployeeID', $employeeId)->where('date', [Date::now()->startOfMonth(), Date::today()])->orderBy('date', 'DESC')->get();
        } else if ($request->range === "HALFYEAR") {
            if (Date::month() <= 6) {
                $attendances = DB::table('tblattendancerecord')->where('EmployeeID', $employeeId)->where('date', [Date::now()->startOfYear(), Date::today()])->orderBy('date', 'DESC')->get();
            } else {
                $attendances = DB::table('tblattendancerecord')->where('EmployeeID', $employeeId)->where('date', [Date::now()->subMonths(6), Date::today()])->orderBy('date', 'DESC')->get();
            }
        } else if ($request->range === "YEAR") {
            $attendances = DB::table('tblattendancerecord')->where('EmployeeID', $employeeId)->where('date', [Date::now()->startOfYear(), Date::today()])->orderBy('date', 'DESC')->get();
        } else {
            return response()->json([
                'errors' => [
                    'range' => ['Invalid range']
                ]
            ], 400);
        }


        //TODO: CHECK LEAVE RECORDS AND HOLIDAYS
        $absences = $this->getEmployeeAbsencesByDateRange($employeeId, Date::parse($attendances[count($attendances) - 1]->date), Date::parse($attendances[0]->date));

        // update each record to have hours worked for each session, total hours worked for the day, overtime hours, undertime hours, and late hours
        foreach ($attendances as $attendance) {
            $workShift = DB::table('tblworkshift')->where('id', $employee->WorkShiftID)->first();

            $timeIn1 = $attendance->time_in_session1;
            $timeOut1 = $attendance->time_out_session1;
            $timeIn2 = $attendance->time_in_session2;
            $timeOut2 = $attendance->time_out_session2;

            $totalHoursWorked = 0;
            $overtimeHours = 0;
            $undertimeHours = 0;
            $lateHours = 0;

            // calculate total hours worked, if no time out for the session, it is considered as not worked or invalid and not counted
            if ($timeIn1 != null && $timeOut1 != null) {
                $totalHoursWorked += Date::parse($timeOut1)->diffInMinutes(Date::parse($timeIn1)) / 60;
            }

            if ($timeIn2 != null && $timeOut2 != null) {
                $totalHoursWorked += Date::parse($timeOut2)->diffInMinutes(Date::parse($timeIn2)) / 60;
            }

            if ($totalHoursWorked > $workShift->hours_per_day) {
                $overtimeHours = $totalHoursWorked - $workShift->hours_per_day;
            } else if ($totalHoursWorked < $workShift->hours_per_day) {
                $undertimeHours = $workShift->hours_per_day - $totalHoursWorked;
            }

            if ($timeIn1 != null && $timeIn1 > $workShift->start_time) {
                $lateHours += Date::parse($timeIn1)->diffInMinutes(Date::parse($workShift->start_time)) / 60;
            }

            if ($timeIn2 != null && $timeIn2 > $workShift->start_time) {
                $lateHours += Date::parse($timeIn2)->diffInMinutes(Date::parse($workShift->start_time)) / 60;
            }

            $attendance->total_hours_worked = $totalHoursWorked;
            $attendance->overtime_hours = $overtimeHours;
            $attendance->undertime_hours = $undertimeHours;
            $attendance->late_hours = $lateHours;
        }

        return response()->json([
            'attendances' => $attendances,
            'absences' => $absences
        ]);
    }

    // public function getTotalHoursWorkedInAMonth(Request $request, string $id)
    // {
    //     $employee = DB::table('tblemployee')->where('id', $id)->first();

    //     if ($request->user()->roleID == RoleEnum::SUPERVISOR->value) {
    //         if ($employee->SupervisorID != $request->user()->id) {
    //             return response()->json([
    //                 'errors' => [
    //                     'Authorization' => ['Access not allowed']
    //                 ]
    //             ], 404);
    //         }
    //     }

    //     if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
    //         if ($id != $request->user()->id) {
    //             return response()->json([
    //                 'errors' => [
    //                     'Authorization' => ['You view other\'s attendance records']
    //                 ]
    //             ], 404);
    //         }
    //     }
    //     $attendanceWithinTheMonth = DB::table('tblattendancerecord')->where('EmployeeID', $id)->where('date', '>=', Date::now()->startOfMonth())->where('date', '<=', Date::now()->endOfMonth())->get();
    //     $totalHoursWorked = 0;
    //     foreach ($attendanceWithinTheMonth as $attendance) {
    //         $workShift = DB::table('tblworkshift')->where('id', $employee->WorkShiftID)->first();

    //         $timeIn1 = $attendance->time_in_session1;
    //         $timeOut1 = $attendance->time_out_session1;
    //         $timeIn2 = $attendance->time_in_session2;
    //         $timeOut2 = $attendance->time_out_session2;

    //         if ($timeIn1 != null && $timeOut1 != null) {
    //             $totalHoursWorked += Date::parse($timeOut1)->diffInMinutes(Date::parse($timeIn1)) / 60;
    //         }

    //         if ($timeIn2 != null && $timeOut2 != null) {
    //             $totalHoursWorked += Date::parse($timeOut2)->diffInMinutes(Date::parse($timeIn2)) / 60;
    //         }
    //     }
    //     return response()->json([
    //         'Total Hours Worked' => $totalHoursWorked
    //     ]);
    // }


    // for not able to differentiate punch in and punch out
    public function attendance(Request $request)
    {
        $user = $request->user();
        $today = DB::table('tblattendancerecord')->where("EmployeeID", $user->EmployeeID)->where('date', Date::today())->get();

        if ($today == null) {
            $newAttendance = DB::table('tblattendancerecord')->insertGetId([
                'EmployeeID' => $user->EmployeeID,
                'date' => Date::today()
            ]);
            $today = DB::table('tblattendancerecord')->where("id", $newAttendance)->get();
        }

        // check current time
        $employeeWorkShift = DB::table('tblemployeeworkshift')->where("EmployeeID", $user->EmployeeID)->first('id');
        $workShift = DB::table('tblworkshift')->where('id', $employeeWorkShift->id)->first();

        $currentTime = Date::now()->format("H:i");

        // time in/time out before start of second segment (ex. needs to time in/time out before end of lunch break)
        if ($currentTime <= $workShift->end_break_time) {
            if ($today->time_in_session1 == null) {
                // if not timed in yet
                DB::table('tblattendancerecord')->where("EmployeeID", $user->EmployeeID)->where('date', Date::today())->update([
                    'time_in_session1' => $currentTime
                ]);
            } else {
                // if already timed in
                DB::table('tblattendancerecord')->where("EmployeeID", $user->EmployeeID)->where('date', Date::today())->update([
                    'time_out_session1' => $currentTime
                ]);
            }

        } else if ($currentTime > $workShift->end_break_time) {
            // time in/time out for second segment after break time, can time in after end break time and time out after working hours. hours exceeding working hours is considered overtime 
            if ($today->time_in_session2 == null) {
                // if not timed in yet
                DB::table('tblattendancerecord')->where("EmployeeID", $user->EmployeeID)->where('date', Date::today())->update([
                    'time_in_session2' => $currentTime
                ]);
            } else {
                // if already timed in
                DB::table('tblattendancerecord')->where("EmployeeID", $user->EmployeeID)->where('date', Date::today())->update([
                    'time_out_session2' => $currentTime
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
        $today = DB::table('tblattendancerecord')->where("EmployeeID", $user->EmployeeID)->where('date', Date::today())->get();

        if ($today == null) {
            $newAttendance = DB::table('tblattendancerecord')->insertGetId([
                'EmployeeID' => $user->EmployeeID,
                'date' => Date::today()
            ]);
            $today = DB::table('tblattendancerecord')->where("id", $newAttendance)->get();
        }

        // check current time
        $employeeWorkShift = DB::table('tblemployeeworkshift')->where("EmployeeID", $user->EmployeeID)->first('id');
        $workShift = DB::table('tblworkshift')->where('id', $employeeWorkShift->id)->first();

        $currentTime = Date::now()->format("H:i");

        // time in/time out before start of second segment (ex. needs to time in/time out before end of lunch break)
        if ($currentTime >= $workShift->end_break_time) {
            DB::table('tblattendancerecord')->where("EmployeeID", $user->EmployeeID)->where('date', Date::today())->update([
                'time_in_session1' => $currentTime
            ]);

        } else if ($currentTime > $workShift->end_break_time) {
            DB::table('tblattendancerecord')->where("EmployeeID", $user->EmployeeID)->where('date', Date::today())->update([
                'time_out_session2' => $currentTime
            ]);
        }

        return response()->json([
            'Message' => "Time in successful"
        ]);
    }

    public function timeOut(Request $request)
    {
        $user = $request->user();
        $today = DB::table('tblattendancerecord')->where("EmployeeID", $user->EmployeeID)->where('date', Date::today())->get();

        if ($today == null) {
            $newAttendance = DB::table('tblattendancerecord')->insertGetId([
                'EmployeeID' => $user->EmployeeID,
                'date' => Date::today()
            ]);
            $today = DB::table('tblattendancerecord')->where("id", $newAttendance)->get();
        }

        // check current time
        $employeeWorkShift = DB::table('tblemployeeworkshift')->where("EmployeeID", $user->EmployeeID)->first('id');
        $workShift = DB::table('tblworkshift')->where('id', $employeeWorkShift->id)->first();

        $currentTime = Date::now()->format("H:i");

        // time in/time out before start of second segment (ex. needs to time in/time out before end of lunch break)
        if ($currentTime <= $workShift->end_break_time) {
            DB::table('tblattendancerecord')->where("EmployeeID", $user->EmployeeID)->where('date', Date::today())->update([
                'time_out_session1' => $currentTime
            ]);

        } else if ($currentTime > $workShift->end_break_time) {
            DB::table('tblattendancerecord')->where("EmployeeID", $user->EmployeeID)->where('date', Date::today())->update([
                'time_out_session2' => $currentTime
            ]);
        }

        return response()->json([
            'Message' => "Time out successful"
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $attendance = DB::table("tblattendancerecord")->find($id);

        if ($attendance == null) {
            return response()->json([
                'errors' => [
                    'Attendance' => ['Attendance does not exist']
                ]
            ], 404);
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
            "time_in_session1" => "nullable|date_format:H:i",
            "time_out_session1" => "nullable|date_format:H:i",
            "time_in_session2" => "nullable|date_format:H:i",
            "time_out_session2" => "nullable|date_format:H:i"
        ]);

        $attendance = DB::table("tblattendancerecord")->find($id);
        $employee = DB::table('tblemployee')->where('id', $attendance->EmployeeID)->first();

        if ($request->user()->roleID == RoleEnum::SUPERVISOR->value) {
            if ($employee->SupervisorID != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['Access not allowed']
                    ]
                ], 404);
            }
        }

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($attendance->EmployeeID != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['You cannot update other\'s attendance records']
                    ]
                ], 404);
            }
        }


        if ($attendance == null) {
            return response()->json([
                'errors' => [
                    'Attendance' => ['Attendance does not exist']
                ]
            ], 404);
        }

        DB::table('tblattendancerecord')->where('id', $id)->update([
            'time_in_session1' => $request->time_in_session1,
            'time_out_session1' => $request->time_out_session1,
            'time_in_session2' => $request->time_in_session2,
            'time_out_session2' => $request->time_out_session2
        ]);

        return response()->json([
            'message' => "Attendance Updated successfully"
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $attendance = DB::table("tblattendancerecord")->find($id);

        if ($attendance == null) {
            return response()->json([
                'errors' => [
                    'Attendance' => ['Attendance does not exist']
                ]
            ], 404);
        }

        DB::table('tblattendancerecord')->where('id', $id)->delete();

        return response()->json([
            'message' => "Attendance Deleted successfully"
        ], 201);
    }

    protected function getEmployeeAbsencesByDateRange($employeeID, $startDate, $endDate)
    {

        // date range
        $attendances = DB::table('tblattendance')->where($employeeID)->where('date', ">=", $startDate)->where('date', "<=", $endDate)->get();

        // get days with no attendance record
        $absences = [];
        $currentAttendance = $attendances[0];
        foreach ($attendances as $attendance) {
            // TODO: CHECK LEAVES AND HOLIDAYS
            if ($attendance->date != $currentAttendance->date) {
                $currentAttendance = $attendance;
            }

            // compare if there is a gap between attendance records (later date - earlier date > 1)
            if (Date::format("Y-m-d", $attendance->date)->diffInDays(Date::format("Y-m-d", $currentAttendance->date)) > 1) {
                // start from the day after the earlier attendance record
                $date = Date::parse($currentAttendance->date)->addDay();
                // get all dates between the two attendance records gap
                while ($date->lt(Date::parse($attendance->date))) {
                    $absences[] = $date->format('Y-m-d');
                    $date = $date->addDay();
                }
            }
        }

        return $absences;
    }
}

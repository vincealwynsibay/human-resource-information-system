<?php

namespace App\Http\Controllers;

use App\Enum\OvertimeRequestEnum;
use App\Enum\RoleEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class OvertimeController extends Controller
{

    public function index(Request $request)
    {

        $validated = $request->validate([
            'employeeId' => 'nullable|exists:tblemployee,id',
            'date' => 'nullable|date',
            'status' => 'nullable|string'
        ]);

        if ($request->user()->RoleID == RoleEnum::EMPLOYEE) {
            $assignedEmployees = DB::table('tblovertime')
                ->join('tblemployee', 'tblovertime.EmployeeID', '=', 'tblemployee.id')
                ->join('tblemployeesupervisor', 'tblemployeesupervisor.SubordinateID', '=', 'tblemployee.id')
                ->where('tblemployeesupervisor.SupervisorID', $request->user()->EmployeeID)
                ->when($request->employeeId, function ($query, $employeeId) {
                    return $query->where('EmployeeID', $employeeId);
                })
                ->when($request->date, function ($query, $date) {
                    return $query->whereDate('start_date', '==', $date);
                })
                ->when($request->status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->get();
        } else {
            $overtimeRequests = DB::table('tblovertime')
                ->when($request->employeeId, function ($query, $employeeId) {
                    return $query->where('EmployeeID', $employeeId);
                })
                ->when($request->date, function ($query, $date) {
                    return $query->whereDate('start_date', '==', $date);
                })
                ->when($request->status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->get();
        }

        return response()->json($overtimeRequests);
    }


    public function getOwnOvertime(Request $request)
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
            'status' => 'nullable|string',
        ]);

        $overtimes = DB::table('tblovertime')->where('EmployeeID', $request->user()->EmployeeID)
            ->when($request->date, function ($query, $date) {
                return $query->whereDate('start_date', '==', $date);
            })
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->get();
        return response()->json($overtimes);
    }

    public function applyForOvertime(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployee,id',
            'date' => 'required|date',
            'reason' => 'required|string',
        ]);


        // no of days between start and end date
        $requestedDate = Date::createFromFormat('Y-m-d', $validated['date']);

        $overtime = DB::table('tblovertime')->insert([
            'EmployeeID' => $validated['employeeId'],
            'requested_date' => $requestedDate,
            'reason' => $validated['reason'],
            'status' => OvertimeRequestEnum::PENDING,
        ]);

        return response()->json($overtime);
    }


    public function approveOvertime(Request $request)
    {
        $validated = $request->validate([
            'overtimeId' => 'required|exists:tblovertime,id',
        ]);

        DB::table('tblovertime')->where('id', $validated['overtimeId'])->update([
            'status' => OvertimeRequestEnum::APPROVED,
        ]);

        $overtime = DB::table('tblovertime')->where('id', $validated['overtimeId'])->first();

        return response()->json($overtime);
    }
    public function rejectOvertime(Request $request)
    {
        $validated = $request->validate([
            'overtimeId' => 'required|exists:tblovertime,id',
        ]);

        DB::table('tblovertime')->where('id', $validated['overtimeId'])->update([
            'status' => OvertimeRequestEnum::REJECTED,
        ]);

        $overtime = DB::table('tblovertime')->where('id', $validated['overtimeId'])->first();

        return response()->json($overtime);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'requestedDate' => 'required|date',
            'reason' => 'required|string',
        ]);

        $overtime = DB::table('tblovertime')->where('id', $id)->update([
            'requested_date' => $validated['requestedDate'],
            'reason' => $validated['reason'],
        ]);

        return response()->json($overtime);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $overtime = DB::table('tblovertime')->where('id', $id)->delete();
        return response()->json($overtime);
    }
}

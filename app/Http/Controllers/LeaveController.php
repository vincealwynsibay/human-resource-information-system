<?php

namespace App\Http\Controllers;

use App\Enum\LeaveRequestEnum;
use App\Enum\RoleEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'nullable|exists:tblemployee,id',
            'fromDate' => 'nullable|date',
            'toDate' => 'nullable|date',
            'status' => 'nullable|string',
            'leaveType' => 'nullable|exists:tblleavetype,id',
        ]);

        if ($request->user()->RoleID == RoleEnum::EMPLOYEE->value) {
            $leaveRequests = DB::table('tblleave')
                ->join('tblemployee', 'tblleave.EmployeeID', '=', 'tblemployee.id')
                ->join('tblemployeesupervisor', 'tblemployeesupervisor.SubordinateID', '=', 'tblemployee.id')
                ->where('tblemployeesupervisor.SupervisorID', $request->user()->EmployeeID)
                ->when($request->employeeId, function ($query, $employeeId) {
                    return $query->where('EmployeeID', $employeeId);
                })
                ->when($request->fromDate, function ($query, $fromDate) {
                    return $query->whereDate('start_date', '>=', $fromDate);
                })
                ->when($request->toDate, function ($query, $toDate) {
                    return $query->whereDate('end_date', '<=', $toDate);
                })
                ->when($request->status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->when($request->leaveType, function ($query, $leaveType) {
                    return $query->where('LeaveTypeID', $leaveType);
                })
                ->get();
        } else {
            $leaveRequests = DB::table('tblleave')
                ->when($request->employeeId, function ($query, $employeeId) {
                    return $query->where('EmployeeID', $employeeId);
                })
                ->when($request->fromDate, function ($query, $fromDate) {
                    return $query->whereDate('start_date', '>=', $fromDate);
                })
                ->when($request->toDate, function ($query, $toDate) {
                    return $query->whereDate('end_date', '<=', $toDate);
                })
                ->when($request->status, function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->when($request->leaveType, function ($query, $leaveType) {
                    return $query->where('LeaveTypeID', $leaveType);
                })
                ->get();
        }


        return response()->json($leaveRequests);
    }

    public function getOwnLeave(Request $request)
    {
        $validated = $request->validate([
            'fromDate' => 'nullable|date',
            'toDate' => 'nullable|date',
            'status' => 'nullable|string',
            'leaveType' => 'nullable|exists:tblleavetype,id',
        ]);

        $leaves = DB::table('tblleave')->where('EmployeeID', $request->user()->EmployeeID)
            ->when($request->fromDate, function ($query, $fromDate) {
                return $query->whereDate('start_date', '>=', $fromDate);
            })
            ->when($request->toDate, function ($query, $toDate) {
                return $query->whereDate('end_date', '<=', $toDate);
            })
            ->when($request->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->leaveType, function ($query, $leaveType) {
                return $query->where('LeaveTypeID', $leaveType);
            })
            ->get();
        return response()->json($leaves);
    }


    public function applyForLeave(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployee,id',
            'leaveType' => 'nullable',
            'startDate' => 'required|date',
            'endDate' => 'required|date',
            'reason' => 'required|string',
        ]);


        // no of days between start and end date
        $startDate = Date::createFromFormat('Y-m-d', $validated['startDate']);
        $endDate = Date::createFromFormat('Y-m-d', $validated['endDate']);

        // if leave type null means unpaid leave
        if ($request->leaveType == null) {
            $leave = DB::table('tblleave')->insert([
                'EmployeeID' => $validated['employeeId'],
                'LeaveTypeID' => null,
                'start_date' => $validated['startDate'],
                'end_date' => $validated['endDate'],
                'reason' => $validated['reason'],
                'status' => LeaveRequestEnum::PENDING,
                'no_of_days' => $startDate->diffInDays($endDate),
            ]);

            return response()->json($leave);
        }

        $leaveType = DB::table('tblleavetype')->where('id', $validated['leaveType'])->first();

        if ($leaveType == null) {
            return response()->json(['error' => 'Leave Type not found'], 400);
        }

        // TODO: SETUP FOR DEFAULT LEAVE ENTITLEMENT
        // check if have leave entitlement for leave type
        $leaveEntitlement = DB::table('tblleaveentitlement')->where('EmployeeID', $validated['employeeId'])->where('LeaveTypeID', $validated['leaveType'])->first();
        if (!$leaveEntitlement) {
            return response()->json(['error' => 'Leave Type not allowed. No leave entitlement'], 400);
        }

        // check if leave is valid, based on entitlement
        $leaveEntitlement = DB::table('tblleaveentitlement')->where('EmployeeID', $validated['employeeId'])->where('LeaveTypeID', $validated['leaveType'])->first();
        if (($leaveEntitlement->entitlement - $leaveEntitlement->days_used) < $startDate->diffInDays($endDate)) {
            return response()->json(['error' => 'Not enough leave days left'], 400);
        }

        $leave = DB::table('tblleave')->insert([
            'EmployeeID' => $validated['employeeId'],
            'LeaveTypeID' => $validated['leaveType'],
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
            'reason' => $validated['reason'],
            'status' => LeaveRequestEnum::PENDING,
            'no_of_days' => $startDate->diffInDays($endDate),
        ]);

        return response()->json($leave);
    }

    public function approveLeave(Request $request)
    {
        $validated = $request->validate([
            'leaveId' => 'required|exists:tblleave,id',
        ]);

        DB::table('tblleave')->where('id', $validated['leaveId'])->update([
            'status' => LeaveRequestEnum::APPROVED,
        ]);

        $leave = DB::table('tblleave')->where('id', $validated['leaveId'])->first();

        // get leave entitlement and update days used
        $leaveEntitlement = DB::table('tblleaveentitlement')->where('EmployeeID', $leave->EmployeeID)->where('LeaveTypeID', $leave->LeaveTypeID)->first();
        $leaveEntitlement->days_used += $leave->no_of_days;
        $leaveEntitlement->save();

        return response()->json($leave);
    }

    public function rejectLeave(Request $request)
    {
        $validated = $request->validate([
            'leaveId' => 'required|exists:tblleave,id',
        ]);

        $leave = DB::table('tblleave')->where('id', $validated['leaveId'])->update([
            'status' => LeaveRequestEnum::REJECTED,
        ]);

        return response()->json($leave);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'startDate' => 'required|date',
            'endDate' => 'required|date',
            'reason' => 'required|string',
        ]);

        $leave = DB::table('tblleave')->where('id', $id)->update([
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
            'reason' => $validated['reason'],
        ]);

        return response()->json($leave);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $leave = DB::table('tblleave')->where('id', $id)->delete();
        return response()->json($leave);
    }
}

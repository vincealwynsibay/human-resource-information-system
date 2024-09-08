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
            $leaveRequests = DB::select('CALL procGetEmployeeLeavesAsSupervisor(?, ?, ?, ?, ?, ?)', [
                $request->user()->EmployeeID,
                $request->employeeId,
                $request->leaveType,
                $request->fromDate,
                $request->toDate,
                $request->status
            ]);
        } else {
            $leaveRequests = DB::select('CALL procGetEmployeeLeavesAsAdmin(?, ?, ?, ?, ?)', [
                $request->employeeId,
                $request->leaveType,
                $request->fromDate,
                $request->toDate,
                $request->status
            ]);
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

        $leaves = DB::select('CALL procGetOwnEmployeeLeaves(?, ?, ?, ?, ?)', [
            $request->user()->EmployeeID,
            $request->leaveType,
            $request->fromDate,
            $request->toDate,
            $request->status
        ]);

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
        $startDate = Date::createFromFormat('Y-m-d', $request->startDate);
        $endDate = Date::createFromFormat('Y-m-d', $request->endDate);

        // if leave type null means unpaid leave
        if ($request->leaveType == null) {
            DB::select('CALL procApplyForLeave(?, ?, ?, ?, ?, ?, ?)', [
                $request->employeeId,
                null,
                $request->startDate,
                $request->endDate,
                $request->reason,
                LeaveRequestEnum::PENDING,
                $startDate->diffInDays($endDate)
            ]);

            return response()->json(['message' => 'Leave applied successfully']);
        }

        $leaveType = DB::select('CALL procGetLeaveType(?)', [$request->leaveType])[0] ?? null;

        if ($leaveType == null) {
            return response()->json(['error' => 'Leave Type not found'], 400);
        }

        // check if have leave entitlement for leave type
        $leaveEntitlement = DB::select('CALL procGetEmployeeLeaveEntitlementsByLeaveType(?, ?)', [
            $request->employeeId,
            $request->leaveType
        ])[0] ?? null;

        if (!$leaveEntitlement) {
            return response()->json(['error' => 'Leave Type not allowed. No leave entitlement'], 400);
        }

        // check if leave is valid, based on entitlement
        if (($leaveEntitlement->entitlement - $leaveEntitlement->days_used) < $startDate->diffInDays($endDate)) {
            return response()->json(['error' => 'Not enough leave days left'], 400);
        }

        DB::select('CALL procApplyForLeave(?, ?, ?, ?, ?, ?, ?)', [
            $request->employeeId,
            $request->leaveType,
            $request->startDate,
            $request->endDate,
            $request->reason,
            LeaveRequestEnum::PENDING->value,
            $startDate->diffInDays($endDate)
        ]);

        return response()->json(['message' => 'Leave applied successfully']);
    }

    public function approveLeave(Request $request)
    {
        $validated = $request->validate([
            'leaveId' => 'required|exists:tblleave,id',
        ]);

        DB::select('CALL procApproveLeave(?, ?)', [
            $request->leaveId,
            $request->user()->EmployeeID
        ]);

        $leave = DB::select('CALL procGetLeave(?)', [$request->leaveId])[0] ?? null;

        // get leave entitlement and update days used
        $leaveEntitlement = DB::select('CALL procGetEmployeeLeaveEntitlementsByLeaveType(?, ?)', [
            $leave->EmployeeID,
            $leave->LeaveTypeID
        ])[0] ?? null;

        if (!$leaveEntitlement) {
            return response()->json(['error' => 'Leave Type not allowed. No leave entitlement'], 400);
        }

        DB::select('CALL procUpdateLeaveEntitlementDaysUsed(?, ?)', [
            $leaveEntitlement->id,
            $leaveEntitlement->days_used + $leave->no_of_days
        ]);

        return response()->json($leave);
    }

    public function rejectLeave(Request $request)
    {
        $validated = $request->validate([
            'leaveId' => 'required|exists:tblleave,id',
        ]);

        DB::select('CALL procRejectLeave(?, ?)', [
            $request->leaveId,
            $request->user()->EmployeeID
        ]);

        return response()->json(['message' => 'Leave rejected successfully']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date',
            'reason' => 'nullable|string',
        ]);

        DB::select('CALL procUpdateLeaveByID(?, ?, ?, ?)', [
            $id,
            $request->startDate,
            $request->endDate,
            $request->reason
        ]);

        return response()->json(['message' => 'Leave updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::select('CALL procDeleteLeaveByID(?)', [$id]);
        return response()->json(['message' => 'Leave deleted successfully']);
    }
}
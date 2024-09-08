<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class LeaveEntitlementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            "name" => "nullable|string|max:200",
            'leaveTypeId' => "nullable|exists:tblleavetype,id",
            "leaveValidFrom" => "nullable|date",
            "leaveValidTo" => "nullable|date",
        ]);

        $leaveEntitlements = DB::select('CALL procGetEmployeeLeaveEntitlements(?, ?, ?, ?)', [
            $request->name,
            $request->leaveTypeId,
            $request->leaveValidFrom,
            $request->leaveValidTo
        ]);

        return response()->json($leaveEntitlements);
    }

    public function getUserLeaveEntitlements(Request $request)
    {
        $user = $request->user();

        $leaveEntitlements = DB::select('CALL procGetUserLeaveEntitlements(?)', [$user->EmployeeID]);

        return response()->json($leaveEntitlements);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployee,id',
            'leaveTypeId' => 'required|exists:tblleavetype,id',
            'entitlement' => 'required|numeric',
            'validFrom' => 'required|date',
            'validTo' => 'nullable|date',
        ]);

        DB::select('CALL procInsertLeaveEntitlement(?, ?, ?, ?, ?)', [
            $request->employeeId,
            $request->leaveTypeId,
            $request->entitlement,
            $request->validFrom,
            $request->validTo
        ]);

        return response()->json(['message' => "Leave entitlement created successfully"]);
    }

    public function storeMany(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployee,id',
            'leaveEntitlements' => 'required|array',
            'leaveEntitlements.*.leaveTypeId' => 'required|exists:tblleavetype,id',
            'leaveEntitlements.*.entitlement' => 'required|numeric',
            'leaveEntitlements.*.validFrom' => 'required|date',
            'leaveEntitlements.*.validTo' => 'nullable|date',
        ]);

        foreach ($request->leaveEntitlements as $leaveEntitlement) {
            DB::select('CALL procInsertLeaveEntitlement(?, ?, ?, ?, ?)', [
                $request->employeeId,
                $leaveEntitlement['leaveTypeId'],
                $leaveEntitlement['entitlement'],
                $leaveEntitlement['validFrom'],
                $leaveEntitlement['validTo']
            ]);
        }

        return response()->json(['message' => "Leave entitlements assigned successfully"]);
    }

    public function assignLeaveEntitlements(Request $request)
    {
        $validated = $request->validate([
            'employeeIds' => 'required|array',
            'employeeIds.*' => 'exists:tblemployee,id',
            'leaveEntitlements' => 'required|array',
            'leaveEntitlements.*.leaveTypeId' => 'required|exists:tblleavetype,id',
            'leaveEntitlements.*.entitlement' => 'required|numeric',
            'leaveEntitlements.*.validFrom' => 'required|date',
            'leaveEntitlements.*.validTo' => 'nullable|date',
        ]);

        foreach ($request->employeeIds as $employeeId) {
            foreach ($request->leaveEntitlements as $leaveEntitlement) {
                DB::select('CALL procAssignLeaveEntitlement(?, ?, ?, ?, ?)', [
                    $employeeId,
                    $leaveEntitlement['leaveTypeId'],
                    $leaveEntitlement['entitlement'],
                    $leaveEntitlement['validFrom'],
                    $leaveEntitlement['validTo']
                ]);
            }
        }

        return response()->json(['message' => "Leave entitlements assigned successfully"]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'entitlement' => 'nullable|numeric',
            'validFrom' => 'nullable|date',
            'validTo' => 'nullable|date',
        ]);

        DB::select('CALL procUpdateLeaveEntitlementByID(?, ?, ?, ?, ?)', [
            $id,
            $request->leaveTypeId,
            $request->entitlement,
            $request->validFrom,
            $request->validTo
        ]);

        return response()->json(['message' => "Leave entitlement updated successfully"]);
    }

    public function updateDaysUsed(Request $request, string $id)
    {
        $validated = $request->validate([
            'daysUsed' => 'required|numeric',
        ]);

        DB::select('CALL procUpdateLeaveEntitlementDaysUsed(?, ?)', [
            $id,
            $request->daysUsed
        ]);

        return response()->json(['message' => "Leave days used updated successfully"]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::select('CALL procDeleteLeaveEntitlementByID(?)', [$id]);

        return response()->json(['message' => "Leave entitlement deleted successfully"]);
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

        $leaveEntitlements = DB::table('tblleaveentitlement')
            ->when($request->name, function ($query, $name) {
                return $query->whereLike(DB::raw("CONCAT(`firstname`, ' ', `lastname`)"), $name);
            })
            ->when($request->leaveTypeId, function ($query, $leaveTypeId) {
                return $query->where('LeaveTypeID', $leaveTypeId);
            })
            ->when($request->leaveValidFrom, function ($query, $leaveValidFrom) {
                return $query->whereDate('valid_from', ">=", $leaveValidFrom);
            })
            ->when($request->leaveValidTo, function ($query, $leaveValidTo) {
                return $query->whereDate('valid_to', "<=", $leaveValidTo);
            })
            ->get();

        return response()->json($leaveEntitlements);
    }

    public function getUserLeaveEntitlements(Request $request)
    {
        $user = $request->user();

        $leaveEntitlements = DB::table('tblleaveentitlement')->where('EmployeeID', $user->EmployeeID)->get();

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


        $id = DB::table('tblleaveentitlement')->insert([
            'EmployeeID' => $request->employeeId,
            'LeaveTypeID' => $request->leaveTypeId,
            'entitlement' => $request->entitlement,
            'valid_from' => $request->validFrom,
            'valid_to' => $request->validTo,
            'date_created' => now(),
        ]);

        return response()->json(['message' => "Leave entitlement created successfully", 'id' => $id]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'entitlement' => 'required|numeric',
            'validFrom' => 'required|date',
            'validTo' => 'nullable|date',
        ]);

        DB::table('tblleaveentitlement')->where('id', $id)->update([
            'entitlement' => $request->entitlement,
            'valid_from' => $request->validFrom,
            'valid_to' => $request->validTo,
        ]);

        return response()->json(['message' => "Leave entitlement updated successfully"]);
    }

    public function updateDaysUsed(Request $request, string $id)
    {
        $validated = $request->validate([
            'daysUsed' => 'required|numeric',
        ]);

        DB::table('tblleaveentitlement')->where('id', $id)->update([
            'days_used' => $request->daysUsed,
        ]);

        return response()->json(['message' => "Leave days used updated successfully"]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::table('tblleaveentitlement')->where('id', $id)->delete();

        return response()->json(['message' => "Leave entitlement deleted successfully"]);
    }
}

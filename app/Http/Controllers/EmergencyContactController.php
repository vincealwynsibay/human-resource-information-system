<?php

namespace App\Http\Controllers;

use App\Enum\RoleEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmergencyContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $employeeId)
    {
        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->RoleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $employee = DB::select('CALL procGetEmployee(?)', [$employeeId])[0] ?? null;

        if ($employee == null) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        $emergencyContacts = DB::select('CALL procGetEmployeeEmergencyContacts(?)', [$employeeId]);
        return response()->json($emergencyContacts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'relationship' => 'required|string',
            'mobile_no' => 'required|string',
            'email' => 'required|email'
        ]);

        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->RoleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::select('CALL procInsertEmployeeEmergencyContact(?, ?, ?, ?, ?)', [
            $employeeId,
            $validated['name'],
            $validated['relationship'],
            $validated['mobile_no'],
            $validated['email']
        ]);

        return response()->json("New Emergency Contact has been added to Employee", 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $employeeId, string $emergencyContactId)
    {
        $validated = $request->validate([
            'name' => 'nullable|string',
            'relationship' => 'nullable|string',
            'mobile_no' => 'nullable|string',
            'email' => 'nullable|email'
        ]);

        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->RoleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $emergencyContact = DB::select('CALL procGetEmployeeEmergencyContact(?)', [$emergencyContactId])[0] ?? null;

        if ($emergencyContact == null) {
            return response()->json([
                'message' => 'Emergency contact not found'
            ], 404);
        }

        DB::select('CALL procUpdateEmployeeEmergencyContactByID(?, ?, ?, ?, ?)', [
            $emergencyContactId,
            $validated['name'],
            $validated['relationship'],
            $validated['mobile_no'],
            $validated['email']
        ]);

        return response()->json([
            'message' => 'Emergency contact updated'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $employeeId, string $emergencyContactId)
    {
        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->RoleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $emergencyContact = DB::select('CALL procGetEmployeeEmergencyContact(?)', [$emergencyContactId])[0] ?? null;

        if ($emergencyContact == null) {
            return response()->json([
                'message' => 'Emergency contact not found'
            ], 404);
        }

        DB::select('CALL procDeleteEmployeeEmergencyContactByID(?)', [$emergencyContactId]);
        return response()->json([
            'message' => 'Emergency contact deleted'
        ]);
    }
}
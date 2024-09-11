<?php

namespace App\Http\Controllers;

use App\Enum\RoleEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CertificateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $employeeId)
    {
        $employee = DB::select('CALL procGetEmployee(?)', [$employeeId])[0] ?? null;

        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employee->id
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($employee == null) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        $certificates = DB::select('CALL procGetEmployeeCertificates(?)', [$employeeId]);
        return response()->json($certificates);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'issuedBy' => 'required|string',
            'issuedDate' => 'required|date',
        ]);
        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::select('CALL procInsertEmployeeCertificate(?, ?, ?, ?)', [
            $employeeId,
            $request->name,
            $request->issuedBy,
            $request->issuedDate
        ]);

        return response()->json("New Certificate Created Successfully", 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $employeeId, string $certificateId)
    {
        $validated = $request->validate([
            'name' => 'nullable|string',
            'issuedBy' => 'nullable|string',
            'issuedDate' => 'nullable|date',
        ]);

        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $certificate = DB::select('CALL procGetEmployeeCertificate(?)', [$certificateId]);


        if ($certificate == null) {
            return response()->json([
                'message' => 'Certificate not found'
            ], 404);
        }

        DB::select('CALL procUpdateEmployeeCertificateByID(?, ?, ?, ?)', [
            $certificateId,
            $request->name,
            $request->issuedBy,
            $request->issuedDate
        ]);

        return response()->json([
            'message' => 'Certificate updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $employeeId, string $certificateId)
    {
        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $certificate = DB::select('CALL procGetEmployeeCertificate(?)', [$certificateId])[0] ?? null;

        if ($certificate == null) {
            return response()->json([
                'message' => 'Certificate not found'
            ], 404);
        }

        DB::select('CALL procDeleteEmployeeCertificateByID(?)', [$certificateId]);
        return response()->json([
            'message' => 'Certificate deleted successfully'
        ]);
    }
}
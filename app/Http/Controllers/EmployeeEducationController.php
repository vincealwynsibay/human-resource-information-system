<?php

namespace App\Http\Controllers;

use App\Enum\RoleEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeEducationController extends Controller
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

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $employee = DB::select('CALL procGetEmployee(?)', [$employeeId])[0] ?? null;

        if ($employee == null) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        $educations = DB::select('CALL procGetEmployeeEducations(?)', [$employeeId]);
        return response()->json($educations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'education' => 'required|exists:tbleducation,id',
            'institute' => 'required|string',
            'major' => 'required|string',
            'yearGraduated' => 'required|digits:4|integer|min:1900|max:' . (date('Y') + 1),
            'gpa' => 'required|numeric',
            'startDate' => 'required|date',
            'endDate' => 'required|date',
        ]);

        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::select('CALL procInsertEmployeeEducation(?, ?, ?, ?, ?, ?, ?, ?)', [
            $employeeId,
            $request->education,
            $request->institute,
            $request->major,
            $request->yearGraduated,
            $request->gpa,
            $request->startDate,
            $request->endDate
        ]);

        return response()->json("New Education has been added to Employee", 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $employeeId, string $educationId)
    {
        $validated = $request->validate([
            'education' => 'nullable|exists:tbleducation,id',
            'institute' => 'nullable|string',
            'major' => 'nullable|string',
            'yearGraduated' => 'nullable|digits:4|integer|min:1900|max:' . (date('Y') + 1),
            'gpa' => 'nullable|numeric',
            'startDate' => 'nullable|date',
            'endDate' => 'nullable|date',
        ]);

        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::select('CALL procUpdateEmployeeEducationByID(?, ?, ?, ?, ?, ?, ?, ?)', [
            $educationId,
            $request->education,
            $request->institute,
            $request->major,
            $request->yearGraduated,
            $request->gpa,
            $request->startDate,
            $request->endDate
        ]);

        $education = DB::select('CALL procGetEmployeeEducation(?)', [$educationId])[0];
        return response()->json($education);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $employeeId, string $educationId)
    {
        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $education = DB::select('CALL procGetEmployeeEducation(?)', [$educationId])[0] ?? null;

        if ($education == null) {
            return response()->json([
                'message' => 'Education not found'
            ], 404);
        }

        DB::select('CALL procDeleteEmployeeEducationByID(?)', [$educationId]);
        return response()->json([
            'message' => 'Education deleted successfully'
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Enum\RoleEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkExperienceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, $employeeId)
    {
        $employee = DB::select('CALL procGetEmployee(?)', [$employeeId])[0] ?? null;

        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($employee == null) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        $workExperiences = DB::select('CALL procGetEmployeeWorkExperiences(?)', [$employeeId]);
        return response()->json($workExperiences);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'employer' => 'required|string',
            'position' => 'required|string',
            'from' => 'required|date',
            'to' => 'required|date',
        ]);
        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::select('CALL procInsertEmployeeWorkExperience(?, ?, ?, ?, ?)', [
            $employeeId,
            $validated['employer'],
            $validated['position'],
            $validated['from'],
            $validated['to']
        ]);

        return response()->json("New Work Experience has been added to employee", 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $employeeId, string $workExperienceId)
    {
        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $validated = $request->validate([
            'employer' => 'nullable|string',
            'position' => 'nullable|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $workExperience = DB::select('CALL procGetEmployeeWorkExperience(?)', [$workExperienceId])[0] ?? null;

        if ($workExperience == null) {
            return response()->json([
                'message' => 'Work experience not found'
            ], 404);
        }

        DB::select('CALL procUpdateEmployeeWorkExperienceByID(?, ?, ?, ?, ?)', [
            $workExperienceId,
            $validated['employer'],
            $validated['position'],
            $validated['from'],
            $validated['to']
        ]);

        return response()->json([
            'message' => 'Work experience updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $employeeId, string $workExperienceId)
    {
        $subordinate = DB::select('CALL procGetEmployeeSupervisor(?, ?)', [
            $request->user()->EmployeeID,
            $employeeId
        ])[0] ?? null;

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value && !$subordinate && $request->user()->EmployeeID != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $workExperience = DB::select('CALL procGetEmployeeWorkExperience(?)', [$workExperienceId])[0] ?? null;

        if ($workExperience == null) {
            return response()->json([
                'message' => 'Work experience not found'
            ], 404);
        }

        DB::select('CALL procDeleteEmployeeWorkExperienceByID(?)', [$workExperienceId]);
        return response()->json([
            'message' => 'Work experience deleted successfully'
        ]);
    }
}
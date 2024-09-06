<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeEducationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($employeeId)
    {
        $employee = DB::table('tblemployee')->where('id', $employeeId)->first();

        if ($employee == null) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        $educations = DB::table('tblemployeeeducation')->where('employeeId', $employeeId)->get();
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

        $educationId = DB::table('tblemployeeeducation')->insertGetId([
            'employeeId' => $employeeId,
            'educationId' => $validated['education'],
            'institute' => $validated['institute'],
            'major' => $validated['major'],
            'year_graduated' => $validated['yearGraduated'],
            'gpa' => $validated['gpa'],
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
        ]);

        $education = DB::table('tblemployeeeducation')->where('id', $educationId)->first();

        return response()->json($education, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id, string $educationId)
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

        DB::table('tblemployeeeducation')->where('id', $educationId)->update([
            'educationId' => $validated['education'],
            'institute' => $validated['institute'],
            'major' => $validated['major'],
            'year_graduated' => $validated['yearGraduated'],
            'gpa' => $validated['gpa'],
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
        ]);

        $education = DB::table('tblemployeeeducation')->where('id', $educationId)->first();
        return response()->json($education);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, string $educationId)
    {
        $education = DB::table('tblemployeeeducation')->where('id', $educationId)->first();

        if ($education == null) {
            return response()->json([
                'message' => 'Education not found'
            ], 404);
        }

        DB::table('tblemployeeeducation')->where('id', $educationId)->delete();
        return response()->json([
            'message' => 'Education deleted successfully'
        ]);
    }
}

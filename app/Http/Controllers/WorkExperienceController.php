<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkExperienceController extends Controller
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

        $workExperiences = DB::table('tblemployeeworkexperience')->where('employeeId', $employeeId)->get();
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

        $workExperienceId = DB::table('tblemployeeworkexperience')->insertGetId([
            'employeeId' => $employeeId,
            'employer' => $validated['employer'],
            'position' => $validated['position'],
            'from_date' => $validated['from'],
            'to_date' => $validated['to'],
        ]);

        $workExperience = DB::table('tblemployeeworkexperience')->where('id', $workExperienceId)->first();
        return response()->json($workExperience, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id, string $workExperienceId)
    {
        $validated = $request->validate([
            'employer' => 'nullable|string',
            'position' => 'nullable|string',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $workExperience = DB::table('tblemployeeworkexperience')->where('id', $workExperienceId)->first();

        if ($workExperience == null) {
            return response()->json([
                'message' => 'Work experience not found'
            ], 404);
        }

        DB::table('tblemployeeworkexperience')->where('id', $workExperienceId)->update([
            'employer' => $validated['employer'],
            'position' => $validated['position'],
            'from_date' => $validated['from'],
            'to_date' => $validated['to'],
        ]);

        return response()->json([
            'message' => 'Work experience updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, string $workExperienceId)
    {
        $workExperience = DB::table('tblemployeeworkexperience')->where('id', $workExperienceId)->first();

        if ($workExperience == null) {
            return response()->json([
                'message' => 'Work experience not found'
            ], 404);
        }

        DB::table('tblemployeeworkexperience')->where('id', $workExperienceId)->delete();
        return response()->json([
            'message' => 'Work experience deleted successfully'
        ]);
    }
}

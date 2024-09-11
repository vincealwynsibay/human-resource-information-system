<?php

namespace App\Http\Controllers;

use App\Models\JobTitle;
use App\Http\Requests\StoreJobTitleRequest;
use App\Http\Requests\UpdateJobTitleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobTitleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $jobTitles = DB::select('CALL procGetJobTitles()');
        return response()->json($jobTitles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJobTitleRequest $request)
    {
        $validated = $request->validate([
            "name" => "required|string|max:45",
            "description" => "nullable|string|max:100",
        ]);

        DB::select('CALL procInsertJobTitle(?, ?)', [
            $request->name,
            $request->description
        ]);

        return response()->json([
            'message' => 'New Job Title created successfully'
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateJobTitleRequest $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:45',
            'description' => 'nullable|string|max:100',
        ]);

        $jobTitle = DB::select('CALL procGetJobTitle(?)', [$id])[0] ?? null;

        if ($jobTitle == null) {
            return response()->json([
                'Job Title' => ['Job Title does not exist']
            ], 404);
        }

        DB::select('CALL procUpdateJobTitleByID(?, ?, ?)', [
            $id,
            $request->name,
            $request->description
        ]);

        return response()->json([
            'message' => "Job Title Updated successfully"
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $jobTitle = DB::select('CALL procGetJobTitle(?)', [$id])[0] ?? null;

        if ($jobTitle == null) {
            return response()->json([
                'Job Title' => ['Job Title does not exist']
            ], 404);
        }

        DB::select('CALL procDeleteJobTitleByID(?)', [$id]);

        return response()->json([
            'message' => "Job Title deleted successfully"
        ], 200);
    }
}
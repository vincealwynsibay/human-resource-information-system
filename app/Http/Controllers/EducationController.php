<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EducationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $educations = DB::select('CALL procGetEducations()');
        return response()->json($educations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
        ]);

        DB::select('CALL procInsertEducation(?)', [
            $validated['name']
        ]);

        return response()->json("New Education Created Successfully", 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'nullable|string',
        ]);

        DB::select('CALL procUpdateEducationByID(?, ?)', [
            $id,
            $validated['name']
        ]);

        $education = DB::select('CALL procGetEducation(?)', [$id])[0];
        return response()->json($education);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::select('CALL procDeleteEducationByID(?)', [$id]);
        return response()->json([
            'message' => 'Education deleted successfully'
        ]);
    }
}
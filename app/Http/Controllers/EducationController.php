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
        $educations = DB::table('tbleducation')->get();
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

        $educationId = DB::table('tbleducation')->insertGetId([
            'name' => $validated['education'],
        ]);

        $education = DB::table('tbleducation')->where('id', $educationId)->first();
        return response()->json($education, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'nullable|string',
        ]);

        DB::table('tbleducation')->where('id', $id)->update([
            'name' => $validated['education'],
        ]);

        $education = DB::table('tbleducation')->where('id', $id)->first();
        return response()->json($education);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::table('tbleducation')->where('id', $id)->delete();
        return response()->json([
            'message' => 'Education deleted successfully'
        ]);
    }
}

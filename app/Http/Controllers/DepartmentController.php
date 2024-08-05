<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $departments = DB::table('tbldepartment')->get();
        return response()->json($departments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDepartmentRequest $request)
    {
        $validated = $request->validate([
            "name" => "required|string|max:45"
        ]);

        DB::table('tbldepartment')->insert([
            'name' => $validated['name']
        ]);

        return response()->json([
            'message' => 'New Department created successfully'
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentRequest $request)
    {
        $validated = $request->validate([
            'id' => "required|exists:tbldepartment,id",
            'name' => 'required|string|max:45'
        ]);

        DB::table('tbldepartment')->where('id', $validated['id'])->update([
            'name' => $validated['name']
        ]);

        return response()->json([
            'message' => "Department Updated successfully"
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'id' => "required|exists:tbldepartment,id"
        ]);

        DB::table('tbldepartment')->where('id', $validated['id'])->delete();

        return response()->json([
            'message' => "Department deleted successfully"
        ], 201);
    }
}

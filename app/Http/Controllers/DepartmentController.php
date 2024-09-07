<?php

namespace App\Http\Controllers;

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
        $departments = DB::select('CALL procGetDepartments()');
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

        DB::select('CALL procInsertDepartment(?)', [
            $validated['name']
        ]);

        return response()->json([
            'message' => 'New Department created successfully'
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentRequest $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:45'
        ]);

        $department = DB::select('CALL procGetDepartment(?)', [$id])[0] ?? null;

        if ($department == null) {
            return response()->json([
                'errors' => [
                    'Department' => ['Department does not exist']
                ]
            ], 404);
        }

        DB::select('CALL procUpdateDepartmentByID(?, ?)', [
            $id,
            $validated['name']
        ]);

        return response()->json([
            'message' => "Department Updated successfully"
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $department = DB::select('CALL procGetDepartment(?)', [$id])[0] ?? null;

        if ($department == null) {
            return response()->json([
                'errors' => [
                    'Department' => ['Department does not exist']
                ]
            ], 404);
        }

        DB::select('CALL procDeleteDepartmentByID(?)', [$id]);

        return response()->json([
            'message' => "Department deleted successfully"
        ], 200);
    }
}
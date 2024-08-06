<?php

namespace App\Http\Controllers;

use App\Models\EmploymentStatus;
use App\Http\Requests\StoreEmploymentStatusRequest;
use App\Http\Requests\UpdateEmploymentStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmploymentStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $employmentStatuses = DB::table('tblemploymentstatus')->get();
        return response()->json($employmentStatuses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmploymentStatusRequest $request)
    {
        $validated = $request->validate([
            "name" => "required|string|max:45"
        ]);

        DB::table('tblemploymentstatus')->insert([
            'name' => $validated['name']
        ]);

        return response()->json([
            'message' => 'New Employment Status created successfully'
        ], 201);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmploymentStatusRequest $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:45'
        ]);

        $employmentStatus = DB::table("tblemploymentstatus")->find($id);

        if ($employmentStatus == null) {
            return response()->json([
                'errors' => [
                    'EmploymentStatus' => ['EmploymentStatus does not exist']
                ]
            ], 404);
        }

        DB::table('tblemploymentstatus')->where('id', $id)->update([
            'name' => $validated['name']
        ]);

        return response()->json([
            'message' => "Employment Status Updated successfully"
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $employmentStatus = DB::table("tblemploymentstatus")->find($id);

        if ($employmentStatus == null) {
            return response()->json([
                'errors' => [
                    'EmploymentStatus' => ['EmploymentStatus does not exist']
                ]
            ], 404);
        }

        DB::table('tblemploymentstatus')->where('id', $id)->delete();

        return response()->json([
            'message' => "Employment Status deleted successfully"
        ], 200);
    }
}

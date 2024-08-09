<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $leaveTypes = DB::table('tblleavetype')->get();
        return response()->json($leaveTypes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            "name" => "required|string|max:50"
        ]);

        DB::table('tblleavetype')->insert([
            'name' => $validated['name']
        ]);

        return response()->json([
            'message' => 'New Leave Type created successfully'
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50'
        ]);

        $leaveType = DB::table("tblleavetype")->find($id);

        if ($leaveType == null) {
            return response()->json([
                'errors' => [
                    'Leave Type' => ['Leave Type does not exist']
                ]
            ], 404);
        }

        DB::table('tblleavetype')->where('id', $id)->update([
            'name' => $validated['name']
        ]);

        return response()->json([
            'message' => "Leave Type Updated successfully"
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $leaveType = DB::table("tblleavetype")->find($id);

        if ($leaveType == null) {
            return response()->json([
                'errors' => [
                    'Leave Type' => ['Leave Type does not exist']
                ]
            ], 404);
        }

        DB::table('tblleavetype')->where('id', $id)->delete();

        return response()->json([
            'message' => "Leave Type deleted successfully"
        ], 200);
    }
}

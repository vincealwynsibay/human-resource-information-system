<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkShiftRequest;
use App\Http\Requests\UpdateWorkShiftRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class WorkShiftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $workShifts = DB::table('tblworkshift')->get();
        return response()->json($workShifts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWorkShiftRequest $request)
    {
        $validated = $request->validate([
            "name" => "required|string|max:100",
            "hours_per_day" => "required|numeric",
            "start_time1" => "required|date_format:H:i",
            "end_time1" => "required|date_format:H:i|after:start_time1",
            "start_time2" => "nullable|date_format:H:i",
            "end_time2" => "nullable|date_format:H:i|after:start_time2"
        ]);

        DB::table('tblworkshift')->insert([
            'name' => $validated['name'],
            'hours_per_day' => $validated['hours_per_day'],
            'start_time1' => $validated['start_time1'],
            'end_time1' => $validated['end_time1'],
            'start_time2' => $request->start_time2,
            'end_time2' => $request->end_time2,
        ]);

        return response()->json([
            'message' => 'New Work Shift created successfully'
        ], 201);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWorkShiftRequest $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:45',
            "hours_per_day" => "required|numeric",
            "start_time1" => "required|date_format:H:i",
            "end_time1" => "required|date_format:H:i|after:start_time1",
            "start_time2" => "nullable|date_format:H:i",
            "end_time2" => "nullable|date_format:H:i|after:start_time2"
        ]);

        $workShift = DB::table("tblworkshift")->find($id);

        if ($workShift == null) {
            return response()->json([
                'errors' => [
                    'WorkShift' => ['WorkShift does not exist']
                ]
            ], 404);
        }

        DB::table('tblworkshift')->where('id', $id)->update([
            'name' => $validated['name'],
            'hours_per_day' => $validated['hours_per_day'],
            'start_time1' => $validated['start_time1'],
            'end_time1' => $validated['end_time1'],
            'start_time2' => $request->start_time2,
            'end_time2' => $request->end_time2
        ]);

        return response()->json([
            'message' => "Work Shift Updated successfully"
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $workShift = DB::table("tblworkshift")->find($id);

        if ($workShift == null) {
            return response()->json([
                'errors' => [
                    'WorkShift' => ['WorkShift does not exist']
                ]
            ], 404);
        }

        DB::table('tblworkshift')->where('id', $id)->delete();

        return response()->json([
            'message' => "Work Shift deleted successfully"
        ], 200);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HolidayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $holidays = DB::table('tblholiday')->get();
        return response()->json($holidays);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'date' => 'required|date',
            'is_full_day' => 'required|integer|between:0,2',
            'recurring' => 'required|boolean'
        ]);

        DB::table('tblholiday')->insert([
            'name' => $validated['name'],
            'date' => $validated['date'],
            'is_full_day' => $validated['is_full_day'],
            'recurring' => $validated['recurring']
        ]);

        return response()->json([
            'message' => 'New Holiday created successfully'
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'date' => 'required|date',
            'is_full_day' => 'required|boolean',
            'recurring' => 'required|boolean'
        ]);

        $holiday = DB::table('tblholiday')->find($id);

        if ($holiday == null) {
            return response()->json([
                'errors' => [
                    'Holiday' => ['Holiday does not exist']
                ]
            ], 404);
        }

        DB::table('tblholiday')->where('id', $id)->update([
            'name' => $validated['name'],
            'date' => $validated['date'],
            'is_full_day' => $validated['is_full_day'],
            'recurring' => $validated['recurring']
        ]);

        return response()->json([
            'message' => 'Holiday updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $holiday = DB::table('tblholiday')->find($id);

        if ($holiday == null) {
            return response()->json([
                'errors' => [
                    'Holiday' => ['Holiday does not exist']
                ]
            ], 404);
        }

        DB::table('tblholiday')->where('id', $id)->delete();

        return response()->json([
            'message' => 'Holiday deleted successfully'
        ]);
    }
}

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
        $holidays = DB::select('CALL procGetHolidays()');
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
            'is_recurring' => 'required|boolean'
        ]);

        DB::select('CALL procInsertHoliday(?, ?, ?, ?)', [
            $validated['name'],
            $validated['date'],
            $validated['is_full_day'],
            $validated['is_recurring']
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
            'is_recurring' => 'required|boolean'
        ]);

        $holiday = DB::select('CALL procGetHoliday(?)', [$id])[0] ?? null;

        if ($holiday == null) {
            return response()->json([
                'message' => ['Holiday does not exist']
            ], 404);
        }

        DB::select('CALL procUpdateHolidayByID(?, ?, ?, ?, ?)', [
            $id,
            $validated['name'],
            $validated['date'],
            $validated['is_full_day'],
            $validated['is_recurring']
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
        $holiday = DB::select('CALL procGetHoliday(?)', [$id])[0] ?? null;

        if ($holiday == null) {
            return response()->json([
                'message' => ['Holiday does not exist']
            ], 404);
        }

        DB::select('CALL procDeleteHolidayByID(?)', [$id]);

        return response()->json([
            'message' => 'Holiday deleted successfully'
        ]);
    }
}
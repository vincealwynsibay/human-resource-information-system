<?php

namespace App\Http\Controllers;

use App\Enum\DayLengthEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkWeekController extends Controller
{
    public function index(Request $request)
    {
        $workWeek = DB::select('CALL procGetWorkWeek()')[0] ?? null;
        return response()->json([
            'mon' => DayLengthEnum::from($workWeek->mon)->value,
            'tue' => DayLengthEnum::from($workWeek->tue)->value,
            'wed' => DayLengthEnum::from($workWeek->wed)->value,
            'thu' => DayLengthEnum::from($workWeek->thu)->value,
            'fri' => DayLengthEnum::from($workWeek->fri)->value,
            'sat' => DayLengthEnum::from($workWeek->sat)->value,
            'sun' => DayLengthEnum::from($workWeek->sun)->value,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'mon' => 'required|integer|min:0|max:2',
            'tue' => 'required|integer|min:0|max:2',
            'wed' => 'required|integer|min:0|max:2',
            'thu' => 'required|integer|min:0|max:2',
            'fri' => 'required|integer|min:0|max:2',
            'sat' => 'required|integer|min:0|max:2',
            'sun' => 'required|integer|min:0|max:2',
        ]);

        $workWeek = DB::select('CALL procGetWorkWeek()')[0] ?? null;
        if ($workWeek) {
            DB::select('CALL procUpdateWorkWeek(?, ?, ?, ?, ?, ?, ?, ?)', [
                $workWeek->id,
                DayLengthEnum::from($validated['mon'])->value,
                DayLengthEnum::from($validated['tue'])->value,
                DayLengthEnum::from($validated['wed'])->value,
                DayLengthEnum::from($validated['thu'])->value,
                DayLengthEnum::from($validated['fri'])->value,
                DayLengthEnum::from($validated['sat'])->value,
                DayLengthEnum::from($validated['sun'])->value,
            ]);
        } else {
            DB::select('CALL procInsertWorkWeek(?, ?, ?, ?, ?, ?, ?)', [
                DayLengthEnum::from($validated['mon'])->value,
                DayLengthEnum::from($validated['tue'])->value,
                DayLengthEnum::from($validated['wed'])->value,
                DayLengthEnum::from($validated['thu'])->value,
                DayLengthEnum::from($validated['fri'])->value,
                DayLengthEnum::from($validated['sat'])->value,
                DayLengthEnum::from($validated['sun'])->value,
            ]);
        }

        return response()->json(['message' => 'Work week updated successfully']);
    }
}
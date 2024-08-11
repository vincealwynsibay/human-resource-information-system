<?php

namespace App\Http\Controllers;

use App\Enum\DayLengthEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkWeekController extends Controller
{

    public function index(Request $request)
    {
        $workWeek = DB::table('tblworkweek')->first();
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

        $workWeek = DB::table('tblworkweek')->first();
        if ($workWeek) {
            DB::table('tblworkweek')->where('id', $workWeek->id)->update([
                'mon' => DayLengthEnum::from($validated['mon'])->value,
                'tue' => DayLengthEnum::from($validated['tue'])->value,
                'wed' => DayLengthEnum::from($validated['wed'])->value,
                'thu' => DayLengthEnum::from($validated['thu'])->value,
                'fri' => DayLengthEnum::from($validated['fri'])->value,
                'sat' => DayLengthEnum::from($validated['sat'])->value,
                'sun' => DayLengthEnum::from($validated['sun'])->value,
            ]);
        } else {
            DB::table('tblworkweek')->insert($validated);
        }

        return response()->json(['message' => 'Work week updated successfully']);
    }
}

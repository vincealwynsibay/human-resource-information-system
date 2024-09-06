<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayGradeController extends Controller
{
    public function index(Request $request)
    {
        $payGrades = DB::table('tblpaygrade')->get();

        return response()->json($payGrades, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'min_salary' => 'required|numeric',
            'max_salary' => 'required|numeric|gt:min_salary',
        ]);

        $payGrade = DB::table('tblpaygrade')->where('name', $validated['name'])->first();

        if ($payGrade) {
            return response()->json(['message' => 'Paygrade already exists'], 400);
        }

        DB::table('tblpaygrade')->insert([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'min_salary' => $validated['min_salary'],
            'max_salary' => $validated['max_salary'],
        ]);

        return response()->json(['message' => 'Paygrade created'], 201);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'nullable|string',
            'description' => 'nullable|string',
            'min_salary' => 'nullable|numeric',
            'max_salary' => 'nullable|numeric|gt:min_salary',
        ]);

        $payGrade = DB::table('tblpaygrade')->where('id', $id)->first();

        if (!$payGrade) {
            return response()->json(['message' => 'Paygrade not found'], 404);
        }

        DB::table('tblpaygrade')->where('id', $id)->update($validated);

        return response()->json(['message' => 'Paygrade updated'], 200);
    }

    public function destroy(Request $request, string $id)
    {
        $payGrade = DB::table('tblpaygrade')->where('id', $id)->first();

        if (!$payGrade) {
            return response()->json(['message' => 'Paygrade not found'], 404);
        }

        DB::table('tblpaygrade')->where('id', $id)->delete();

        return response()->json(['message' => 'Paygrade deleted'], 200);
    }
}

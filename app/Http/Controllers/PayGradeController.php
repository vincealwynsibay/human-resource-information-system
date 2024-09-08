<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayGradeController extends Controller
{
    public function index(Request $request)
    {
        $payGrades = DB::select('CALL procGetPayGrades()');
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

        $payGrade = DB::select('CALL procGetPayGradeByName(?)', [$validated['name']])[0] ?? null;

        if ($payGrade) {
            return response()->json(['message' => 'Paygrade already exists'], 400);
        }

        DB::select('CALL procInsertPayGrade(?, ?, ?, ?)', [
            $validated['name'],
            $validated['description'],
            $validated['min_salary'],
            $validated['max_salary']
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

        $payGrade = DB::select('CALL procGetPayGrade(?)', [$id])[0] ?? null;

        if (!$payGrade) {
            return response()->json(['message' => 'Paygrade not found'], 404);
        }

        DB::select('CALL procUpdatePayGradeByID(?, ?, ?, ?, ?)', [
            $id,
            $validated['name'],
            $validated['description'],
            $validated['min_salary'],
            $validated['max_salary']
        ]);

        return response()->json(['message' => 'Paygrade updated'], 200);
    }

    public function destroy(Request $request, string $id)
    {
        $payGrade = DB::select('CALL procGetPayGrade(?)', [$id])[0] ?? null;

        if (!$payGrade) {
            return response()->json(['message' => 'Paygrade not found'], 404);
        }

        DB::select('CALL procDeletePayGradeByID(?)', [$id]);

        return response()->json(['message' => 'Paygrade deleted'], 200);
    }
}
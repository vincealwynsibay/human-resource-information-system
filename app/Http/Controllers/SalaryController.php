<?php

namespace App\Http\Controllers;

use App\Enum\PayFrequencyEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SalaryController extends Controller
{

    /**
     * Display the specified resource.
     */
    public function getSalaryByEmployee(string $employeeId)
    {
        $employee = DB::select('CALL procGetEmployee(?)', [$employeeId])[0] ?? null;

        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $salary = DB::select('CALL procGetEmployeeSalary(?)', [$employeeId])[0] ?? null;

        return response()->json($salary, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'paygradeId' => 'required',
            'amount' => 'required|numeric',
            'change_reason' => 'nullable'
        ]);

        $paygrade = DB::select('CALL procGetPayGrade(?)', [$request->paygradeId])[0] ?? null;

        if ($paygrade == null) {
            return response()->json(['message' => 'Paygrade not found'], 404);
        }

        if ($paygrade->min_salary >= $request->amount || $paygrade->max_salary <= $request->amount) {
            return response()->json(['message' => 'Amount is not within the paygrade range'], 400);
        }

        $salary = DB::select('CALL procGetEmployeeSalary(?)', [$employeeId])[0] ?? null;

        if ($salary == null) {
            DB::select('CALL procInsertEmployeeSalary(?, ?, ?, ?, ?)', [
                $employeeId,
                $request->paygradeId,
                $request->amount,
                'Starting Salary',
                Date::now()
            ]);
        } else {
            DB::select('CALL procUpdateEmployeeSalaryByID(?, ?)', [
                $employeeId,
                Date::now()
            ]);
        }

        DB::select('CALL procInsertEmployeeSalary(?, ?, ?, ?, ?)', [
            $employeeId,
            $request->paygradeId,
            $request->amount,
            $request->change_reason,
            Date::now()
        ]);

        return response()->json(['message' => 'Salary created'], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $employeeId, string $salaryId)
    {
        $validated = $request->validate([
            'paygradeId' => 'nullable',
            'amount' => 'nullable|numeric',
            'change_reason' => 'nullable'
        ]);

        $paygrade = DB::select('CALL procGetPayGrade(?)', [$request->paygradeId])[0] ?? null;

        if ($paygrade == null) {
            return response()->json(['message' => 'Paygrade not found'], 404);
        }

        if ($paygrade->min_salary >= $request->amount || $paygrade->max_salary <= $request->amount) {
            return response()->json(['message' => 'Amount is not within the paygrade range'], 400);
        }

        DB::select('CALL procUpdateEmployeeSalaryByID(?, ?, ?, ?)', [
            $salaryId,
            $request->paygradeId,
            $request->amount,
            $request->change_reason
        ]);

        return response()->json(['message' => 'Salary updated'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::select('CALL procDeleteEmployeeSalaryByID(?, ?)', [$id, Date::now()]);

        return response()->json(['message' => 'Salary deleted'], 200);
    }
}
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
        // $employee = Employee::find($employeeId);
        $employee = DB::table('tblemployee')->where("id", $employeeId);

        if (!$employee) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        $salary = DB::table('tblbasicsalary')->where("EmployeeID", $employeeId)->whereNull('is_deleted')->first();

        return response()->json($salary, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployee,id',
            'paygradeId' => 'required',
            'amount' => 'required|numeric',
            'change_reason' => 'nullable'
        ]);

        $paygrade = DB::table('tblpaygrade')->where('id', $validated['paygradeId'])->first();

        if ($paygrade == null) {
            return response()->json(['message' => 'Paygrade not found'], 404);
        }

        if ($paygrade->min_salary > $validated['amount'] || $paygrade->max_salary < $validated['amount']) {
            return response()->json(['message' => 'Amount is not within the paygrade range'], 400);
        }


        $salary = DB::table('tblbasicsalary')->where('EmployeeID', $validated['employeeId'])->first();

        if ($salary == null) {
            DB::table('tblbasicsalary')->insert([
                'EmployeeID' => $validated['employeeId'],
                'PayGradeID' => $validated['paygradeId'],
                'amount' => $validated['amount'],
                'change_reason' => 'Starting Salary'
            ]);
        } else {
            // invalidate the old salary
            DB::table('tblbasicsalary')->where(['EmployeeID', $validated['employeeId']])->update([
                'is_deleted' => Date::now()
            ]);
        }

        DB::table('tblbasicsalary')->insert([
            'EmployeeID' => $validated['employeeId'],
            'PayGradeID' => $validated['paygradeId'],
            'amount' => $validated['amount'],
            'change_reason' => $validated['change_reason']
        ]);

        return response()->json(['message' => 'Salary created'], 201);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'paygradeId' => 'nullable',
            'amount' => 'nullable|numeric',
            'change_reason' => 'nullable'
        ]);

        $paygrade = DB::table('tblpaygrade')->where('id', $validated['paygradeId'])->first();

        if ($paygrade == null) {
            return response()->json(['message' => 'Paygrade not found'], 404);
        }

        if ($paygrade->min_salary > $validated['amount'] || $paygrade->max_salary < $validated['amount']) {
            return response()->json(['message' => 'Amount is not within the paygrade range'], 400);
        }

        DB::table('tblbasicsalary')->where('id', $id)->whereNull('is_deleted')->update([
            'EmployeeID' => $validated['employeeId'],
            'PayGradeID' => $validated['paygradeId'],
            'amount' => $validated['amount'],
            'change_reason' => $validated['change_reason']
        ]);

        return response()->json(['message' => 'Salary updated'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::table('tblbasicsalary')->where('id', $id)->whereNull('is_deleted')->update([
            'is_deleted' => Date::now()
        ]);

        return response()->json(['message' => 'Salary deleted'], 200);
    }
}

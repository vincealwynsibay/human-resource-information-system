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

        $salary = DB::table('tblbasicsalary')->where("EmployeeID", $employeeId)->get();

        return response()->json($salary, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployee,id',
            'payFrequency' => ['required', [Rule::enum(PayFrequencyEnum::class)]],
            'amount' => 'required|numeric',
        ]);

        DB::table('tblbasicsalary')->insert([
            'EmployeeID' => $validated['employeeId'],
            'pay_frequency' => $validated['payFrequency'],
            'amount' => $validated['amount'],
            'effective_date_start' => Date::now()
        ]);

        return response()->json(['message' => 'Salary created'], 201);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployee,id',
            'payFrequency' => ['required', [Rule::enum(PayFrequencyEnum::class)]],
            'amount' => 'required|numeric',
        ]);

        DB::table('tblbasicsalary')->where('id', $id)->update([
            'EmployeeID' => $validated['employeeId'],
            'pay_frequency' => $validated['payFrequency'],
            'amount' => $validated['amount']
        ]);

        return response()->json(['message' => 'Salary updated'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::table('tblbasicsalary')->where('id', $id)->update([
            'effective_date_end' => Date::now()
        ]);

        return response()->json(['message' => 'Salary deleted'], 200);
    }
}

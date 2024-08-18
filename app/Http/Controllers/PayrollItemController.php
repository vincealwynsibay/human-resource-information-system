<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PayrollItemTypeEnum;

class PayrollItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $payrollItems = DB::table('tblpayrollitems')->where('EmployeeID', $request->user()->EmployeeID)->get();
        return response()->json($payrollItems);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployees,id',
            'itemType' => ['required', [Rule::enum(PayrollItemTypeEnum::class)]],
            'amount' => 'required|numeric',
            'description' => 'required|string',
        ]);

        DB::table('tblpayrollitems')->insert([
            'EmployeeID' => $validated['employeeId'],
            'item_type' => $validated['itemType'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'created_date' => now(),
        ]);

        return response()->json(['message' => 'Payroll item created successfully'], 201);
    }


    public function addBonus(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployees,id',
            'amount' => 'required|numeric',
            'description' => 'required|string',
        ]);

        DB::table('tblpayrollitems')->insert([
            'EmployeeID' => $validated['employeeId'],
            'item_type' => PayrollItemTypeEnum::BONUS,
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'created_date' => now(),
        ]);

        return response()->json(['message' => 'Bonus added successfully'], 201);
    }

    public function addDeduction(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployees,id',
            'amount' => 'required|numeric',
            'description' => 'required|string',
        ]);

        DB::table('tblpayrollitems')->insert([
            'EmployeeID' => $validated['employeeId'],
            'item_type' => PayrollItemTypeEnum::DEDUCTION,
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'created_date' => now(),
        ]);

        return response()->json(['message' => 'Deduction added successfully'], 201);
    }

    public function addAllowance(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployees,id',
            'amount' => 'required|numeric',
            'description' => 'required|string',
        ]);

        DB::table('tblpayrollitems')->insert([
            'EmployeeID' => $validated['employeeId'],
            'item_type' => PayrollItemTypeEnum::ALLOWANCE,
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'created_date' => now(),
        ]);

        return response()->json(['message' => 'Allowance added successfully'], 201);
    }

    public function addCommission(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployees,id',
            'amount' => 'required|numeric',
            'description' => 'required|string',
        ]);

        DB::table('tblpayrollitems')->insert([
            'EmployeeID' => $validated['employeeId'],
            'item_type' => PayrollItemTypeEnum::COMMISSION,
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'created_date' => now(),
        ]);

        return response()->json(['message' => 'Commission added successfully'], 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $payrollItem = DB::table('tblpayrollitems')->where('id', $id)->first();
        return response()->json($payrollItem);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'itemType' => ['required', [Rule::enum(PayrollItemTypeEnum::class)]],
            'amount' => 'required|numeric',
            'description' => 'required|string',
        ]);

        DB::table('tblpayrollitems')->where('id', $id)->update([
            'item_type' => $validated['itemType'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'updated_date' => now(),
        ]);

        return response()->json(['message' => 'Payroll item updated successfully'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::table('tblpayrollitems')->where('id', $id)->delete();
        return response()->json(['message' => 'Payroll item deleted successfully'], 200);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Enum\PayrollItemTypeEnum;

class PayrollItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => "numeric"
        ]);

        $payrollItems = DB::select('CALL procGetPayrollItems(?)', [$request->employeeId]);
        return response()->json($payrollItems);
    }

    public function getUserPayrollItems(Request $request)
    {
        $payrollItems = DB::select('CALL procGetPayrollItems(?)', [$request->employeeId]);
        return response()->json($payrollItems);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployee,id',
            'itemType' => [Rule::enum(PayrollItemTypeEnum::class)],
            // 'itemType' => "string",
            'amount' => 'required|numeric',
            'description' => 'required|string',
        ]);

        DB::select('CALL procInsertPayrollItem(?, ?, ?, ?)', [
            $request->employeeId,
            $request->itemType,
            $request->amount,
            $request->description
        ]);

        return response()->json(['message' => 'Payroll item created successfully'], 201);
    }


    public function addBonus(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployee,id',
            'amount' => 'required|numeric',
            'description' => 'required|string',
        ]);

        DB::select('CALL procInsertPayrollItem(?, ?, ?, ?)', [
            $request->employeeId,
            PayrollItemTypeEnum::BONUS->value,
            $request->amount,
            $request->description
        ]);

        return response()->json(['message' => 'Bonus added successfully'], 201);
    }

    public function addDeduction(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployee,id',
            'amount' => 'required|numeric',
            'description' => 'required|string',
        ]);

        DB::select('CALL procInsertPayrollItem(?, ?, ?, ?)', [
            $request->employeeId,
            PayrollItemTypeEnum::DEDUCTION->value,
            $request->amount,
            $request->description
        ]);


        return response()->json(['message' => 'Deduction added successfully'], 201);
    }

    public function addAllowance(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployee,id',
            'amount' => 'required|numeric',
            'description' => 'required|string',
        ]);

        DB::select('CALL procInsertPayrollItem(?, ?, ?, ?)', [
            $request->employeeId,
            PayrollItemTypeEnum::ALLOWANCE->value,
            $request->amount,
            $request->description
        ]);


        return response()->json(['message' => 'Allowance added successfully'], 201);
    }

    public function addCommission(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployee,id',
            'amount' => 'required|numeric',
            'description' => 'required|string',
        ]);

        DB::select('CALL procInsertPayrollItem(?, ?, ?, ?)', [
            $request->employeeId,
            PayrollItemTypeEnum::COMMISSION->value,
            $request->amount,
            $request->description
        ]);


        return response()->json(['message' => 'Commission added successfully'], 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $payrollItem = DB::select('CALL procGetPayrollItem(?)', [$id])[0] ?? null;
        return response()->json($payrollItem);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'itemType' => ['required', Rule::enum(PayrollItemTypeEnum::class)],
            'amount' => 'required|numeric',
            'description' => 'required|string',
        ]);

        DB::select("CALL procUpdatePayrollItemByID(?, ?, ?, ?)", [
            $id,
            $request->itemType,
            $request->amount,
            $request->description
        ]);

        return response()->json(['message' => 'Payroll item updated successfully'], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::select('CALL procDeletePayrollItemByID(?)', [$id]);
        return response()->json(['message' => 'Payroll item deleted successfully'], 200);
    }
}

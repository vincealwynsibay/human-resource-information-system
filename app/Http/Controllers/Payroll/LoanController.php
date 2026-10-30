<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Payroll\PayrollService;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    use Respond;

    public function index(Request $request)
    {
        $loans = PayrollService::ledger()->list();
        if ($id = $request->query('employeeId')) {
            $loans = array_values(array_filter($loans, fn ($l) => $l['employeeId'] === $id));
        }
        return response()->json(['items' => $loans]);
    }

    /** A new loan: employeeId, name, principal and the amortization deducted each pay period. */
    public function store(Request $request)
    {
        return $this->attempt(fn () => PayrollService::ledger()->add($request->only(['employeeId', 'name', 'principal', 'amortization'])), 201);
    }

    /** Remove a loan that has never been deducted from pay. */
    public function destroy(string $id)
    {
        return $this->attempt(function () use ($id) {
            PayrollService::ledger()->remove($id);
            return response()->noContent();
        });
    }
}

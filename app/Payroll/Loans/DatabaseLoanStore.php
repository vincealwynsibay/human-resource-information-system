<?php

namespace App\Payroll\Loans;

use App\Models\Payroll\PayrollLoan;
use App\Models\Payroll\PayrollLoanPayment;
use Illuminate\Support\Facades\DB;

/**
 * The loan ledger kept in the payroll_loans and payroll_loan_payments tables. write() saves the ledger's new state:
 * loans it no longer lists are removed, and payments are only ever added (one per loan and pay period).
 */
class DatabaseLoanStore implements LoanStore
{
    public function read(): array
    {
        return PayrollLoan::with('payments')->orderBy('created_at')->orderBy('id')->get()->map(fn (PayrollLoan $l) => [
            'id' => $l->id, 'employeeId' => $l->employee_id, 'name' => $l->name,
            'principal' => $l->principal, 'amortization' => $l->amortization, 'balance' => $l->balance,
            'history' => $l->payments->map(fn (PayrollLoanPayment $p) => [
                'periodStart' => substr($p->period_start, 0, 10), 'periodEnd' => substr($p->period_end, 0, 10),
                'amount' => $p->amount, 'balanceAfter' => $p->balance_after, 'runId' => $p->payroll_run_id,
            ])->all(),
        ])->all();
    }

    public function write(array $loans): void
    {
        DB::transaction(function () use ($loans) {
            PayrollLoan::whereNotIn('id', array_column($loans, 'id'))->delete();
            foreach ($loans as $l) {
                PayrollLoan::updateOrCreate(['id' => $l['id']], [
                    'employee_id' => $l['employeeId'], 'name' => $l['name'],
                    'principal' => $l['principal'], 'amortization' => $l['amortization'], 'balance' => $l['balance'],
                ]);
                foreach ($l['history'] as $h) {
                    PayrollLoanPayment::firstOrCreate(
                        ['payroll_loan_id' => $l['id'], 'period_start' => $h['periodStart'], 'period_end' => $h['periodEnd']],
                        ['amount' => $h['amount'], 'balance_after' => $h['balanceAfter'], 'payroll_run_id' => $h['runId']],
                    );
                }
            }
        });
    }
}

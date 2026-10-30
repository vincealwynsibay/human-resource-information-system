<?php

namespace App\Payroll\Loans;

/**
 * Where the loan ledger keeps its loans: a list of arrays with id, employeeId, name, principal,
 * amortization, balance and history (periodStart, periodEnd, amount, balanceAfter, runId).
 */
interface LoanStore
{
    public function read(): array;

    public function write(array $loans): void;
}

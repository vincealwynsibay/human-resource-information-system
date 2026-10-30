<?php

namespace App\Payroll\Loans;

use App\Payroll\Money;
use Closure;

/**
 * Loan ledger amortized by the payroll runs. A run reads the open balances, and payments are POSTED
 * (balance reduced, history appended) only after the run is saved and its totals verified. Posting is
 * idempotent per loan and pay period, so re-running or retrying cannot deduct the same period twice.
 */
class LoanLedger
{
    private Closure $newId;

    public function __construct(private LoanStore $store, ?Closure $newId = null)
    {
        $this->newId = $newId ?? fn () => self::uuid();
    }

    public function list(): array
    {
        return $this->store->read();
    }

    public function add(array $fields): array
    {
        $p = (float) ($fields['principal'] ?? 0);
        $a = (float) ($fields['amortization'] ?? 0);
        $name = trim((string) ($fields['name'] ?? ''));
        if (empty($fields['employeeId'])) {
            throw new LoanException('employeeId is required', 400);
        }
        if ($name === '') {
            throw new LoanException('Loan name is required', 400);
        }
        if (!($p > 0)) {
            throw new LoanException('Principal must be a positive amount', 400);
        }
        if (!($a > 0 && $a <= $p)) {
            throw new LoanException('Amortization must be positive and no more than the principal', 400);
        }
        $loan = [
            'id' => ($this->newId)(), 'employeeId' => $fields['employeeId'], 'name' => $name,
            'principal' => Money::round2($p), 'amortization' => Money::round2($a), 'balance' => Money::round2($p), 'history' => [],
        ];
        $this->store->write([...$this->store->read(), $loan]);
        return $loan;
    }

    /** Remove a loan that has never been deducted from pay. */
    public function remove(string $id): void
    {
        $loans = $this->store->read();
        $loan = $this->find($loans, $id);
        if ($loan === null) {
            throw new LoanException('Unknown loan', 404);
        }
        if ($loans[$loan]['history']) {
            throw new LoanException('This loan already has payments posted and cannot be removed', 400);
        }
        array_splice($loans, $loan, 1);
        $this->store->write($loans);
    }

    /**
     * Open loans for one employee, in the shape Payslip::compute expects. For a period whose payment is
     * already posted, the balance is the one BEFORE that payment, so recomputing or replacing the run
     * reproduces the same deduction instead of deducting from an already-reduced balance.
     */
    public function inputsFor(string $employeeId, ?array $period = null): array
    {
        $out = [];
        foreach ($this->store->read() as $l) {
            if ($l['employeeId'] !== $employeeId) {
                continue;
            }
            $posted = $period ? $this->postedFor($l, $period) : null;
            $balance = $posted ? Money::round2($posted['balanceAfter'] + $posted['amount']) : $l['balance'];
            if ($balance > 0) {
                $out[] = ['loanId' => $l['id'], 'name' => $l['name'], 'amount' => $l['amortization'], 'balance' => $balance];
            }
        }
        return $out;
    }

    /** Apply a run's loan deductions ($result has period and slips) to the balances. Returns payments posted. */
    public function post(array $result, ?string $runId = null): int
    {
        $loans = $this->store->read();
        $posted = 0;
        foreach ($result['slips'] as $slip) {
            foreach ($slip['loans'] ?? [] as $d) {
                if (empty($d['loanId']) || !(($d['deducted'] ?? 0) > 0)) {
                    continue;
                }
                $i = $this->find($loans, $d['loanId']);
                if ($i === null || $this->postedFor($loans[$i], $result['period'])) {
                    continue;
                }
                $loans[$i]['balance'] = Money::round2(max(0, $loans[$i]['balance'] - $d['deducted']));
                $loans[$i]['history'][] = [
                    'periodStart' => $result['period']['start'], 'periodEnd' => $result['period']['end'],
                    'amount' => $d['deducted'], 'balanceAfter' => $loans[$i]['balance'], 'runId' => $runId,
                ];
                $posted++;
            }
        }
        if ($posted) {
            $this->store->write($loans);
        }
        return $posted;
    }

    private function find(array $loans, string $id): ?int
    {
        foreach ($loans as $i => $l) {
            if ($l['id'] === $id) {
                return $i;
            }
        }
        return null;
    }

    private function postedFor(array $loan, array $period): ?array
    {
        foreach ($loan['history'] as $h) {
            if ($h['periodStart'] === $period['start'] && $h['periodEnd'] === $period['end']) {
                return $h;
            }
        }
        return null;
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}

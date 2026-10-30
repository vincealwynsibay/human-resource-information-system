<?php

namespace App\Payroll;

use App\Models\Payroll\PayrollPayslip;
use App\Models\Payroll\PayrollProfile;
use App\Models\Payroll\PayrollRun;
use App\Payroll\Alphalist\Builder;
use App\Payroll\Loans\DatabaseLoanStore;
use App\Payroll\Loans\LoanLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Payroll runs kept in the database. A run is computed by the engine and saved as a DRAFT; it then moves
 * DRAFT -> PENDING_APPROVAL -> APPROVED -> PAID, or is CANCELLED before it is paid. Only a draft can be recomputed,
 * and loan balances move only when a run is paid, once per loan and pay period.
 */
class PayrollService
{
    public const OPEN = [PayrollRun::DRAFT, PayrollRun::PENDING_APPROVAL, PayrollRun::APPROVED];

    public static function ledger(): LoanLedger
    {
        return new LoanLedger(new DatabaseLoanStore());
    }

    /** Every pay profile, in the engine's shape. */
    public static function profiles(?array $employeeIds = null): array
    {
        $q = PayrollProfile::orderBy('name');
        if ($employeeIds !== null) {
            $q->whereIn('employee_id', $employeeIds);
        }
        return $q->get()->map->toEngine()->all();
    }

    private static function fail(string $message, int $status = 422): InvalidArgumentException
    {
        return new InvalidArgumentException($message, $status);
    }

    /**
     * Compute and save a regular run as a DRAFT. $data: periodStart, periodEnd, frequency, and optionally payDate,
     * schedule, employeeIds (default: everyone with a profile), inputs (per employee id), defaults, notes, and
     * useAttendance: take days, absences, late, undertime and premium hours from the HR time records.
     */
    public static function createRun(array $data, ?int $userId = null): PayrollRun
    {
        $period = ['start' => $data['periodStart'], 'end' => $data['periodEnd'], 'frequency' => $data['frequency']];
        if ($period['end'] < $period['start']) {
            throw self::fail('periodEnd cannot be before periodStart');
        }
        $clash = PayrollRun::where('kind', 'regular')->where('status', '!=', PayrollRun::CANCELLED)
            ->where('period_start', $period['start'])->where('period_end', $period['end'])->first();
        if ($clash) {
            throw self::fail("A {$clash->status} run for this period already exists; cancel it before computing the period again.", 409);
        }
        $profiles = self::profiles($data['employeeIds'] ?? null);
        if (!$profiles) {
            throw self::fail('There are no pay profiles to compute: add the employees\' pay profiles first.');
        }
        $inputs = ['inputs' => $data['inputs'] ?? [], 'defaults' => $data['defaults'] ?? null, 'employeeIds' => $data['employeeIds'] ?? null, 'useAttendance' => (bool) ($data['useAttendance'] ?? false)];
        $result = self::computeRegular($profiles, $period, $data['schedule'] ?? 'split', $inputs['inputs'], $inputs['defaults'], null, $inputs['useAttendance']);
        $inputs['attendance'] = $result['attendance'] ?? null;
        return self::save('regular', $result, [
            'pay_date' => $data['payDate'] ?? $period['end'], 'inputs' => $inputs, 'notes' => $data['notes'] ?? null, 'created_by' => $userId,
        ]);
    }

    /**
     * Compute a regular period with each employee's open loans. A loan's balance here is the ledger's balance less
     * what other unpaid runs already deduct from it, so two drafts computed before either is paid cannot together
     * take more than the loan has left. With $useAttendance, each employee's time records for the period supply the
     * attendance and premium hours (anything given in $inputs still wins), and the result carries a per-employee
     * `attendance` summary of what was counted.
     */
    public static function computeRegular(array $profiles, array $period, string $schedule, array $inputs, ?array $defaults, ?string $exceptRunId = null, bool $useAttendance = false): array
    {
        $fromHr = $useAttendance ? HrAttendance::forPeriod($profiles, $period, Holidays::calendar((int) substr($period['start'], 0, 4))) : [];
        if ($useAttendance && !HrAttendance::available()) {
            throw self::fail('There are no HR time records in this database to compute attendance from.');
        }
        $pending = [];
        $open = PayrollRun::whereIn('status', self::OPEN)->when($exceptRunId, fn ($q) => $q->whereKeyNot($exceptRunId))->with('payslips')->get();
        foreach ($open as $other) {
            foreach ($other->payslips as $s) {
                foreach ($s->data['loans'] ?? [] as $d) {
                    if (!empty($d['loanId'])) {
                        $pending[$d['loanId']] = ($pending[$d['loanId']] ?? 0) + $d['deducted'];
                    }
                }
            }
        }
        $ledger = self::ledger();
        $byId = [];
        foreach (array_values($profiles) as $i => $p) {
            $own = $inputs[$p['id']] ?? $inputs["#{$i}"] ?? [];
            if (isset($fromHr[$p['id']])) {
                $hr = $fromHr[$p['id']]['input'];
                $own = [...$own, 'attendance' => [...$hr['attendance'], ...($own['attendance'] ?? [])], 'workLogs' => $own['workLogs'] ?? $hr['workLogs']];
            }
            $loans = [];
            foreach ($ledger->inputsFor($p['id'], $period) as $l) {
                $l['balance'] = Money::round2($l['balance'] - ($pending[$l['loanId']] ?? 0));
                if ($l['balance'] > 0) {
                    $loans[] = $l;
                }
            }
            $byId[$p['id']] = [...$own, 'loans' => [...$loans, ...($own['loans'] ?? [])]];
        }
        $result = RunService::compute(['profiles' => $profiles, 'period' => $period, 'schedule' => $schedule, 'inputs' => $byId, 'defaults' => $defaults]);
        if ($useAttendance) {
            $result['attendance'] = array_map(fn ($x) => $x['summary'], $fromHr);
        }
        return $result;
    }

    /**
     * Set one employee's manual allowances and other deductions on a draft and recompute it. They go into the run's
     * inputs rather than straight onto the payslip, so tax and contributions are worked out again with them.
     */
    public static function adjust(PayrollRun $run, string $employeeId, array $allowances, array $otherDeductions): PayrollRun
    {
        if ($run->status !== PayrollRun::DRAFT || $run->kind !== 'regular') {
            throw self::fail('Only a regular run that is still a draft can be adjusted.');
        }
        if (!$run->payslips()->where('employee_id', $employeeId)->exists()) {
            throw self::fail('This employee is not in the run.', 404);
        }
        $clean = function (array $lines, bool $withTaxable) {
            $out = [];
            foreach ($lines as $l) {
                $name = trim((string) ($l['name'] ?? ''));
                $amount = Money::round2(Money::parse($l['amount'] ?? null));
                if ($name === '' || mb_strlen($name) > 100 || !($amount > 0)) {
                    throw self::fail('Each line needs a description of up to 100 characters and a positive amount.');
                }
                $out[] = ['name' => $name, 'amount' => $amount, ...($withTaxable ? ['taxable' => (bool) ($l['taxable'] ?? true)] : [])];
            }
            return $out;
        };
        $inputs = $run->inputs ?? [];
        $own = $inputs['inputs'][$employeeId] ?? [];
        $own['allowances'] = $clean($allowances, true);
        $own['otherDeductions'] = $clean($otherDeductions, false);
        $inputs['inputs'][$employeeId] = $own;
        $run->inputs = $inputs;
        $run->save();
        return self::recompute($run);
    }

    /** Recompute a draft from its saved inputs and the current profiles, keeping its id. */
    public static function recompute(PayrollRun $run): PayrollRun
    {
        if ($run->status !== PayrollRun::DRAFT) {
            throw self::fail("Only a draft can be recomputed; this run is {$run->status}.");
        }
        if ($run->kind !== 'regular') {
            throw self::fail('Only regular runs are recomputed; cancel this one and create it again.');
        }
        $inputs = $run->inputs ?? [];
        $result = self::computeRegular(self::profiles($inputs['employeeIds'] ?? null), $run->period(), $run->schedule, $inputs['inputs'] ?? [], $inputs['defaults'] ?? null, $run->id, (bool) ($inputs['useAttendance'] ?? false));
        return DB::transaction(function () use ($run, $result, $inputs) {
            $run->payslips()->delete();
            $run->inputs = [...$inputs, 'attendance' => $result['attendance'] ?? null];
            return self::fill($run, $result);
        });
    }

    /** Save an engine result (period, totals, slips) as a new DRAFT run. */
    public static function save(string $kind, array $result, array $extra = []): PayrollRun
    {
        return DB::transaction(function () use ($kind, $result, $extra) {
            $run = new PayrollRun([
                'kind' => $kind,
                'period_start' => $result['period']['start'], 'period_end' => $result['period']['end'],
                'frequency' => $result['period']['frequency'], 'schedule' => $result['schedule'] ?? 'n/a',
                'pay_date' => $extra['pay_date'] ?? $result['payDate'] ?? $result['period']['end'],
                'status' => PayrollRun::DRAFT,
                ...array_intersect_key($extra, array_flip(['inputs', 'notes', 'created_by'])),
            ]);
            return self::fill($run, $result);
        });
    }

    private static function fill(PayrollRun $run, array $result): PayrollRun
    {
        $t = $result['totals'];
        $run->fill([
            'total_gross' => $t['gross'], 'total_deductions' => $t['deductions'] ?? Money::sum(array_column($result['slips'], 'totalDeductions')),
            'total_net' => $t['net'], 'total_employer_cost' => $t['employerCost'] ?? 0,
        ])->save();
        foreach ($result['slips'] as $s) {
            $run->payslips()->create([
                'employee_id' => $s['employeeId'], 'name' => $s['name'],
                'gross' => $s['gross'], 'total_deductions' => $s['totalDeductions'], 'taxable_income' => $s['taxableIncome'], 'net' => $s['net'],
                'data' => $s,
            ]);
        }
        return $run->fresh('payslips');
    }

    /**
     * Move a run between statuses. The move is a conditional update, so two people approving or paying the same run
     * at once cannot both succeed.
     */
    public static function transition(PayrollRun $run, array $from, string $to, array $set = []): PayrollRun
    {
        $moved = PayrollRun::whereKey($run->getKey())->whereIn('status', $from)->update(['status' => $to, 'updated_at' => now(), ...$set]);
        if (!$moved) {
            $now = PayrollRun::find($run->getKey())?->status ?? 'gone';
            throw self::fail("This run is {$now}; it cannot move to {$to} from there.", 409);
        }
        return $run->fresh('payslips');
    }

    public static function submit(PayrollRun $run, ?int $userId = null): PayrollRun
    {
        return self::transition($run, [PayrollRun::DRAFT], PayrollRun::PENDING_APPROVAL, ['submitted_at' => now(), 'submitted_by' => $userId]);
    }

    /** Whether this person may approve the run: with separate approval on, not whoever prepared or submitted it. */
    public static function mayApprove(PayrollRun $run, ?int $userId): bool
    {
        if (!config('payroll.separate_approver', true)) {
            return true;
        }
        return $userId !== null && !in_array($userId, array_filter([$run->created_by, $run->submitted_by], fn ($v) => $v !== null), true);
    }

    public static function approve(PayrollRun $run, ?int $userId = null): PayrollRun
    {
        if (!self::mayApprove($run, $userId)) {
            throw self::fail('A run must be approved by someone other than the person who prepared or submitted it.', 409);
        }
        return self::transition($run, [PayrollRun::PENDING_APPROVAL], PayrollRun::APPROVED, ['approved_at' => now(), 'approved_by' => $userId]);
    }

    public static function cancel(PayrollRun $run): PayrollRun
    {
        return self::transition($run, self::OPEN, PayrollRun::CANCELLED, ['cancelled_at' => now()]);
    }

    /** Mark an approved run paid and post its loan deductions to the ledger, in one transaction. */
    public static function pay(PayrollRun $run): PayrollRun
    {
        return DB::transaction(function () use ($run) {
            $paid = self::transition($run, [PayrollRun::APPROVED], PayrollRun::PAID, ['paid_at' => now()]);
            self::ledger()->post(['period' => $paid->period(), 'slips' => $paid->payslips->pluck('data')->all()], $paid->id);
            return $paid;
        });
    }

    /** Payslips of a calendar year from every run that is not cancelled, optionally leaving out some kinds. */
    public static function slipsOfYear(int $year, array $excludeKinds = []): array
    {
        return PayrollPayslip::whereHas('run', function ($q) use ($year, $excludeKinds) {
            $q->where('status', '!=', PayrollRun::CANCELLED)->where('period_start', '>=', "{$year}-01-01")->where('period_start', '<=', "{$year}-12-31");
            if ($excludeKinds) {
                $q->whereNotIn('kind', $excludeKinds);
            }
        })->orderBy('id')->get()->pluck('data')->all();
    }

    /** Ids of the profiles that are minimum-wage earners, as the annual summary needs them. */
    public static function minimumWageEarners(array $profiles): array
    {
        return array_values(array_map(fn ($p) => $p['id'], array_filter($profiles, fn ($p) => Profile::minimumWageStatus($p)['earner'])));
    }

    /** Annual summary rows for a year, each with the employee's government IDs. */
    public static function annualRows(int $year): array
    {
        $profiles = array_column(self::profiles(), null, 'id');
        $rows = YearEnd::annualSummary(self::slipsOfYear($year), $year, self::minimumWageEarners(array_values($profiles)));
        return array_map(fn ($r) => [...$r, 'ids' => $profiles[$r['employeeId']]['ids'] ?? []], $rows);
    }

    /** How many of a year's runs are not paid yet: year-end figures include them, so the caller should say so. */
    public static function unpaidRunsOfYear(int $year): int
    {
        return PayrollRun::whereIn('status', self::OPEN)->where('period_start', '>=', "{$year}-01-01")->where('period_start', '<=', "{$year}-12-31")->count();
    }

    public static function thirteenthRun(int $year, array $earnedBasic = [], array $otherBonuses = [], ?int $userId = null): PayrollRun
    {
        $result = YearEnd::thirteenthMonthRun(self::profiles(), $year, $earnedBasic, $otherBonuses);
        if (!$result['slips']) {
            throw self::fail('Nobody has 13th-month pay to compute for this year.');
        }
        return self::save('thirteenth', $result, ['created_by' => $userId]);
    }

    /** Final pay for one employee, annualized against what the year's other runs already paid and withheld. */
    public static function finalPay(string $employeeId, array $data, ?int $userId = null): PayrollRun
    {
        $profile = self::profiles([$employeeId])[0] ?? throw self::fail('This employee has no pay profile.', 404);
        $hireDate = $data['hireDate'] ?? $profile['hireDate'] ?? null;
        if (!$hireDate) {
            throw self::fail('A hire date is needed: set it on the pay profile or send it.');
        }
        $year = (int) substr($data['separationDate'], 0, 4);
        $ytdRow = null;
        foreach (YearEnd::annualSummary(self::slipsOfYear($year, ['final_pay']), $year) as $r) {
            if ($r['employeeId'] === $employeeId) {
                $ytdRow = $r;
            }
        }
        $slip = YearEnd::finalPay([
            'employee' => $profile, 'hireDate' => $hireDate, 'birthDate' => $data['birthDate'] ?? $profile['birthDate'] ?? null,
            ...array_intersect_key($data, array_flip(['separationDate', 'cause', 'unpaidDays', 'unusedSilDays', 'earnedBasicYear', 'otherBonuses', 'firstTimeRetirement', 'clearance'])),
            'ytd' => ['taxableCompensation' => $ytdRow['taxableCompensation'] ?? 0, 'taxWithheld' => $ytdRow['taxWithheld'] ?? 0],
        ]);
        $result =['period' => $slip['period'], 'schedule' => 'n/a', 'totals' => ['gross' => $slip['gross'], 'deductions' => $slip['totalDeductions'], 'net' => $slip['net'], 'employerCost' => 0], 'slips' => [$slip]];
        return self::save('final_pay', $result, ['created_by' => $userId, 'notes' => "Final pay: {$slip['separation']['cause']} on {$slip['separation']['date']}"]);
    }

    public static function yearEndAdjustment(int $year, ?int $userId = null): PayrollRun
    {
        $existing = PayrollRun::where('kind', 'year_end_adjustment')->where('status', '!=', PayrollRun::CANCELLED)->where('period_start', "{$year}-12-31")->first();
        if ($existing) {
            throw self::fail("The {$year} year-end adjustment already exists ({$existing->status}); cancel it to compute it again.", 409);
        }
        $result = YearEnd::yearEndAdjustmentRun(self::annualRows($year), $year);
        if (!$result['slips']) {
            throw self::fail('Everyone\'s tax withheld already matches the tax due: no adjustment is needed.');
        }
        return self::save('year_end_adjustment', $result, ['created_by' => $userId]);
    }

    public static function alphalist(int $year, array $employer, ?string $regionNumber = null): array
    {
        return Builder::build([
            'rows' => self::annualRows($year), 'profiles' => self::profiles(), 'year' => $year,
            'employer' => $employer, 'regionNumber' => $regionNumber,
        ]);
    }
}

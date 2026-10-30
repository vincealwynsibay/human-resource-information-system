<?php

namespace App\Payroll;

use InvalidArgumentException;

/**
 * One employee, one pay period -> an itemised Philippine payslip.
 *
 * Supported: monthly, daily and hourly-paid employees; MONTHLY, SEMI_MONTHLY and WEEKLY periods.
 * BI_WEEKLY is rejected (the BIR publishes no table for it).
 */
class Payslip
{
    private const FREQUENCIES = ['MONTHLY', 'SEMI_MONTHLY', 'WEEKLY'];
    private const DAY_LABEL = [
        'rest_day' => 'rest day',
        'special_non_working' => 'special non-working day',
        'special_on_rest_day' => 'special day on rest day',
        'regular_holiday' => 'regular holiday',
        'regular_holiday_on_rest_day' => 'regular holiday on rest day',
        'double_holiday' => 'double holiday',
        'double_holiday_on_rest_day' => 'double holiday on rest day',
    ];

    public static function addDays(string $iso, int $n): string
    {
        return gmdate('Y-m-d', strtotime("{$iso}T00:00:00Z") + $n * 86400);
    }

    public static function cutoff(array $period): ?int
    {
        if ($period['frequency'] !== 'SEMI_MONTHLY') {
            return null;
        }
        return (int) substr($period['start'], 8, 2) <= 15 ? 1 : 2;
    }

    /**
     * Share of a monthly amount deducted in this period. SEMI_MONTHLY follows the schedule (split or
     * second cutoff). WEEKLY deducts the whole month in the last weekly period of the month.
     */
    private static function share(float $monthlyAmount, array $period, string $schedule): float
    {
        if ($period['frequency'] === 'MONTHLY') {
            return $monthlyAmount;
        }
        if ($period['frequency'] === 'WEEKLY') {
            return substr(self::addDays($period['end'], 7), 0, 7) !== substr($period['end'], 0, 7) ? $monthlyAmount : 0;
        }
        $cutoff = self::cutoff($period);
        if ($schedule === 'second_cutoff') {
            return $cutoff === 2 ? $monthlyAmount : 0;
        }
        $first = Money::round2($monthlyAmount / 2);
        return $cutoff === 1 ? $first : Money::round2($monthlyAmount - $first);
    }

    public static function compute(array $input): array
    {
        $employee = $input['employee'];
        $period = $input['period'];
        $calendar = $input['calendar'];
        $attendance = $input['attendance'] ?? [];
        $opts = ['contributionSchedule' => 'split', ...($input['options'] ?? [])];
        if (!in_array($period['frequency'], self::FREQUENCIES, true)) {
            throw new InvalidArgumentException("Unsupported pay frequency {$period['frequency']}");
        }
        $basis = $employee['payBasis'] ?? 'monthly';
        if (!in_array($basis, Profile::PAY_BASES, true)) {
            throw new InvalidArgumentException("Unknown pay basis {$basis}");
        }

        $factor = Profile::factor($employee);
        $rateField = Profile::RATE_FIELD[$basis];
        if (!(($employee[$rateField] ?? 0) > 0)) {
            throw new InvalidArgumentException("{$rateField} must be positive for {$employee['id']}");
        }
        if ($basis === 'daily' && !(($attendance['daysWorked'] ?? -1) >= 0)) {
            throw new InvalidArgumentException("attendance.daysWorked is required for daily-paid {$employee['id']}");
        }
        if ($basis === 'hourly' && !(($attendance['hoursWorked'] ?? -1) >= 0)) {
            throw new InvalidArgumentException("attendance.hoursWorked is required for hourly-paid {$employee['id']}");
        }
        $daily = Profile::dailyRate($employee);
        $monthly = Profile::monthlyEquivalent($employee); // the salary for monthly-paid; otherwise picks the contribution bracket
        $hourly = $daily / 8;

        $earnings = [];
        if ($basis === 'monthly') {
            $periodBasic = match ($period['frequency']) {
                'MONTHLY' => $monthly,
                'SEMI_MONTHLY' => $monthly / 2,
                default => ($monthly * 12) / 52,
            };
            $earnings[] = ['code' => 'BASIC', 'label' => 'Basic pay', 'amount' => Money::round2($periodBasic), 'taxable' => true];
        } else {
            if ($basis === 'daily') {
                $earnings[] = ['code' => 'BASIC', 'label' => 'Basic pay (' . Money::str($attendance['daysWorked']) . ' day/s)', 'amount' => Money::round2($daily * $attendance['daysWorked']), 'taxable' => true];
            } else {
                $earnings[] = ['code' => 'BASIC', 'label' => 'Basic pay (' . Money::str($attendance['hoursWorked']) . ' hour/s)', 'amount' => Money::round2($employee['hourlyRate'] * $attendance['hoursWorked']), 'taxable' => true];
            }
            // Regular holidays are paid at 100% for daily and hourly-paid staff whether or not they work; working adds the premium.
            $skip = $attendance['holidayPayExcludedDates'] ?? [];
            $holidayDays = 0;
            foreach ($calendar['holidays'] as $h) {
                if ($h['date'] < $period['start'] || $h['date'] > $period['end'] || in_array($h['date'], $skip, true) || $h['type'] !== 'regular') {
                    continue;
                }
                $holidayDays++; // a double holiday has two regular entries, so it naturally counts 200%
            }
            if ($holidayDays) {
                $earnings[] = ['code' => 'HOLIDAY_PAY', 'label' => "Regular holiday pay ({$holidayDays} day/s)", 'amount' => Money::round2($daily * $holidayDays), 'taxable' => true];
            }
        }

        $attendanceDeductions = [];
        $addAttendance = function (string $code, string $label, float $amount) use (&$attendanceDeductions) {
            if ($amount) {
                $attendanceDeductions[] = ['code' => $code, 'label' => $label, 'amount' => $amount];
            }
        };
        if ($basis === 'monthly') {
            $addAttendance('ABSENCE', 'Absences (' . Money::str($attendance['absentDays'] ?? null) . ' day/s)', Money::round2(($attendance['absentDays'] ?? 0) * $daily));
        }
        // Hourly-paid staff are paid only for hours worked, so lateness is already out of hoursWorked.
        if ($basis !== 'hourly') {
            $addAttendance('LATE', 'Late (' . Money::str($attendance['lateMinutes'] ?? null) . ' min)', Money::round2((($attendance['lateMinutes'] ?? 0) / 60) * $hourly));
            $addAttendance('UNDERTIME', 'Undertime (' . Money::str($attendance['undertimeMinutes'] ?? null) . ' min)', Money::round2((($attendance['undertimeMinutes'] ?? 0) / 60) * $hourly));
        }

        // Premium pay from work logs.
        $ot = 0;
        $nd = 0;
        $hol = 0;
        $dayLabels = [];
        foreach ($input['workLogs'] ?? [] as $log) {
            $p = Premium::forLog($log, $hourly, $calendar);
            $ot += $p['overtime'];
            $nd += $p['nightDifferential'];
            $hol += $p['holidayRestPremium'];
            if ($p['key'] !== 'ordinary') {
                $dayLabels[$log['date'] . ' ' . (self::DAY_LABEL[$p['key']] ?? $p['key'])] = true;
            }
        }
        if (Money::round2($ot)) {
            $earnings[] = ['code' => 'OVERTIME', 'label' => 'Overtime pay', 'amount' => Money::round2($ot), 'taxable' => true];
        }
        if (Money::round2($nd)) {
            $earnings[] = ['code' => 'NIGHT_DIFF', 'label' => 'Night differential', 'amount' => Money::round2($nd), 'taxable' => true];
        }
        if (Money::round2($hol)) {
            $earnings[] = ['code' => 'HOLIDAY_PREMIUM', 'label' => 'Holiday / rest-day premium (' . implode(', ', array_keys($dayLabels)) . ')', 'amount' => Money::round2($hol), 'taxable' => true];
        }

        foreach ($input['allowances'] ?? [] as $a) {
            $earnings[] = ['code' => 'ALLOWANCE', 'label' => $a['name'], 'amount' => Money::round2($a['amount']), 'taxable' => ($a['taxable'] ?? true) !== false];
        }

        // Statutory contributions (monthly figures, spread over the periods of the month).
        $c = Contributions::monthly($monthly);
        $schedule = $opts['contributionSchedule'];
        $emp = [
            'sss' => self::share($c['sss']['employee'], $period, $schedule),
            'philhealth' => self::share($c['philhealth']['employee'], $period, $schedule),
            'pagibig' => self::share($c['pagibig']['employee'], $period, $schedule),
        ];
        $employer = [
            'sss' => self::share($c['sss']['employer'], $period, $schedule),
            'ec' => self::share($c['sss']['ec'], $period, $schedule),
            'philhealth' => self::share($c['philhealth']['employer'], $period, $schedule),
            'pagibig' => self::share($c['pagibig']['employer'], $period, $schedule),
        ];
        $employer['total'] = Money::sum(array_values($employer));

        $grossEarnings = Money::sum(array_column($earnings, 'amount'));
        $taxableEarnings = Money::sum(array_column(array_filter($earnings, fn ($e) => $e['taxable']), 'amount'));
        $taxableIncome = Money::round2(max(0, $taxableEarnings - Money::sum(array_column($attendanceDeductions, 'amount')) - $emp['sss'] - $emp['philhealth'] - $emp['pagibig']));
        $mwe = Profile::minimumWageStatus($employee);
        $tax = $mwe['earner'] ? 0 : Tax::withholding($taxableIncome, $period['frequency']);

        // Loan amortization: never more than the remaining balance. Loans come out after tax.
        $loanLines = [];
        $loans = [];
        foreach ($input['loans'] ?? [] as $l) {
            $amount = Money::round2(min($l['amount'], $l['balance']));
            if ($amount > 0) {
                $loanLines[] = ['code' => 'LOAN', 'label' => "{$l['name']} (balance after: " . Money::fixed2(Money::round2($l['balance'] - $amount)) . ')', 'amount' => $amount];
            }
            $loans[] = ['loanId' => $l['loanId'] ?? null, 'name' => $l['name'], 'deducted' => $amount, 'balanceAfter' => Money::round2($l['balance'] - $amount)];
        }

        $other = array_map(fn ($d) => ['code' => 'OTHER_DEDUCTION', 'label' => $d['name'], 'amount' => Money::round2($d['amount'])], $input['otherDeductions'] ?? []);
        $deductions = array_values(array_filter([
            ...$attendanceDeductions,
            ['code' => 'SSS', 'label' => 'SSS contribution', 'amount' => $emp['sss']],
            ['code' => 'PHILHEALTH', 'label' => 'PhilHealth contribution', 'amount' => $emp['philhealth']],
            ['code' => 'PAGIBIG', 'label' => 'Pag-IBIG contribution', 'amount' => $emp['pagibig']],
            ['code' => 'WITHHOLDING_TAX', 'label' => 'Withholding tax', 'amount' => $tax],
            ...$loanLines,
            ...$other,
        ], fn ($d) => $d['amount'] > 0));

        $totalDeductions = Money::sum(array_column($deductions, 'amount'));
        $notes = [];
        if (!empty($calendar['missing'])) {
            $notes[] = "No holiday calendar for {$calendar['year']}; holiday premiums were computed as ordinary days.";
        }
        if ($mwe['auto']) {
            $notes[] = $mwe['earner'] ? "Treated as a minimum-wage earner, exempt from withholding tax. {$mwe['reason']}" : "Not a minimum-wage earner. {$mwe['reason']}";
        }

        return [
            'employeeId' => $employee['id'],
            'name' => $employee['name'] ?? null,
            'period' => $period,
            'cutoff' => self::cutoff($period),
            'rates' => ['daily' => Money::round2($daily), 'hourly' => Money::round2($hourly), 'factor' => $factor],
            'earnings' => $earnings,
            'deductions' => $deductions,
            'loans' => $loans,
            'gross' => $grossEarnings,
            'totalDeductions' => $totalDeductions,
            'taxableIncome' => $taxableIncome,
            'net' => Money::round2($grossEarnings - $totalDeductions),
            'employer' => $employer,
            'notes' => $notes,
        ];
    }
}

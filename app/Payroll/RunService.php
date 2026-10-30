<?php

namespace App\Payroll;

use App\Payroll\Loans\LoanLedger;

/**
 * Computes a pay period for every profile: one payslip each, plus the run totals.
 */
class RunService
{
    /**
     * @param array $a profiles, period, and optionally inputs (keyed by employee id or by position "#0", "#1", ...),
     *                 schedule (split | second_cutoff), ledger (a LoanLedger whose open loans are deducted) and
     *                 defaults (daysWorked / hoursWorked for daily and hourly staff whose inputs do not say).
     */
    public static function compute(array $a): array
    {
        $period = $a['period'];
        $schedule = $a['schedule'] ?? 'split';
        $inputs = $a['inputs'] ?? [];
        $ledger = $a['ledger'] ?? null;
        $calendar = Holidays::calendar((int) substr($period['start'], 0, 4));
        $slips = [];
        foreach (array_values($a['profiles']) as $i => $p) {
            $input = $inputs[$p['id']] ?? $inputs["#{$i}"] ?? [];
            $input = self::withDefaultAttendance($input, $p, $a['defaults'] ?? null);
            if ($ledger instanceof LoanLedger) {
                $input['loans'] = [...$ledger->inputsFor($p['id'], $period), ...($input['loans'] ?? [])];
            }
            $slips[] = Payslip::compute([
                'employee' => $p, 'period' => $period, 'calendar' => $calendar,
                'options' => ['contributionSchedule' => $schedule],
                ...$input,
            ]);
        }
        $totals = [
            'gross' => Money::sum(array_column($slips, 'gross')),
            'deductions' => Money::sum(array_column($slips, 'totalDeductions')),
            'net' => Money::sum(array_column($slips, 'net')),
            'employerCost' => Money::sum(array_map(fn ($s) => $s['employer']['total'], $slips)),
        ];
        return ['period' => $period, 'schedule' => $schedule, 'calendarMissing' => $calendar['missing'], 'totals' => $totals, 'slips' => $slips];
    }

    /** Fill in days or hours worked for daily and hourly-paid staff whose inputs do not say. */
    private static function withDefaultAttendance(array $input, array $profile, ?array $defaults): array
    {
        $need = match ($profile['payBasis'] ?? 'monthly') {
            'daily' => ['daysWorked', $defaults['daysWorked'] ?? null],
            'hourly' => ['hoursWorked', $defaults['hoursWorked'] ?? null],
            default => null,
        };
        // A null count is "not given" (the JS original read it as 0 hours, a JavaScript quirk not carried over).
        if (!$need || $need[1] === null || $need[1] === '' || isset($input['attendance'][$need[0]])) {
            return $input;
        }
        $input['attendance'] = [...($input['attendance'] ?? []), $need[0] => Money::parse($need[1])];
        return $input;
    }
}

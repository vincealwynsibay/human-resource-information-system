<?php

namespace App\Payroll;

/**
 * Monthly SSS, PhilHealth and Pag-IBIG contributions for a given monthly compensation.
 * Returns whole-month amounts; the payslip decides how to spread them over cutoffs.
 */
class Contributions
{
    /** SSS: the bracket whose floor is the highest one at or below the compensation. */
    public static function sss(float $monthlyCompensation): array
    {
        $t = Tables::get('sss');
        $bracket = $t['brackets'][0];
        foreach ($t['brackets'] as $b) {
            if ($monthlyCompensation >= $b['comp_min']) {
                $bracket = $b;
            }
        }
        return ['employee' => $bracket['employee'], 'employer' => $bracket['employer'], 'ec' => $bracket['ec'], 'msc' => $bracket['msc']];
    }

    /** PhilHealth: 5% of the monthly basic salary clamped to the floor and ceiling, split equally. */
    public static function philhealth(float $monthlyBasic): array
    {
        $t = Tables::get('philhealth');
        $base = min(max($monthlyBasic, $t['salary_floor']), $t['salary_ceiling']);
        return ['employee' => Money::round2($base * $t['employee_rate']), 'employer' => Money::round2($base * $t['employer_rate']), 'base' => $base];
    }

    /** Pag-IBIG: 1% or 2% employee share and 2% employer share, base capped at the fund-salary cap. */
    public static function pagibig(float $monthlyCompensation): array
    {
        $t = Tables::get('pagibig');
        $base = min($monthlyCompensation, $t['mfs_cap']);
        $tier = end($t['tiers']);
        foreach ($t['tiers'] as $x) {
            if ($monthlyCompensation >= $x['comp_min'] && ($x['comp_max'] === null || $monthlyCompensation <= $x['comp_max'])) {
                $tier = $x;
                break;
            }
        }
        return ['employee' => Money::round2($base * $tier['employee_rate']), 'employer' => Money::round2($base * $tier['employer_rate']), 'base' => $base];
    }

    public static function monthly(float $monthlyBasic): array
    {
        $s = self::sss($monthlyBasic);
        $p = self::philhealth($monthlyBasic);
        $h = self::pagibig($monthlyBasic);
        return [
            'sss' => ['employee' => $s['employee'], 'employer' => $s['employer'], 'ec' => $s['ec']],
            'philhealth' => ['employee' => $p['employee'], 'employer' => $p['employer']],
            'pagibig' => ['employee' => $h['employee'], 'employer' => $h['employer']],
        ];
    }
}

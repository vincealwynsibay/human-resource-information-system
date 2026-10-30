<?php

namespace App\Payroll;

/**
 * 13th-month pay (PD 851): one twelfth of the basic salary actually earned in the calendar year. Together
 * with other bonuses it is tax-exempt up to the combined ceiling; the excess is taxable compensation.
 */
class Thirteenth
{
    public static function compute(float $totalBasicEarnedInYear, float $otherBonuses = 0): array
    {
        $t = Tables::get('thirteenth');
        $amount = Money::round2($totalBasicEarnedInYear / 12);
        $combined = $amount + $otherBonuses;
        return [
            'amount' => $amount,
            'taxExemptCeiling' => $t['tax_exempt_ceiling'],
            'taxableExcess' => Money::round2(max(0, $combined - $t['tax_exempt_ceiling'])),
            'payBy' => $t['pay_deadline'],
        ];
    }
}

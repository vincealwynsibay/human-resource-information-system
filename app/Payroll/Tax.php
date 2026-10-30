<?php

namespace App\Payroll;

use InvalidArgumentException;

/**
 * Withholding tax on compensation under TRAIN (RR 11-2018).
 *
 * DAILY, WEEKLY, SEMI_MONTHLY and MONTHLY use the BIR's own Annex E. ANNUALLY uses the annual
 * schedule, which the year-end annualization needs. BI_WEEKLY has no BIR table and is rejected.
 */
class Tax
{
    private const ANNEX_KEY = ['DAILY' => 'daily', 'WEEKLY' => 'weekly', 'SEMI_MONTHLY' => 'semi_monthly', 'MONTHLY' => 'monthly'];

    public static function table(string $frequency): array
    {
        if (isset(self::ANNEX_KEY[$frequency])) {
            return Tables::get('annexE')['tables'][self::ANNEX_KEY[$frequency]];
        }
        if ($frequency === 'ANNUALLY') {
            return Tables::get('tax')['annual'];
        }
        throw new InvalidArgumentException("Withholding tax is not supported for frequency {$frequency}");
    }

    public static function withholding(float $taxableIncome, string $frequency): float
    {
        $rows = self::table($frequency);
        if ($taxableIncome <= 0) {
            return 0;
        }
        // Annex E brackets are "from X": the last bracket whose lower bound the income reaches.
        // The annual schedule is "not over Y": the first bracket whose ceiling it does not exceed.
        $row = null;
        if (isset(self::ANNEX_KEY[$frequency])) {
            foreach (array_reverse($rows) as $r) {
                if ($taxableIncome >= $r['over']) {
                    $row = $r;
                    break;
                }
            }
        } else {
            foreach ($rows as $r) {
                if ($r['not_over'] === null || $taxableIncome <= $r['not_over']) {
                    $row = $r;
                    break;
                }
            }
        }
        return Money::round2(max(0, $row['base_tax'] + $row['rate'] * ($taxableIncome - $row['of_excess_over'])));
    }
}

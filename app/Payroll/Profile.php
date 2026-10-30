<?php

namespace App\Payroll;

/**
 * Employee pay-profile helpers: pay-basis arithmetic and the regional minimum-wage check.
 */
class Profile
{
    public const PAY_BASES = ['monthly', 'daily', 'hourly'];
    public const DEFAULT_DAILY_RATE_FACTOR = 261; // DOLE equivalent-monthly factor for a 5-day week
    public const RATE_FIELD = ['monthly' => 'monthlySalary', 'daily' => 'dailyRate', 'hourly' => 'hourlyRate'];

    public static function factor(array $p): float
    {
        return $p['dailyRateFactor'] ?? self::DEFAULT_DAILY_RATE_FACTOR;
    }

    /** Daily rate: monthly x 12 / factor, the daily rate itself, or eight hours at the hourly rate. */
    public static function dailyRate(array $p): float
    {
        return match ($p['payBasis'] ?? 'monthly') {
            'daily' => $p['dailyRate'],
            'hourly' => $p['hourlyRate'] * 8,
            default => ($p['monthlySalary'] * 12) / self::factor($p),
        };
    }

    /** Monthly equivalent, used to find the SSS / PhilHealth / Pag-IBIG bracket. */
    public static function monthlyEquivalent(array $p): float
    {
        if (($p['payBasis'] ?? 'monthly') === 'monthly') {
            return $p['monthlySalary'];
        }
        return Money::round2((self::dailyRate($p) * self::factor($p)) / 12);
    }

    public static function regions(): array
    {
        return array_column(Tables::get('minWage')['regions'], 'region');
    }

    /**
     * Statutory daily minimum wage for a region. Wage orders give a range by sector: the higher figure is
     * non-agriculture, the lower is agriculture / small establishments. Null for an unknown region.
     */
    public static function minimumWage(?string $region, ?string $sector = 'non_agri'): ?array
    {
        $t = Tables::get('minWage');
        foreach ($t['regions'] as $r) {
            if ($r['region'] === $region) {
                return [
                    'region' => $r['region'], 'wageOrder' => $r['wage_order'], 'effective' => $r['effective'], 'matrixAsOf' => $t['matrix_as_of'],
                    'dailyMin' => $sector === 'agri_small' ? $r['daily_min_low'] : $r['daily_min_high'], 'sector' => $sector,
                ];
            }
        }
        return null;
    }

    /**
     * Is this employee a minimum-wage earner (exempt from income tax)? `minimumWageEarner` may be true/false,
     * or 'auto' to compare the daily rate with the regional minimum for the profile's region and sector.
     */
    public static function minimumWageStatus(array $p): array
    {
        if (($p['minimumWageEarner'] ?? null) === 'auto') {
            $m = self::minimumWage($p['region'] ?? null, array_key_exists('sector', $p) ? $p['sector'] : 'non_agri');
            if (!$m) {
                return ['earner' => false, 'auto' => true, 'reason' => 'Minimum-wage status is set to automatic but the profile has no known region.'];
            }
            $daily = self::dailyRate($p);
            $d = Money::round2($daily);
            return ['earner' => $daily <= $m['dailyMin'], 'auto' => true, 'daily' => $d, ...$m,
                'reason' => 'Daily rate ' . Money::str($d) . " vs " . Money::str($m['dailyMin']) . " minimum ({$m['region']}, {$m['wageOrder']}, matrix as of {$m['matrixAsOf']})."];
        }
        return ['earner' => ($p['minimumWageEarner'] ?? null) === true, 'auto' => false];
    }
}

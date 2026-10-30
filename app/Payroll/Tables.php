<?php

namespace App\Payroll;

/**
 * Statutory tables from resources/payroll. Each file cites its primary source (see ATTRIBUTION.md there).
 */
class Tables
{
    private static array $cache = [];

    private const FILES = [
        'sss' => 'sss_2025.json',
        'philhealth' => 'philhealth_2025.json',
        'pagibig' => 'pagibig_2025.json',
        'tax' => 'income_tax_2025.json',
        'premium' => 'premium_pay.json',
        'thirteenth' => '13th_month_pay.json',
        'leaves' => 'leaves.json',
        'minWage' => 'min_wage_2025.json',
        'annexE' => 'withholding_annex_e.json',
        'separation' => 'separation_pay.json',
        'retirement' => 'retirement_pay.json',
    ];

    public static function dir(): string
    {
        return dirname(__DIR__, 2) . '/resources/payroll';
    }

    public static function get(string $name): array
    {
        return self::$cache[$name] ??= self::load(self::FILES[$name]);
    }

    public static function load(string $file): array
    {
        return json_decode(file_get_contents(self::dir() . '/' . $file), true, flags: JSON_THROW_ON_ERROR);
    }
}

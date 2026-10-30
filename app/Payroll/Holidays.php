<?php

namespace App\Payroll;

/**
 * Holiday classification. Multipliers do not change year to year, but holiday DATES do: movable days
 * (Holy Week, Eid, bridge days) are fixed by proclamation each year, so the calendar lives in
 * resources/payroll/holidays-<year>.json and must be supplied per year.
 */
class Holidays
{
    /** The calendar for a year, or an empty one flagged as missing. */
    public static function calendar(int $year): array
    {
        $file = Tables::dir() . "/holidays-{$year}.json";
        if (!is_file($file)) {
            return ['year' => $year, 'holidays' => [], 'missing' => true];
        }
        return [...Tables::load("holidays-{$year}.json"), 'missing' => false];
    }

    /** 'regular' | 'double' | 'special' | null for an ISO date (YYYY-MM-DD). */
    public static function type(string $date, array $calendar): ?string
    {
        $regular = 0;
        $special = false;
        foreach ($calendar['holidays'] as $h) {
            if ($h['date'] !== $date) {
                continue;
            }
            if ($h['type'] === 'regular') {
                $regular++;
            } elseif ($h['type'] === 'special_non_working') {
                $special = true;
            }
        }
        if ($regular >= 2) {
            return 'double';
        }
        if ($regular === 1) {
            return 'regular';
        }
        return $special ? 'special' : null;
    }

    /** Key into the premium-pay matrix for a day, given its holiday type and rest-day flag. */
    public static function dayKey(string $date, bool $isRestDay, array $calendar): string
    {
        return match (self::type($date, $calendar)) {
            'double' => $isRestDay ? 'double_holiday_on_rest_day' : 'double_holiday',
            'regular' => $isRestDay ? 'regular_holiday_on_rest_day' : 'regular_holiday',
            'special' => $isRestDay ? 'special_on_rest_day' : 'special_non_working',
            default => $isRestDay ? 'rest_day' : 'ordinary',
        };
    }
}

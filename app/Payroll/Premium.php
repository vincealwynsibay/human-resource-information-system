<?php

namespace App\Payroll;

/**
 * Premium pay for hours worked, from the DOLE multiplier matrix.
 *
 * The monthly salary already covers ordinary working days and regular holidays at 100%, but NOT rest
 * days or special non-working days. So for hours worked only what the salary does not already pay is added:
 *   regular hours  -> hourly * (multiplier - included)
 *   overtime hours -> hourly * multiplier            (overtime is never in the salary)
 * where `included` is 1 on ordinary days and regular holidays, 0 on rest days and special days.
 */
class Premium
{
    private const INCLUDED_IN_MONTHLY_SALARY = ['ordinary', 'regular_holiday', 'regular_holiday_on_rest_day', 'double_holiday', 'double_holiday_on_rest_day'];

    private static ?array $matrix = null;

    private static function matrix(): array
    {
        return self::$matrix ??= array_column(Tables::get('premium')['matrix'], null, 'day');
    }

    /**
     * @param array $log date, isRestDay, regularHours, nightHours (subset of regular), overtimeHours,
     *                   nightOvertimeHours (subset of overtime): night means between 10pm and 6am.
     */
    public static function forLog(array $log, float $hourly, array $calendar): array
    {
        $key = Holidays::dayKey($log['date'], !empty($log['isRestDay']), $calendar);
        $m = self::matrix()[$key];
        $included = in_array($key, self::INCLUDED_IN_MONTHLY_SALARY, true) ? 1 : 0;
        $regular = $log['regularHours'] ?? 0;
        $night = min($log['nightHours'] ?? 0, $regular);
        $ot = $log['overtimeHours'] ?? 0;
        $nightOt = min($log['nightOvertimeHours'] ?? 0, $ot);

        $overtime = $hourly * (($ot - $nightOt) * $m['overtime'] + $nightOt * $m['night_overtime']);
        $regularPay = $hourly * (($regular - $night) * ($m['regular'] - $included) + $night * ($m['night'] - $included));
        // On an ordinary day the only regular-hours add-on is the 10% night differential;
        // on holidays and rest days it is the whole premium.
        $isOrdinary = $key === 'ordinary';
        return [
            'key' => $key,
            'overtime' => Money::round2($overtime),
            'nightDifferential' => Money::round2($isOrdinary ? $regularPay : 0),
            'holidayRestPremium' => Money::round2($isOrdinary ? 0 : $regularPay),
        ];
    }
}

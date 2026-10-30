<?php

namespace App\Payroll;

/**
 * Money helpers. Amounts are plain floats in pesos, rounded to centavos at every line.
 */
class Money
{
    /** Round to centavos, half up (ties go toward +infinity, as the totals were validated with). */
    public static function round2(float $n): float
    {
        return self::roundHalfUp(($n + PHP_FLOAT_EPSILON) * 100) / 100;
    }

    /** Sum in integer centavos so repeated additions never drift. */
    public static function sum(array $values): float
    {
        $acc = 0;
        foreach ($values as $v) {
            $acc += self::roundHalfUp($v * 100);
        }
        return $acc / 100;
    }

    /** Nearest integer, ties toward +infinity. */
    public static function roundHalfUp(float $x): float
    {
        $r = floor($x);
        return $x - $r >= 0.5 ? $r + 1 : $r;
    }

    /** Shortest decimal form of a number, used in payslip labels ("695", "1206.9"). */
    public static function str(int|float|null $n): string
    {
        if ($n === null) {
            return '';
        }
        if (is_int($n) || (floor($n) == $n && abs($n) < 1e15)) {
            return (string) (int) $n;
        }
        return json_encode($n);
    }

    /** A value read as a number the way a form field is: blank is 0, anything not numeric is NaN. */
    public static function parse(mixed $v): float
    {
        if ($v === null || is_bool($v)) {
            return (float) $v;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return 0.0;
        }
        return is_numeric($s) ? (float) $s : NAN;
    }

    public static function fixed2(float $n): string
    {
        return number_format($n, 2, '.', '');
    }
}

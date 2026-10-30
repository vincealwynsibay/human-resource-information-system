<?php

namespace App\Payroll;

/**
 * Name ordering that follows the Unicode root collation the BIR-validated files were sorted with, without
 * needing the intl extension: letters compare without accents or case first (so "de la Cruz" sits with
 * "De La Cruz" and "Peña" with "Pena"), then accents, then lower case before upper case. Spaces and
 * punctuation sort before digits, digits before letters.
 */
class Collate
{
    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y',
    ];

    public static function compare(string $a, string $b): int
    {
        return self::lexical(self::key($a, 0), self::key($b, 0))
            ?: self::lexical(self::key($a, 1), self::key($b, 1))
            ?: self::lexical(self::key($a, 2), self::key($b, 2));
    }

    /** Element by element; a prefix sorts first. (PHP's <=> on arrays compares their lengths first.) */
    private static function lexical(array $x, array $y): int
    {
        foreach ($x as $i => $v) {
            if (!array_key_exists($i, $y)) {
                return 1;
            }
            if ($v !== $y[$i]) {
                return $v <=> $y[$i];
            }
        }
        return count($x) <=> count($y);
    }

    /** Level 0: base letters; 1: accents; 2: case (lower first). Each char maps to a sortable weight. */
    private static function key(string $s, int $level): array
    {
        $out = [];
        foreach (mb_str_split($s) as $ch) {
            $lower = mb_strtolower($ch);
            $base = self::ACCENTS[$lower] ?? $lower;
            $out[] = match ($level) {
                0 => self::primary($base),
                1 => $base === $lower ? 0 : 1,
                2 => $lower === $ch ? 0 : 1,
            };
        }
        return $out;
    }

    private static function primary(string $base): int
    {
        $code = mb_ord($base);
        if (ctype_digit($base)) {
            return 100000 + $code;
        }
        if (preg_match('/\p{L}/u', $base)) {
            return 200000 + $code;
        }
        return $code; // whitespace and punctuation first
    }
}

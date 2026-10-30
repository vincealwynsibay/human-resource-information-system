<?php

namespace App\Payroll\Bank;

use Illuminate\Support\Str;

/**
 * An example fixed-width layout of the kind banks use: a header record, then one detail record per employee, amounts
 * in centavos, names in upper-case ASCII. It is not any bank's format.
 *
 *   H  company (40)  pay date YYYYMMDD (8)  record count (6)  total in centavos (15)
 *   D  account number, zero-padded (20)  account name (40)  amount in centavos (15)  reference (20)
 */
final class GenericFixedWidth implements BankFormat
{
    public function key(): string
    {
        return 'generic-fixed';
    }

    public function label(): string
    {
        return 'Generic fixed-width';
    }

    public function extension(): string
    {
        return 'txt';
    }

    public function render(array $lines, array $meta): string
    {
        $records = ['H' . self::text($meta['company'], 40) . str_replace('-', '', $meta['payDate']) . self::number($meta['count'], 6) . self::number(self::centavos($meta['total']), 15)];
        foreach ($lines as $l) {
            $records[] = 'D' . self::number($l['accountNumber'], 20) . self::text($l['accountName'], 40) . self::number(self::centavos($l['amount']), 15) . self::text($meta['reference'], 20);
        }
        return implode("\r\n", $records) . "\r\n";
    }

    private static function centavos(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private static function number(int|string $v, int $width): string
    {
        return str_pad((string) $v, $width, '0', STR_PAD_LEFT);
    }

    private static function text(string $v, int $width): string
    {
        return str_pad(substr(strtoupper(Str::ascii($v)), 0, $width), $width);
    }
}

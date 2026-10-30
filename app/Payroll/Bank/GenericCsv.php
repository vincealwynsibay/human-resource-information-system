<?php

namespace App\Payroll\Bank;

/** A plain CSV of who gets paid how much and where. Not any bank's upload format. */
final class GenericCsv implements BankFormat
{
    public function key(): string
    {
        return 'generic-csv';
    }

    public function label(): string
    {
        return 'Generic CSV';
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function render(array $lines, array $meta): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Sequence', 'Employee ID', 'Account name', 'Account number', 'Bank', 'Amount', 'Reference'], escape: '');
        foreach ($lines as $l) {
            fputcsv($out, [$l['sequence'], $l['employeeId'], $l['accountName'], $l['accountNumber'], $l['bankName'], number_format($l['amount'], 2, '.', ''), $meta['reference']], escape: '');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }
}

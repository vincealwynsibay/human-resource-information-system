<?php

namespace App\Payroll\Bank;

/**
 * A payroll credit file layout. Banks give their own layouts to corporate clients; to support one, implement this
 * and list the class under `bank_formats` in config/payroll.php.
 */
interface BankFormat
{
    /** Short id used in the download URL, e.g. "generic-csv". */
    public function key(): string;

    /** What the format is called in the app. */
    public function label(): string;

    public function extension(): string;

    /**
     * The file's contents. Each line: sequence, employeeId, name, accountName, accountNumber, bankName, amount.
     * $meta: company, payDate (YYYY-MM-DD), reference, count, total.
     */
    public function render(array $lines, array $meta): string;
}

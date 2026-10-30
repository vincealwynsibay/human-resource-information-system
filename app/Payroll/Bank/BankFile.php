<?php

namespace App\Payroll\Bank;

use App\Models\Payroll\PayrollRun;
use App\Payroll\Money;
use App\Payroll\PayrollService;
use InvalidArgumentException;

/** The credit lines for an approved or paid run: everyone with net pay and a bank account, and who lacks an account. */
final class BankFile
{
    /** @return list<BankFormat> */
    public static function formats(): array
    {
        return array_map(fn (string $class) => app($class), config('payroll.bank_formats', [GenericCsv::class, GenericFixedWidth::class]));
    }

    public static function format(string $key): BankFormat
    {
        foreach (self::formats() as $f) {
            if ($f->key() === $key) {
                return $f;
            }
        }
        throw new InvalidArgumentException("Unknown bank file format \"{$key}\".", 404);
    }

    public static function forRun(PayrollRun $run): array
    {
        if (!in_array($run->status, [PayrollRun::APPROVED, PayrollRun::PAID], true)) {
            throw new InvalidArgumentException('A bank file is made from an approved or paid run.', 409);
        }
        $slips = $run->payslips()->where('net', '>', 0)->orderBy('name')->get();
        $profiles = array_column(PayrollService::profiles($slips->pluck('employee_id')->all()), null, 'id');
        $lines = [];
        $missing = [];
        foreach ($slips as $s) {
            $bank = $profiles[$s->employee_id]['bank'] ?? null;
            if (!$bank) {
                $missing[] = ['employeeId' => $s->employee_id, 'name' => $s->name, 'amount' => $s->net];
                continue;
            }
            $lines[] = [
                'sequence' => count($lines) + 1, 'employeeId' => $s->employee_id, 'name' => $s->name,
                'accountName' => $bank['accountName'] ?? $s->name, 'accountNumber' => $bank['accountNumber'], 'bankName' => $bank['bankName'], 'amount' => $s->net,
            ];
        }
        $payDate = substr((string) $run->pay_date, 0, 10);
        return [
            'meta' => [
                'company' => config('payroll.company_name'), 'payDate' => $payDate,
                'reference' => "PAY {$payDate}", 'count' => count($lines), 'total' => Money::sum(array_column($lines, 'amount')),
            ],
            'lines' => $lines,
            'missing' => $missing,
            'missingTotal' => Money::sum(array_column($missing, 'amount')),
        ];
    }
}

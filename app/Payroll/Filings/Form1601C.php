<?php

namespace App\Payroll\Filings;

use App\Models\Payroll\PayrollRun;
use App\Payroll\Money;
use App\Payroll\PayrollService;
use App\Payroll\YearEnd;

/**
 * BIR Form 1601-C, the Monthly Remittance Return of Income Taxes Withheld on Compensation, as a worksheet: the
 * month's figures under the form's item numbers (January 2018 ENCS), ready to copy into eBIRForms or eFPS. It is
 * built from the same per-employee summary as Form 2316 and the alphalist, so the twelve months add up to the year.
 * A run counts in the month it is paid (its pay date), since tax is withheld when compensation is paid.
 */
final class Form1601C
{
    /** Approved and paid runs with a pay date in the month, the worksheet, and how many runs of the month are still open. */
    public static function forMonth(int $year, int $month): array
    {
        [$from, $to] = [sprintf('%d-%02d-01', $year, $month), date('Y-m-t', mktime(0, 0, 0, $month, 1, $year))];
        $inMonth = fn () => PayrollRun::where('pay_date', '>=', $from)->where('pay_date', '<=', $to);
        $runs = $inMonth()->whereIn('status', [PayrollRun::APPROVED, PayrollRun::PAID])->with('payslips')->orderBy('pay_date')->get();
        $slips = $runs->flatMap->payslips->pluck('data')->all();
        $sheet = self::worksheet($slips, PayrollService::minimumWageEarners(PayrollService::profiles()), $year, $month);
        return [
            ...$sheet,
            'runs' => $runs->map(fn (PayrollRun $r) => [
                ...$r->only(['id', 'kind', 'status', 'period_start', 'period_end', 'pay_date']),
                'employees' => $r->payslips->count(),
                'taxWithheld' => Money::sum($r->payslips->map(fn ($s) => self::withheld($s->data))->all()),
            ])->values()->all(),
            'openRuns' => $inMonth()->whereIn('status', [PayrollRun::DRAFT, PayrollRun::PENDING_APPROVAL])->count(),
        ];
    }

    /** The return's computation items (14 to 27) from a month's payslips. $mwe lists the minimum-wage earners' ids. */
    public static function worksheet(array $slips, array $mwe, int $year, int $month): array
    {
        // The summary keeps one calendar year; a run paid in January for a December period still belongs to this month.
        $rows = [];
        $byYear = [];
        foreach ($slips as $s) {
            $byYear[substr($s['period']['start'], 0, 4)][] = $s;
        }
        foreach ($byYear as $y => $group) {
            array_push($rows, ...YearEnd::annualSummary($group, $y, $mwe));
        }
        $sum = fn (callable $f) => Money::round2(array_sum(array_map($f, $rows)));

        $i = [];
        $i[14] = $sum(fn ($r) => $r['grossCompensation']);
        $i[15] = $sum(fn ($r) => $r['mweBasic']);
        $i[16] = $sum(fn ($r) => $r['mweHoliday'] + $r['mweOvertime'] + $r['mweNight']);
        $i[17] = $sum(fn ($r) => $r['nonTaxableThirteenth']);
        $i[18] = $sum(fn ($r) => $r['nonTaxableDeMinimis']);
        $i[19] = $sum(fn ($r) => $r['contributions']);
        $i[20] = $sum(fn ($r) => $r['nonTaxableOther']);
        $i[21] = Money::sum([$i[15], $i[16], $i[17], $i[18], $i[19], $i[20]]);
        $i[22] = Money::round2($i[14] - $i[21]);
        // Employees other than minimum-wage earners whose pay is taxable but low enough that nothing was withheld.
        $i[23] = $sum(fn ($r) => !$r['mwe'] && $r['taxWithheld'] + $r['taxRefunded'] == 0 ? max(0, $r['taxableCompensation']) : 0);
        $i[24] = Money::round2($i[22] - $i[23]);
        $i[25] = $sum(fn ($r) => $r['taxWithheld'] + $r['taxRefunded']);
        $i[26] = Money::round2(-$sum(fn ($r) => $r['taxRefunded']));
        $i[27] = Money::round2($i[25] + $i[26]);

        $notes = [];
        if ($i[26] != 0) {
            $notes[] = 'Item 26 is the over-withheld tax refunded to employees by the year-end adjustment, credited against this remittance.';
        }
        if (abs($i[22] - $sum(fn ($r) => $r['taxableCompensation'])) >= 0.01) {
            $notes[] = 'Item 22 does not match the taxable compensation on the payslips; check the runs before filing.';
        }
        $notes[] = 'Items 28 to 35 (amended returns, other remittances, penalties) are filled in eBIRForms when they apply.';

        return [
            'form' => '1601-C', 'year' => $year, 'month' => $month, 'employees' => count(array_unique(array_column($rows, 'employeeId'))),
            'dueDate' => self::dueDate($year, $month),
            'items' => array_map(fn ($n) => ['item' => $n, 'label' => self::LABELS[$n], 'amount' => $i[$n]], array_keys($i)),
            'notes' => $notes,
        ];
    }

    /** Manual and eBIRForms filers: the 10th of the following month, except the December return, due 15 January. eFPS filers file by industry group, 11th to 15th. */
    public static function dueDate(int $year, int $month): string
    {
        return $month === 12 ? sprintf('%d-01-15', $year + 1) : date('Y-m-10', mktime(0, 0, 0, $month + 1, 1, $year));
    }

    private static function withheld(array $slip): float
    {
        return Money::sum(array_column(array_filter($slip['deductions'] ?? [], fn ($d) => $d['code'] === 'WITHHOLDING_TAX'), 'amount'));
    }

    private const LABELS = [
        14 => 'Total amount of compensation',
        15 => 'Statutory minimum wage (minimum wage earners)',
        16 => 'Holiday pay, overtime pay, night shift differential and hazard pay (minimum wage earners)',
        17 => '13th month pay and other benefits',
        18 => 'De minimis benefits',
        19 => 'SSS, GSIS, PHIC, HDMF mandatory contributions and union dues (employee share)',
        20 => 'Other non-taxable compensation',
        21 => 'Total non-taxable compensation (items 15 to 20)',
        22 => 'Total taxable compensation (item 14 less item 21)',
        23 => 'Less: taxable compensation not subject to withholding tax (employees other than MWEs earning P250,000 or less a year)',
        24 => 'Net taxable compensation (item 22 less item 23)',
        25 => 'Total taxes withheld',
        26 => 'Add/(less): adjustment of taxes withheld from previous months',
        27 => 'Taxes withheld for remittance (items 25 and 26)',
    ];
}

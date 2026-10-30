<?php

namespace App\Payroll\Filings;

use App\Models\Payroll\PayrollRun;
use App\Payroll\Money;
use App\Payroll\PayrollService;
use App\Payroll\Tables;

/**
 * A month's SSS, PhilHealth and Pag-IBIG contributions per employee: the details behind the SSS contribution list
 * (R-3), the PhilHealth remittance report (RF-1) and the Pag-IBIG remittance, for entering in each agency's portal.
 * Contributions belong to the month the pay is for, so a month covers the approved and paid regular runs whose period
 * starts in it. Every figure is what the payslips actually deducted; the salary credit or base each agency asks for
 * is worked back from those amounts with the same tables the payslips used.
 */
final class Remittances
{
    public const AGENCIES = ['sss', 'philhealth', 'pagibig'];

    public static function forMonth(int $year, int $month): array
    {
        [$from, $to] = [sprintf('%d-%02d-01', $year, $month), date('Y-m-t', mktime(0, 0, 0, $month, 1, $year))];
        $inMonth = fn () => PayrollRun::where('kind', 'regular')->where('period_start', '>=', $from)->where('period_start', '<=', $to);
        $runs = $inMonth()->whereIn('status', [PayrollRun::APPROVED, PayrollRun::PAID])->with('payslips')->orderBy('period_start')->get();
        $profiles = array_column(PayrollService::profiles(), null, 'id');
        return [
            'year' => $year, 'month' => $month,
            ...self::build($runs->flatMap->payslips->pluck('data')->all(), $profiles),
            'runs' => $runs->map(fn (PayrollRun $r) => [...$r->only(['id', 'status', 'period_start', 'period_end', 'pay_date']), 'employees' => $r->payslips->count()])->values()->all(),
            'openRuns' => $inMonth()->whereIn('status', [PayrollRun::DRAFT, PayrollRun::PENDING_APPROVAL])->count(),
        ];
    }

    /** Per-agency rows and totals from a month's payslips. $profiles (by employee id) supply the members' numbers. */
    public static function build(array $slips, array $profiles): array
    {
        $by = [];
        foreach ($slips as $s) {
            $id = $s['employeeId'];
            $by[$id] ??= ['employeeId' => $id, 'name' => $s['name'], 'sss' => 0, 'philhealth' => 0, 'pagibig' => 0, 'er' => ['sss' => 0, 'ec' => 0, 'philhealth' => 0, 'pagibig' => 0]];
            $e = &$by[$id];
            foreach ($s['deductions'] as $d) {
                $key = ['SSS' => 'sss', 'PHILHEALTH' => 'philhealth', 'PAGIBIG' => 'pagibig'][$d['code']] ?? null;
                if ($key) {
                    $e[$key] = Money::round2($e[$key] + $d['amount']);
                }
            }
            foreach (['sss', 'ec', 'philhealth', 'pagibig'] as $k) {
                $e['er'][$k] = Money::round2($e['er'][$k] + ($s['employer'][$k] ?? 0));
            }
            unset($e);
        }
        uasort($by, fn ($a, $b) => strcmp($a['name'], $b['name']));

        $sssTable = Tables::get('sss');
        $out = ['employees' => count($by)];
        foreach (self::AGENCIES as $agency) {
            $rows = [];
            foreach ($by as $e) {
                if ($e[$agency] == 0 && $e['er'][$agency] == 0) {
                    continue;
                }
                $p = $profiles[$e['employeeId']] ?? [];
                $row = [
                    'employeeId' => $e['employeeId'], 'name' => $e['name'], 'lastName' => $p['lastName'] ?? null, 'firstName' => $p['firstName'] ?? null, 'middleName' => $p['middleName'] ?? null,
                    'number' => $p['ids'][$agency] ?? null, 'employee' => $e[$agency], 'employer' => $e['er'][$agency],
                ];
                if ($agency === 'sss') {
                    // The bracket whose employee share matches what was deducted gives the salary credit and its MPF part.
                    $bracket = current(array_filter($sssTable['brackets'], fn ($b) => abs($b['employee'] - $e['sss']) < 0.005 && abs($b['employer'] - $e['er']['sss']) < 0.005)) ?: null;
                    $mpfShare = $bracket ? $bracket['mpf_msc'] / $bracket['msc'] : 0;
                    // No matching bracket: the month is not complete yet (say one cutoff approved), or was adjusted by hand.
                    $row += [
                        'msc' => $bracket['msc'] ?? null, 'ec' => $e['er']['ec'],
                        'employeeMpf' => Money::round2($e['sss'] * $mpfShare), 'employerMpf' => Money::round2($e['er']['sss'] * $mpfShare),
                        'partial' => $bracket === null,
                    ];
                } else {
                    // PhilHealth: 5% of the clamped monthly basic salary. Pag-IBIG: the employer always pays 2% of the capped base.
                    $row['base'] = $agency === 'philhealth' ? Money::round2(($e['philhealth'] + $e['er']['philhealth']) / 0.05) : Money::round2($e['er']['pagibig'] / 0.02);
                }
                $row['total'] = Money::sum([$row['employee'], $row['employer'], $row['ec'] ?? 0]);
                $rows[] = $row;
            }
            $sum = fn (string $k) => Money::sum(array_map(fn ($r) => $r[$k] ?? 0, $rows));
            $out[$agency] = [
                'rows' => $rows,
                'totals' => array_filter(['employee' => $sum('employee'), 'employer' => $sum('employer'), 'ec' => $agency === 'sss' ? $sum('ec') : null, 'total' => $sum('total')], fn ($v) => $v !== null),
                'missingNumbers' => array_values(array_map(fn ($r) => $r['name'], array_filter($rows, fn ($r) => !$r['number']))),
            ];
        }
        return $out;
    }

    public const TITLES = ['sss' => 'SSS contributions (R-3)', 'philhealth' => 'PhilHealth premiums (RF-1)', 'pagibig' => 'Pag-IBIG contributions'];

    /** One agency's list as CSV, last name first as the agencies list members. */
    public static function csv(array $report, string $agency): string
    {
        $head = ['Employee ID', ['sss' => 'SS number', 'philhealth' => 'PhilHealth number', 'pagibig' => 'Pag-IBIG MID'][$agency], 'Last name', 'First name', 'Middle name'];
        $head = [...$head, ...match ($agency) {
            'sss' => ['Monthly salary credit', 'Employee share', 'Employer share', 'EC', 'Total', 'MPF in employee share', 'MPF in employer share'],
            'philhealth' => ['Monthly basic salary', 'Personal share', 'Employer share', 'Total'],
            'pagibig' => ['Monthly compensation (capped)', 'Employee share', 'Employer share', 'Total'],
        }];
        $money = fn ($v) => $v === null ? '' : number_format((float) $v, 2, '.', '');
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $head, escape: '');
        foreach ($report[$agency]['rows'] as $r) {
            $names = [$r['employeeId'], $r['number'] ?? '', $r['lastName'] ?? $r['name'], $r['firstName'] ?? '', $r['middleName'] ?? ''];
            fputcsv($out, [...$names, ...array_map($money, match ($agency) {
                'sss' => [$r['msc'], $r['employee'], $r['employer'], $r['ec'], $r['total'], $r['employeeMpf'], $r['employerMpf']],
                default => [$r['base'], $r['employee'], $r['employer'], $r['total']],
            })], escape: '');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }
}

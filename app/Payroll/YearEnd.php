<?php

namespace App\Payroll;

use InvalidArgumentException;

/**
 * Year-end and exit computations: 13th-month payout run, final pay, annual compensation summary, BIR Form 2316
 * figures and the year-end tax adjustment. Results use the same payslip shape as Payslip::compute.
 */
class YearEnd
{
    /** BIR Form 2316, September 2021 (ENCS) version: item number and label, in form order. */
    public const FORM_2316_ITEMS = [
        ['19', 'Gross Compensation Income from Present Employer (Sum of Items 38 and 52)'],
        ['20', 'Less: Total Non-Taxable/Exempt Compensation Income from Present Employer (From Item 38)'],
        ['21', 'Taxable Compensation Income from Present Employer (Item 19 Less Item 20) (From Item 52)'],
        ['22', 'Add: Taxable Compensation Income from Previous Employer, if applicable'],
        ['23', 'Gross Taxable Compensation Income (Sum of Items 21 and 22)'],
        ['24', 'Tax Due'],
        ['25A', 'Amount of Taxes Withheld: Present Employer'],
        ['25B', 'Amount of Taxes Withheld: Previous Employer, if applicable'],
        ['26', 'Total Amount of Taxes Withheld as adjusted (Sum of Items 25A and 25B)'],
        ['27', '5% Tax Credit (PERA Act of 2008)'],
        ['28', 'Total Taxes Withheld (Sum of Items 26 and 27)'],
        ['29', 'Basic Salary (including the exempt P250,000 & below) or the Statutory Minimum Wage of the MWE'],
        ['30', 'Holiday Pay (MWE)'], ['31', 'Overtime Pay (MWE)'], ['32', 'Night Shift Differential (MWE)'], ['33', 'Hazard Pay (MWE)'],
        ['34', '13th Month Pay and Other Benefits (maximum of P90,000)'],
        ['35', 'De Minimis Benefits'],
        ['36', 'SSS, GSIS, PHIC & PAG-IBIG Contributions and Union Dues (Employee share only)'],
        ['37', 'Salaries and Other Forms of Compensation'],
        ['38', 'Total Non-Taxable/Exempt Compensation Income (Sum of Items 29 to 37)'],
        ['39', 'Basic Salary'], ['40', 'Representation'], ['41', 'Transportation'], ['42', 'Cost of Living Allowance (COLA)'], ['43', 'Fixed Housing Allowance'],
        ['44', 'Others (specify)'], ['45', 'Commission'], ['46', 'Profit Sharing'], ['47', "Fees Including Director's Fees"],
        ['48', 'Taxable 13th Month Benefits'], ['49', 'Hazard Pay'], ['50', 'Overtime Pay'], ['51', 'Others (specify)'],
        ['52', 'Total Taxable Compensation Income (Sum of Items 39 to 51B)'],
    ];

    /** Whole years of age on a date (birthday not yet reached this year counts one less). */
    public static function ageOn(string $birthDate, string $date): int
    {
        [$by, $bm, $bd] = array_map('intval', explode('-', $birthDate));
        [$y, $m, $d] = array_map('intval', explode('-', $date));
        return $y - $by - ($m < $bm || ($m === $bm && $d < $bd) ? 1 : 0);
    }

    /** Calendar months from a to b (ISO dates): whole months, plus the leftover days as a fraction of a month. */
    public static function monthsBetween(string $a, string $b): float
    {
        [$ay, $am, $ad] = array_map('intval', explode('-', $a));
        [$by, $bm, $bd] = array_map('intval', explode('-', $b));
        $whole = ($by - $ay) * 12 + ($bm - $am) - ($bd < $ad ? 1 : 0);
        $anchor = gmmktime(0, 0, 0, $am + $whole, $ad, $ay);
        return $whole + (gmmktime(0, 0, 0, $bm, $bd, $by) - $anchor) / 86400 / 30.4375;
    }

    private static function slip(array $employee, array $period, array $earnings, array $notes = [], array $deductions = []): array
    {
        $gross = Money::sum(array_column($earnings, 'amount'));
        $totalDeductions = Money::sum(array_column($deductions, 'amount'));
        return [
            'employeeId' => $employee['id'], 'name' => $employee['name'] ?? null, 'period' => $period, 'cutoff' => null,
            'earnings' => $earnings, 'deductions' => $deductions, 'loans' => [], 'gross' => $gross, 'totalDeductions' => $totalDeductions,
            'taxableIncome' => Money::sum(array_column(array_filter($earnings, fn ($e) => $e['taxable']), 'amount')),
            'net' => Money::round2($gross - $totalDeductions), 'employer' => ['total' => 0], 'notes' => $notes,
        ];
    }

    private static function sumCodes(array $lines, string ...$codes): float
    {
        return Money::sum(array_column(array_filter($lines, fn ($x) => in_array($x['code'], $codes, true)), 'amount'));
    }

    /**
     * 13th-month pay (PD 851) for every profile: total basic salary earned in the year / 12. `earnedBasic[id]`
     * overrides the estimate (monthly salary x months worked, 12 by default; a profile may carry
     * `monthsWorkedInYear`). The part up to the combined ceiling is tax-exempt; any excess is taxable and is
     * settled by the year-end annualization, not withheld here.
     */
    public static function thirteenthMonthRun(array $profiles, int|string $year, array $earnedBasic = [], array $otherBonuses = []): array
    {
        $period = ['start' => "{$year}-01-01", 'end' => "{$year}-12-31", 'frequency' => 'ANNUALLY'];
        $slips = [];
        foreach ($profiles as $p) {
            $earned = $earnedBasic[$p['id']] ?? Money::round2(Profile::monthlyEquivalent($p) * ($p['monthsWorkedInYear'] ?? 12));
            $bonus = $otherBonuses[$p['id']] ?? 0;
            $t = Thirteenth::compute($earned, $bonus);
            $exempt = Money::round2(max(0, min($t['amount'], $t['taxExemptCeiling'] - $bonus)));
            $lines = [];
            if ($exempt > 0) {
                $lines[] = ['code' => 'THIRTEENTH', 'label' => '13th-month pay (tax-exempt part, basic earned ' . Money::fixed2($earned) . ' / 12)', 'amount' => $exempt, 'taxable' => false];
            }
            if ($t['taxableExcess'] > 0) {
                $lines[] = ['code' => 'THIRTEENTH', 'label' => '13th-month pay (taxable excess over the combined P90,000 ceiling)', 'amount' => $t['taxableExcess'], 'taxable' => true];
            }
            $s = self::slip($p, $period, $lines, $t['taxableExcess'] > 0 ? ['Tax on the taxable excess is settled at year-end annualization; nothing was withheld here.'] : []);
            if ($s['gross'] > 0) {
                $slips[] = $s;
            }
        }
        $totals = ['gross' => Money::sum(array_column($slips, 'gross')), 'deductions' => 0, 'net' => Money::sum(array_column($slips, 'net')), 'employerCost' => 0];
        return ['period' => $period, 'schedule' => 'n/a', 'payDate' => "{$year}-12-24", 'totals' => $totals, 'slips' => $slips];
    }

    /**
     * Final pay on separation (DOLE Labor Advisory 06-2020; Labor Code Arts. 298-299; RA 7641 for retirement):
     * unpaid salary, cash conversion of unused Service Incentive Leave, pro-rated 13th month, and either
     * separation pay by cause or statutory retirement pay (cause "retirement", needs birthDate).
     * Pass `ytd` (taxableCompensation, taxWithheld for the year so far) to annualize: the difference between the
     * annual tax due and the tax already withheld becomes a deduction or a refund line.
     */
    public static function finalPay(array $a): array
    {
        $separation = Tables::get('separation');
        $retirement = Tables::get('retirement');
        $employee = $a['employee'];
        $hireDate = $a['hireDate'];
        $separationDate = $a['separationDate'];
        $cause = $a['cause'] ?? 'resignation';
        $unpaidDays = $a['unpaidDays'] ?? 0;
        $unusedSilDays = $a['unusedSilDays'] ?? 0;
        $otherBonuses = $a['otherBonuses'] ?? 0;
        $ytd = $a['ytd'] ?? null;
        $clearance = $a['clearance'] ?? [];

        $retiring = $cause === 'retirement';
        $rule = $retiring ? null : ($separation['causes'][$cause] ?? null);
        if (!$retiring && !$rule) {
            throw new InvalidArgumentException("Unknown separation cause \"{$cause}\". Known: " . implode(', ', [...array_keys($separation['causes']), 'retirement']));
        }
        if ($separationDate < $hireDate) {
            throw new InvalidArgumentException('separationDate is before hireDate');
        }
        $daily = Profile::dailyRate($employee);
        $monthly = Profile::monthlyEquivalent($employee);
        $lines = [];
        $notes = [];

        if ($unpaidDays > 0) {
            $lines[] = ['code' => 'UNPAID_SALARY', 'label' => 'Unpaid salary (' . Money::str($unpaidDays) . ' day/s)', 'amount' => Money::round2($daily * $unpaidDays), 'taxable' => true];
        }

        $serviceMonths = self::monthsBetween($hireDate, $separationDate);
        $silMonths = $separation['rules']['sil_eligibility_months'];
        if ($unusedSilDays > 0) {
            if ($serviceMonths >= $silMonths) {
                $lines[] = ['code' => 'SIL_CONVERSION', 'label' => 'Unused Service Incentive Leave (' . Money::str($unusedSilDays) . ' day/s)', 'amount' => Money::round2($daily * $unusedSilDays), 'taxable' => true];
            } else {
                $notes[] = "Less than {$silMonths} months of service: Service Incentive Leave is not yet due, so no cash conversion.";
            }
        }

        $year = substr($separationDate, 0, 4);
        $yearStart = substr($hireDate, 0, 4) === $year ? $hireDate : "{$year}-01-01";
        $earned = $a['earnedBasicYear'] ?? Money::round2($monthly * min(12, self::monthsBetween($yearStart, $separationDate)));
        $t = Thirteenth::compute($earned, $otherBonuses);
        $exempt13 = Money::round2(max(0, min($t['amount'], $t['taxExemptCeiling'] - $otherBonuses)));
        if ($exempt13 > 0) {
            $lines[] = ['code' => 'THIRTEENTH', 'label' => 'Pro-rated 13th-month pay (basic earned ' . Money::fixed2($earned) . ' / 12)', 'amount' => $exempt13, 'taxable' => false];
        }
        if ($t['taxableExcess'] > 0) {
            $lines[] = ['code' => 'THIRTEENTH', 'label' => 'Pro-rated 13th-month pay (taxable excess)', 'amount' => $t['taxableExcess'], 'taxable' => true];
        }

        $years = (int) floor($serviceMonths / 12);
        if ($serviceMonths - $years * 12 >= $separation['rules']['fraction_counts_as_year_months']) {
            $years += 1;
        }

        if ($retiring) {
            if (empty($a['birthDate'])) {
                throw new InvalidArgumentException('birthDate is required to compute retirement pay');
            }
            $r = $retirement['rules'];
            $age = self::ageOn($a['birthDate'], $separationDate);
            if ($age < $r['min_age'] || $serviceMonths < $r['min_service_years'] * 12) {
                $notes[] = "Not eligible for statutory retirement pay: it needs age {$r['min_age']}+ and {$r['min_service_years']}+ years of service (age {$age}, " . number_format($serviceMonths / 12, 1, '.', '') . ' years of service).';
            } else {
                $d = $r['half_month_salary_days'];
                $days = $d['base'] + $d['thirteenth_month_share'] + $d['service_incentive_leave'];
                $taxExempt = $age >= 50 && $serviceMonths >= 120 && ($a['firstTimeRetirement'] ?? true);
                $lines[] = ['code' => 'RETIREMENT_PAY', 'label' => 'Retirement pay (RA 7641): ' . Money::str($days) . " days x {$years} year/s x " . Money::fixed2($daily) . ' a day', 'amount' => Money::round2($days * $daily * $years), 'taxable' => !$taxExempt];
                if (!$taxExempt) {
                    $notes[] = 'Retirement pay is treated as taxable: the 10-years-of-service, age 50 and first-time conditions for exemption are not all met.';
                }
                if ($age > $r['compulsory_age']) {
                    $notes[] = "Above the compulsory retirement age of {$r['compulsory_age']}.";
                }
            }
        } else {
            $months = max($rule['minimum_months'], $rule['months_per_year'] * $years);
            if ($months > 0) {
                $lines[] = ['code' => 'SEPARATION_PAY', 'label' => "Separation pay: {$rule['label']} (" . Money::str($months) . ' month/s x ' . Money::fixed2($monthly) . ", {$years} year/s of service)", 'amount' => Money::round2($months * $monthly), 'taxable' => false];
            } elseif ($cause !== 'resignation' && $cause !== 'just_cause') {
                $notes[] = "{$rule['label']}: no separation pay required.";
            }
        }

        $deductions = [];
        if ($ytd) {
            $taxableNow = Money::sum(array_column(array_filter($lines, fn ($l) => $l['taxable']), 'amount'));
            $due = Tax::withholding(max(0, $ytd['taxableCompensation'] + $taxableNow), 'ANNUALLY');
            $trueUp = Money::round2($due - $ytd['taxWithheld']);
            if ($trueUp > 0) {
                $deductions[] = ['code' => 'WITHHOLDING_TAX', 'label' => 'Withholding tax true-up (annualization)', 'amount' => $trueUp];
            } elseif ($trueUp < 0) {
                $lines[] = ['code' => 'TAX_REFUND', 'label' => 'Refund of over-withheld tax (annualization)', 'amount' => -$trueUp, 'taxable' => false];
            }
            $notes[] = 'Annualized on year-to-date taxable pay ' . Money::fixed2($ytd['taxableCompensation']) . ' and tax withheld ' . Money::fixed2($ytd['taxWithheld']) . '.';
        } else {
            $notes[] = 'Withholding-tax true-up not computed: no year-to-date figures were supplied.';
        }
        foreach ($clearance as $c) {
            $amount = Money::round2((float) ($c['amount'] ?? 0));
            if (trim((string) ($c['name'] ?? '')) === '' || !($amount > 0)) {
                throw new InvalidArgumentException('Each clearance deduction needs a description and a positive amount');
            }
            $deductions[] = ['code' => 'CLEARANCE', 'label' => trim($c['name']), 'amount' => $amount];
        }
        if ($clearance) {
            $notes[] = 'Clearance deductions are only valid for amounts the employee authorized or the law allows (Labor Code Art. 113).';
        }
        $owed = Money::sum(array_column($lines, 'amount'));
        $deducted = Money::sum(array_column($deductions, 'amount'));
        if ($deducted > $owed) {
            throw new InvalidArgumentException('Deductions (' . Money::fixed2($deducted) . ') exceed the final pay (' . Money::fixed2($owed) . ')');
        }
        $notes[] = "Release within {$separation['rules']['final_pay_days']} days of separation.";

        $period = ['start' => $separationDate, 'end' => $separationDate, 'frequency' => 'MONTHLY']; // a final-pay run is a single-day MONTHLY run
        return [...self::slip($employee, $period, $lines, $notes, $deductions), 'serviceYears' => $years, 'separation' => ['cause' => $cause, 'date' => $separationDate]];
    }

    /**
     * Annual compensation summary per employee from payslips: the figures a BIR Form 2316 needs. `$mwe` lists the
     * ids of minimum-wage earners: their basic pay, holiday pay, overtime and night differential are exempt
     * (form items 29 to 32) and carry no tax due.
     */
    public static function annualSummary(array $slips, int|string $year, array $mwe = []): array
    {
        $zero = ['grossCompensation' => 0, 'nonTaxable' => 0, 'nonTaxableThirteenth' => 0, 'nonTaxableDeMinimis' => 0, 'nonTaxableOther' => 0, 'contributions' => 0, 'taxableCompensation' => 0, 'taxWithheld' => 0,
            'basicTaxable' => 0, 'overtimeTaxable' => 0, 'thirteenthTaxable' => 0, 'othersTaxable' => 0, 'mweBasic' => 0, 'mweHoliday' => 0, 'mweOvertime' => 0, 'mweNight' => 0,
            'taxRefunded' => 0, 'taxWithheldDecember' => 0, 'taxYearEndAdjustment' => 0];
        $by = [];
        foreach ($slips as $s) {
            if (!str_starts_with($s['period']['start'], (string) $year)) {
                continue;
            }
            $id = $s['employeeId'];
            $by[$id] ??= ['employeeId' => $id, 'name' => $s['name'], 'mwe' => in_array($id, $mwe, true), ...$zero];
            $e = &$by[$id];
            // A refund of over-withheld tax is not compensation: it reduces the tax withheld instead.
            $pay = array_values(array_filter($s['earnings'], fn ($x) => $x['code'] !== 'TAX_REFUND'));
            $refund = self::sumCodes($s['earnings'], 'TAX_REFUND');
            $attendance = self::sumCodes($s['deductions'], 'ABSENCE', 'LATE', 'UNDERTIME');
            $contrib = self::sumCodes($s['deductions'], 'SSS', 'PHILHEALTH', 'PAGIBIG');
            $exempt = array_values(array_filter($pay, fn ($x) => !$x['taxable']));
            $taxable = array_values(array_filter($pay, fn ($x) => $x['taxable']));
            $add = function (string $field, float $v) use (&$e) {
                $e[$field] = Money::round2($e[$field] + $v);
            };

            $add('grossCompensation', Money::sum(array_column($pay, 'amount')) - $attendance);
            $add('nonTaxable', Money::sum(array_column($exempt, 'amount')));
            $add('nonTaxableThirteenth', self::sumCodes($exempt, 'THIRTEENTH'));
            $add('nonTaxableDeMinimis', self::sumCodes($exempt, 'ALLOWANCE'));
            $add('nonTaxableOther', Money::sum(array_column($exempt, 'amount')) - self::sumCodes($exempt, 'THIRTEENTH', 'ALLOWANCE'));
            $add('contributions', $contrib);
            $add('taxWithheld', self::sumCodes($s['deductions'], 'WITHHOLDING_TAX') - $refund);
            $add('taxRefunded', $refund);
            if (substr($s['period']['start'], 5, 2) === '12') {
                $add('taxWithheldDecember', self::sumCodes($s['deductions'], 'WITHHOLDING_TAX'));
            }
            // Tax collected by the year-end adjustment is "amount withheld and paid for in December" (2316 item 25B); everything else is item 25A.
            $add('taxYearEndAdjustment', Money::sum(array_column(array_filter($s['deductions'], fn ($d) => $d['code'] === 'WITHHOLDING_TAX' && preg_match('/year-end annualization/i', $d['label'] ?? '')), 'amount')));
            if (!empty($s['separation'])) {
                $e['separation'] = $s['separation'];
            }
            if ($s['period']['start'] < ($e['firstPayDate'] ?? '9999')) {
                $e['firstPayDate'] = $s['period']['start'];
            }

            $basic = self::sumCodes($taxable, 'BASIC', 'UNPAID_SALARY');
            $holiday = self::sumCodes($taxable, 'HOLIDAY_PAY', 'HOLIDAY_PREMIUM');
            $overtime = self::sumCodes($taxable, 'OVERTIME');
            $night = self::sumCodes($taxable, 'NIGHT_DIFF');
            $taxableSum = Money::sum(array_column($taxable, 'amount'));
            if ($e['mwe']) {
                // The exempt basic pay is what is left after attendance deductions and the contributions taken out of it.
                $add('mweBasic', $basic - $attendance - $contrib);
                $add('mweHoliday', $holiday);
                $add('mweOvertime', $overtime);
                $add('mweNight', $night);
                $rest = $taxableSum - $basic - $holiday - $overtime - $night;
                $add('taxableCompensation', $rest);
                $add('thirteenthTaxable', self::sumCodes($taxable, 'THIRTEENTH'));
                $add('othersTaxable', $rest - self::sumCodes($taxable, 'THIRTEENTH'));
            } else {
                $add('taxableCompensation', $taxableSum - $attendance - $contrib);
                $add('basicTaxable', $basic - $attendance - $contrib);
                $add('overtimeTaxable', $overtime);
                $add('thirteenthTaxable', self::sumCodes($taxable, 'THIRTEENTH'));
                $add('othersTaxable', $taxableSum - $basic - $overtime - self::sumCodes($taxable, 'THIRTEENTH'));
            }
            unset($e);
        }
        $rows = array_map(function ($e) use ($year) {
            $taxDue = $e['mwe'] ? 0 : Tax::withholding(max(0, $e['taxableCompensation']), 'ANNUALLY');
            return ['year' => $year, ...$e, 'taxDue' => $taxDue, 'overWithheld' => Money::round2($e['taxWithheld'] - $taxDue)];
        }, array_values($by));
        usort($rows, fn ($x, $y) => Collate::compare($x['name'], $y['name']));
        return $rows;
    }

    private static function csvCell(mixed $v): string
    {
        $s = is_string($v) ? $v : Money::str($v);
        return preg_match('/[",\n]/', $s) ? '"' . str_replace('"', '""', $s) . '"' : $s;
    }

    /** The annual summary as CSV, one named column per figure (form item numbers vary between versions). */
    public static function annualCsv(array $rows): string
    {
        $cols = [
            ['TIN', fn ($r) => $r['ids']['tin'] ?? ''], ['Employee', fn ($r) => $r['name']], ['Year', fn ($r) => $r['year']],
            ['Gross compensation (present employer)', fn ($r) => $r['grossCompensation']],
            ['Non-taxable: 13th month and other benefits', fn ($r) => $r['nonTaxableThirteenth']],
            ['Non-taxable: other (separation, retirement, allowances)', fn ($r) => $r['nonTaxableOther']],
            ['Non-taxable: SSS, PhilHealth, Pag-IBIG contributions', fn ($r) => $r['contributions']],
            ['Taxable compensation', fn ($r) => $r['taxableCompensation']],
            ['Tax due', fn ($r) => $r['taxDue']], ['Tax withheld', fn ($r) => $r['taxWithheld']], ['Over / (under) withheld', fn ($r) => $r['overWithheld']],
            ['SSS no.', fn ($r) => $r['ids']['sss'] ?? ''], ['PhilHealth no.', fn ($r) => $r['ids']['philhealth'] ?? ''], ['Pag-IBIG no.', fn ($r) => $r['ids']['pagibig'] ?? ''],
        ];
        $lines = [implode(',', array_map(fn ($c) => self::csvCell($c[0]), $cols))];
        foreach ($rows as $r) {
            $lines[] = implode(',', array_map(fn ($c) => self::csvCell($c[1]($r)), $cols));
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Map an annual summary row onto the form's item numbers. Items there is no data for (previous employer, PERA
     * credit, representation, COLA, housing, commission, profit sharing, fees, hazard pay) are 0. An employee whose
     * taxable compensation is P250,000 or less has the taxable basic salary reported in the exempt item 29.
     */
    public static function annual2316Row(array $r): array
    {
        $items = array_fill_keys(array_column(self::FORM_2316_ITEMS, 0), 0);
        $notes = [];
        $basic = $r['basicTaxable'];
        if (!$r['mwe'] && $r['taxableCompensation'] <= 250000 && $basic > 0) {
            $items['29'] = $basic;
            $basic = 0;
            $notes[] = 'Taxable compensation is P250,000 or less: basic salary reported in item 29 (exempt).';
        }
        if ($r['mwe']) {
            $items['29'] = $r['mweBasic'];
            $items['30'] = $r['mweHoliday'];
            $items['31'] = $r['mweOvertime'];
            $items['32'] = $r['mweNight'];
            $notes[] = 'Minimum-wage earner: basic, holiday, overtime and night differential reported as exempt (items 29 to 32).';
        }
        $items['34'] = $r['nonTaxableThirteenth'];
        $items['35'] = $r['nonTaxableDeMinimis'];
        $items['36'] = $r['contributions'];
        $items['37'] = $r['nonTaxableOther'];
        $items['38'] = Money::round2(self::addItems($items, ['29', '30', '31', '32', '33', '34', '35', '36', '37']));
        $items['39'] = $basic;
        $items['48'] = $r['thirteenthTaxable'];
        $items['50'] = $r['overtimeTaxable'];
        $items['51'] = $r['othersTaxable'];
        $items['52'] = Money::round2(self::addItems($items, ['39', '40', '41', '42', '43', '44', '45', '46', '47', '48', '49', '50', '51']));
        $items['19'] = Money::round2($items['38'] + $items['52']);
        $items['20'] = $items['38'];
        $items['21'] = Money::round2($items['19'] - $items['20']);
        $items['23'] = $items['21'];
        $items['24'] = $r['taxDue'];
        $items['25A'] = $r['taxWithheld'];
        $items['26'] = $items['25A'];
        $items['28'] = $items['26'];
        return [
            'employeeId' => $r['employeeId'], 'name' => $r['name'], 'year' => $r['year'], 'tin' => $r['ids']['tin'] ?? '',
            'items' => $items, 'notes' => $notes, 'balanced' => abs($items['19'] - $r['grossCompensation']) < 0.005,
        ];
    }

    /** Plain left-to-right float addition, as the form totals were validated with. */
    private static function addItems(array $items, array $keys): float
    {
        $a = 0;
        foreach ($keys as $k) {
            $a += $items[$k];
        }
        return $a;
    }

    /** One row per employee, one column per form item (item number and label in the header). */
    public static function annual2316Csv(array $rows): string
    {
        $head = ['TIN', 'Employee', 'Year', ...array_map(fn ($i) => "Item {$i[0]}: {$i[1]}", self::FORM_2316_ITEMS), 'Notes'];
        $lines = [implode(',', array_map(self::csvCell(...), $head))];
        foreach (array_map(self::annual2316Row(...), $rows) as $o) {
            $cells = [$o['tin'], $o['name'], $o['year'], ...array_map(fn ($i) => $o['items'][$i[0]], self::FORM_2316_ITEMS), implode(' ', $o['notes'])];
            $lines[] = implode(',', array_map(self::csvCell(...), $cells));
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Year-end tax adjustment (annualization) for employees still on payroll: over-withheld tax is refunded (a
     * non-taxable refund line); tax still due is a deduction to take from the next pay. Separated employees were
     * annualized in their final pay and minimum-wage earners owe nothing, so both are left out.
     */
    public static function yearEndAdjustmentRun(array $rows, int|string $year): array
    {
        $period = ['start' => "{$year}-12-31", 'end' => "{$year}-12-31", 'frequency' => 'MONTHLY'];
        $slips = [];
        foreach ($rows as $r) {
            if (!empty($r['separation']) || $r['mwe']) {
                continue;
            }
            $diff = Money::round2($r['taxWithheld'] - $r['taxDue']); // positive: over-withheld
            if (abs($diff) < 0.01) {
                continue;
            }
            $employee = ['id' => $r['employeeId'], 'name' => $r['name']];
            $note = 'Annual tax due ' . Money::fixed2($r['taxDue']) . ' against ' . Money::fixed2($r['taxWithheld']) . ' withheld during the year.';
            $slips[] = $diff > 0
                ? self::slip($employee, $period, [['code' => 'TAX_REFUND', 'label' => 'Refund of over-withheld tax (year-end annualization)', 'amount' => $diff, 'taxable' => false]], [$note])
                : self::slip($employee, $period, [], [$note, 'Deduct this from the next pay: this slip carries only the deduction.'], [['code' => 'WITHHOLDING_TAX', 'label' => 'Tax still due (year-end annualization)', 'amount' => -$diff]]);
        }
        $totals = ['gross' => Money::sum(array_column($slips, 'gross')), 'deductions' => Money::sum(array_column($slips, 'totalDeductions')), 'net' => Money::sum(array_column($slips, 'net')), 'employerCost' => 0];
        return ['period' => $period, 'schedule' => 'n/a', 'totals' => $totals, 'slips' => $slips];
    }
}

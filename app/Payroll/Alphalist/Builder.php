<?php

namespace App\Payroll\Alphalist;

use App\Payroll\Collate;
use App\Payroll\Money;
use App\Payroll\Profile;
use App\Payroll\YearEnd;

/**
 * Builds the BIR annual alphalist of employees (Form 1604-C) data file from the annual summary.
 *
 * Record structure, order, types, widths and lists of values come from RMC 25-2024 Annex "A" (see Spec). The line
 * format, which the annexes do not state (what is quoted, zero-padding, record order, one running sequence number,
 * region codes), follows the rules the official BIR Alphalist Validation Module 7.4 enforces: files built this way
 * were accepted by that module with "No Errors Encountered" on 30 Oct 2026, and corrupted copies were rejected.
 * Still run real files through the module before filing.
 */
class Builder
{
    public const NEWLINE = "\r\n";

    public const ASSUMPTIONS = [
        'Line format (quoting, zero-padding, record order, one running sequence number) follows what the official Alphalist 7.4 validation module accepts; files built this way passed it on 30 Oct 2026.',
        'The file always includes the control record for each schedule it contains.',
        'PRES_NONTAX_GROSS_COMP_INCOME is form 2316 item 19; GROSS_COMP_INCOME and NET_TAXABLE_COMP_INCOME are item 23 (the module checks them against previous plus present taxable compensation).',
        'PRES_TAXABLES_SALARIES (Schedule 1) and PRES_TAXABLE_SALARIES (Schedule 2) are the taxable amounts outside basic salary and 13th month (items 50 and 51; Schedule 2 also includes item 39).',
        'Withholding follows the validator\'s own formulas: ACTUAL_AMT_WTHLD equals TAX_DUE; AMT_WTHLD_DEC is only the year-end catch-up (2316 item 25B); PRES_TAX_WTHLD is the tax withheld before it and before any refund; OVER_WTHLD is the refund. An employee whose tax withheld differs from tax due is an error: run the year-end adjustment first.',
        'Names lose the characters the validator refuses (double quote, ampersand, apostrophe, comma, vertical bar).',
        'SUBS_FILING is N (no substituted filing), and there is no previous-employer data, so every PREV_ field is 0.00.',
        'REGION_NUM is the validator\'s text region code, taken from the employee\'s region (BARMM maps to ARMM, the only matching code on the validator\'s list) unless the profile sets one.',
        'A 12-digit TIN is split into the 9-digit TIN and a 4-digit branch code made of 0 and the last 3 digits; a 9-digit TIN gets branch 0000.',
    ];

    private const NUMBER_MAX = 99999999999.99;

    /** Split a TIN into the 9-digit TIN and 4-digit branch code. Null when it is not 9 or 12 digits. */
    public static function splitTin(mixed $raw, string $defaultBranch = '0000'): ?array
    {
        $d = preg_replace('/\D/', '', $raw === null ? '' : self::str($raw));
        if (strlen($d) === 9) {
            return ['tin' => $d, 'branch' => $defaultBranch];
        }
        if (strlen($d) === 12) {
            return ['tin' => substr($d, 0, 9), 'branch' => '0' . substr($d, 9)];
        }
        return null;
    }

    /** The region code for a profile: an explicit one, else the code for its region name. */
    public static function regionCodeFor(array $profile, ?string $fallback = null): ?string
    {
        return $profile['regionNumber'] ?? Spec::REGION_NAME_TO_CODE[$profile['region'] ?? ''] ?? $fallback;
    }

    /** A value as text, numbers in their shortest form. */
    public static function str(mixed $v): string
    {
        return match (true) {
            $v === null => '',
            is_bool($v) => $v ? 'true' : 'false',
            is_int($v), is_float($v) => Money::str($v),
            default => (string) $v,
        };
    }

    private static function truthy(mixed $v): bool
    {
        return $v !== null && $v !== '' && $v !== false && $v !== 0 && $v !== 0.0;
    }

    private static function mmddyyyy(string $iso): string
    {
        return substr($iso, 5, 2) . '/' . substr($iso, 8, 2) . '/' . substr($iso, 0, 4);
    }

    private static function clean(mixed $v): string
    {
        $s = mb_strtoupper($v === null ? '' : self::str($v));
        $s = preg_replace('/["&\',|]/', ' ', $s);
        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    private static function cents(mixed $n): float
    {
        return Money::roundHalfUp((float) $n * 100);
    }

    /** The validation of one record against its field table. Returns the list of problems. */
    public static function validateRecord(array $fields, array $rec, string $label): array
    {
        $problems = [];
        foreach ($fields as [$name, $type, $width]) {
            $v = $rec[$name] ?? null;
            $where = "{$label} {$name}";
            if ($v === null || $v === '') {
                if ($type === 'N' || $type === 'I') {
                    $problems[] = "{$where}: missing number";
                } elseif ($name !== 'MIDDLE_NAME') {
                    $problems[] = "{$where}: missing";
                }
                continue;
            }
            if ($type === 'T') {
                $s = self::str($v);
                if (mb_strlen($s) > $width) {
                    $problems[] = "{$where}: \"{$s}\" is longer than {$width} characters";
                } elseif (!preg_match('/^[\x20-\x7E]*$/', $s)) {
                    $problems[] = "{$where}: \"{$s}\" has characters outside printable ASCII";
                } elseif (preg_match('/^(TIN|TIN_EMPYR|TIM_EMPYR)$/', $name) && !preg_match('/^\d{9}$/', $s)) {
                    $problems[] = "{$where}: \"{$s}\" must be exactly 9 digits";
                } elseif (str_starts_with($name, 'BRANCH_CODE') && !preg_match('/^\d{4}$/', $s)) {
                    $problems[] = "{$where}: \"{$s}\" must be exactly 4 digits";
                } elseif ($name === 'REGION_NUM' && !in_array($s, Spec::REGION_CODES, true)) {
                    $problems[] = "{$where}: \"{$s}\" is not one of the validator's region codes (" . implode(', ', array_slice(Spec::REGION_CODES, 0, 17)) . '...)';
                }
            } elseif ($type === 'D') {
                if (!is_string($v) || !preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v)) {
                    $problems[] = "{$where}: \"" . self::str($v) . '" is not MM/DD/YYYY';
                } elseif (!Spec::isRealDate($v)) {
                    $problems[] = "{$where}: \"{$v}\" is not a real date";
                }
            } elseif ($type === 'N') {
                if (!(is_int($v) || is_float($v)) || !is_finite($v) || $v < 0 || $v > self::NUMBER_MAX) {
                    $problems[] = "{$where}: " . self::str($v) . ' must be between 0 and 99,999,999,999.99';
                } elseif (Money::roundHalfUp($v * 100) / 100 != $v) {
                    $problems[] = "{$where}: " . self::str($v) . ' has more than two decimals';
                }
            } elseif ($type === 'I') {
                $isInt = is_int($v) || (is_float($v) && is_finite($v) && floor($v) == $v);
                if (!$isInt || $v < 1 || strlen(self::str($v)) > $width) {
                    $problems[] = "{$where}: " . self::str($v) . " is not a positive integer of at most {$width} digits";
                }
            }
        }
        return $problems;
    }

    /** Serialize one record. `$kind` is header, detail or control: it decides which text fields are written bare. */
    public static function serializeRecord(array $fields, array $rec, string $kind = 'detail'): string
    {
        $bare = Spec::BARE[$kind];
        $cells = [];
        foreach ($fields as [$name, $type, $width]) {
            $v = $rec[$name] ?? ($type === 'N' || $type === 'I' ? 0 : '');
            $cells[] = match (true) {
                $type === 'N' => str_pad(number_format((float) $v, 2, '.', ''), 14, '0', STR_PAD_LEFT),
                $type === 'I' => str_pad(self::str($v), $width, '0', STR_PAD_LEFT),
                in_array($name, $bare, true) => self::str($v),
                default => '"' . str_replace('"', '""', self::str($v)) . '"',
            };
        }
        return implode(',', $cells);
    }

    private static function controlOf(array $controlFields, array $detailFields, array $detailRows, array $lead): array
    {
        $out = $lead;
        foreach ($controlFields as [$name, $type]) {
            if ($type !== 'N') {
                continue;
            }
            $src = Spec::detailColumnFor($name, $detailFields);
            $a = 0;
            foreach ($detailRows as $r) {
                $a += $r[$src] ?? 0;
            }
            $out[$name] = Money::round2($a);
        }
        return $out;
    }

    /** Combined and net taxable compensation must equal previous plus present taxable compensation (checked by the module). */
    private static function crossFieldProblems(array $rec, string $label): array
    {
        $out = [];
        if (self::cents($rec['GROSS_COMP_INCOME']) !== self::cents($rec['PREV_TOTAL_TAXABLE']) + self::cents($rec['PRES_TOTAL_COMP'])) {
            $out[] = "{$label}: GROSS_COMP_INCOME must equal PREV_TOTAL_TAXABLE plus PRES_TOTAL_COMP";
        }
        if (self::cents($rec['NET_TAXABLE_COMP_INCOME']) !== self::cents($rec['GROSS_COMP_INCOME'])) {
            $out[] = "{$label}: NET_TAXABLE_COMP_INCOME must equal GROSS_COMP_INCOME";
        }
        return $out;
    }

    /**
     * @param array $p rows (annual summary rows with `ids`), profiles (names, dates, region, status), year,
     *                 employer (tin, branch), and optionally regionNumber: the default region code.
     */
    public static function build(array $p): array
    {
        $rows = $p['rows'];
        $profiles = $p['profiles'];
        $year = $p['year'];
        $employer = $p['employer'] ?? null;
        $regionNumber = $p['regionNumber'] ?? null;
        $problems = [];
        $error = function (string $message, ?string $who = null) use (&$problems) {
            $problems[] = ['severity' => 'error', 'message' => $message, 'employee' => $who];
        };
        $warn = function (string $message, ?string $who = null) use (&$problems) {
            $problems[] = ['severity' => 'warning', 'message' => $message, 'employee' => $who];
        };

        $employerBranch = $employer['branch'] ?? null;
        $et = self::splitTin($employer['tin'] ?? null, $employerBranch ?? '0000');
        if (!$et) {
            $error('The employer TIN must have 9 or 12 digits');
        }
        $validBranch = self::truthy($employerBranch) && preg_match('/^\d{4}$/', self::str($employerBranch));
        $branch = $et ? ($validBranch ? $employerBranch : $et['branch']) : '0000';
        if (self::truthy($employerBranch) && !$validBranch) {
            $error('The employer branch code must have 4 digits');
        }
        $period = "12/31/{$year}";
        $lead = fn (string $code, string $tinField) => ['SCHEDULE_NUM' => $code, 'FTYPE_CODE' => Spec::FORM_TYPE, $tinField => $et['tin'] ?? '', 'BRANCH_CODE_EMPLYR' => $branch, 'RETRN_PERIOD' => $period];

        $prev = [];
        foreach ([...Spec::schedule1Detail(), ...Spec::schedule2Detail()] as [$name, $type]) {
            if ($type === 'N' && str_starts_with($name, 'PREV_')) {
                $prev[$name] = 0;
            }
        }

        $s1 = [];
        $s2 = [];
        foreach ($rows as $r) {
            $prof = [];
            foreach ($profiles as $candidate) {
                if ($candidate['id'] === $r['employeeId']) {
                    $prof = $candidate;
                    break;
                }
            }
            $who = $r['name'];
            $f = YearEnd::annual2316Row($r);
            $it = $f['items'];
            $t = self::splitTin($r['ids']['tin'] ?? $prof['ids']['tin'] ?? null);
            if (!$t) {
                $error('Missing or invalid TIN (needs 9 or 12 digits)', $who);
            }
            if (!self::truthy($prof['lastName'] ?? null)) {
                $error('Missing last name (edit the employee record)', $who);
            }
            if (!self::truthy($prof['firstName'] ?? null)) {
                $error('Missing first name (edit the employee record)', $who);
            }
            $region = self::regionCodeFor($prof, $regionNumber);
            if (!self::truthy($region)) {
                $error('Missing region code (set the employee\'s region or a region code, or pass a default)', $who);
            } elseif (($prof['region'] ?? null) === 'BARMM' && !self::truthy($prof['regionNumber'] ?? null)) {
                $warn('BARMM has no code on the validator\'s list; ARMM was used: set the region code if the BIR says otherwise', $who);
            }

            $hireDate = $prof['hireDate'] ?? null;
            $hire = self::truthy($hireDate) && strcmp($hireDate, "{$year}-01-01") > 0 ? $hireDate : "{$year}-01-01";
            $separation = $r['separation'] ?? null;
            $end = $separation['date'] ?? "{$year}-12-31";
            $reason = $separation ? (($separation['cause'] ?? null) === 'retirement' ? 'R' : 'T') : 'NA';
            $base = [
                'TIN' => $t['tin'] ?? '', 'BRANCH_CODE' => $t['branch'] ?? '0000',
                'LAST_NAME' => self::clean($prof['lastName'] ?? null), 'FIRST_NAME' => self::clean($prof['firstName'] ?? null), 'MIDDLE_NAME' => self::clean($prof['middleName'] ?? null),
                'REGION_NUM' => $region ?? '',
                'EMPLOYMENT_FROM' => self::mmddyyyy($hire), 'EMPLOYMENT_TO' => self::mmddyyyy($end),
                'NATIONALITY' => self::clean($prof['nationality'] ?? 'FILIPINO'), 'EMPLOYMENT_STATUS' => $prof['employmentStatus'] ?? 'R', 'REASON_SEPARATION' => $reason, 'SUBS_FILING' => 'N',
                'TAX_CREDIT_PERA' => 0,
            ];
            // The validator wants ACTUAL = TAX_DUE, and the December column to be only the year-end catch-up (item 25B):
            // what is left of TAX_DUE after PRES_TAX_WTHLD (tax withheld before the catch-up and before any refund).
            $dec = Money::round2($r['taxYearEndAdjustment'] ?? 0);
            $gross = Money::round2($r['taxWithheld'] + $r['taxRefunded']);
            $wh = ['PREV_TAX_WTHLD' => 0, 'PRES_TAX_WTHLD' => Money::round2($gross - $dec), 'AMT_WTHLD_DEC' => $dec, 'OVER_WTHLD' => $r['taxRefunded'], 'ACTUAL_AMT_WTHLD' => $r['taxWithheld']];
            if (!$r['mwe'] && abs($r['taxWithheld'] - $it['24']) >= 1) {
                $error('Tax actually withheld (' . Money::fixed2($r['taxWithheld']) . ') differs from the tax due (' . Money::fixed2($it['24']) . '): run the year-end adjustment first, the validator requires them to match', $who);
            }

            if (!$r['mwe']) {
                $s1[] = [
                    ...$lead('D1', 'TIN_EMPYR'), ...$base, ...$prev,
                    'PRES_NONTAX_GROSS_COMP_INCOME' => $it['19'], 'NONTAX_BASIC_SAL' => $it['29'], 'PRES_NONTAX_13TH_MONTH' => $it['34'], 'PRES_NONTAX_DE_MINIMIS' => $it['35'],
                    'PRES_NONTAX_SSS_ETC' => $it['36'], 'PRES_NONTAX_SALARIES' => $it['37'], 'PRES_TOTAL_NONTAX_COMP_INCOME' => $it['38'],
                    'TAX_BASIC_SAL' => $it['39'], 'PRES_TAXABLE_13TH_MONTH' => $it['48'], 'PRES_TAXABLES_SALARIES' => Money::round2($it['50'] + $it['51']), 'PRES_TOTAL_COMP' => $it['52'],
                    'GROSS_COMP_INCOME' => $it['23'], 'NET_TAXABLE_COMP_INCOME' => $it['23'], 'TAX_DUE' => $it['24'], ...$wh,
                ];
            } else {
                $factor = $prof['dailyRateFactor'] ?? 261;
                $smw = Profile::minimumWage($prof['region'] ?? null, array_key_exists('sector', $prof) ? $prof['sector'] : 'non_agri');
                if (!$smw) {
                    $error('A minimum-wage earner needs a known region to state the statutory minimum wage', $who);
                }
                $day = $smw['dailyMin'] ?? 0;
                $s2[] = [
                    ...$lead('D2', 'TIN_EMPYR'), ...$base, ...$prev,
                    'PRES_NONTAX_GROSS_COMP_INCOME' => $it['19'], 'PRES_NONTAX_BASIC_SMW_DAY' => $day, 'PRES_NONTAX_BASIC_SMW_MONTH' => Money::round2(($day * $factor) / 12), 'PRES_NONTAX_BASIC_SMW_YEAR' => Money::round2($day * $factor),
                    'FACTOR_USED' => $factor,
                    'PRES_NONTAX_HOLIDAY_PAY' => $it['30'], 'PRES_NONTAX_OVERTIME_PAY' => $it['31'], 'PRES_NONTAX_NIGHT_DIFF' => $it['32'], 'PRES_NONTAX_HAZARD_PAY' => $it['33'],
                    'PRES_NONTAX_13TH_MONTH' => $it['34'], 'PRES_NONTAX_DE_MINIMIS' => $it['35'], 'PRES_NONTAX_SSS_ETC' => $it['36'], 'PRES_NONTAX_SALARIES' => $it['37'],
                    'PRES_TOTAL_NONTAX_COMP_INCOME' => $it['38'], 'PRES_TAXABLE_13TH_MONTH' => $it['48'], 'PRES_TAXABLE_SALARIES' => Money::round2($it['39'] + $it['50'] + $it['51']),
                    'PRES_TOTAL_COMP' => $it['52'], 'GROSS_COMP_INCOME' => $it['23'], 'NET_TAXABLE_COMP_INCOME' => $it['23'], 'TAX_DUE' => $it['24'], ...$wh,
                    'NONTAX_BASIC_SAL' => $it['29'],
                ];
            }
            if (!$f['balanced']) {
                $warn('Form 2316 items do not balance with the payroll gross compensation', $who);
            }
        }
        $byName = fn ($a, $b) => Collate::compare("{$a['LAST_NAME']} {$a['FIRST_NAME']}", "{$b['LAST_NAME']} {$b['FIRST_NAME']}");
        usort($s1, $byName);
        usort($s2, $byName);
        // One running sequence number across both schedules, as in the files the official module accepted.
        $seq = 0;
        foreach ($s1 as &$x) {
            $x['SEQ_NUM'] = ++$seq;
        }
        unset($x);
        foreach ($s2 as &$x) {
            $x['SEQ_NUM'] = ++$seq;
        }
        unset($x);

        $c1 = $s1 ? self::controlOf(Spec::schedule1Control(), Spec::schedule1Detail(), $s1, $lead('C1', 'TIM_EMPYR')) : null;
        $c2 = $s2 ? self::controlOf(Spec::schedule2Control(), Spec::schedule2Detail(), $s2, $lead('C2', 'TIM_EMPYR')) : null;
        $header = ['FTYPE_CODE' => Spec::HEADER_CODE, 'TIN' => $et['tin'] ?? '', 'BRANCH_CODE' => $branch, 'RETRN_PERIOD' => $period];

        foreach (self::validateRecord(Spec::header(), $header, 'header') as $m) {
            $error($m);
        }
        foreach ([[$s1, Spec::schedule1Detail(), 'Schedule 1'], [$s2, Spec::schedule2Detail(), 'Schedule 2']] as [$list, $fields, $key]) {
            foreach ($list as $x) {
                foreach ([...self::validateRecord($fields, $x, "{$key} {$x['LAST_NAME']}"), ...self::crossFieldProblems($x, "{$key} {$x['LAST_NAME']}")] as $m) {
                    $error($m, $x['LAST_NAME']);
                }
            }
        }
        if ($c1) {
            foreach (self::validateRecord(Spec::schedule1Control(), $c1, 'Schedule 1 control') as $m) {
                $error($m);
            }
        }
        if ($c2) {
            foreach (self::validateRecord(Spec::schedule2Control(), $c2, 'Schedule 2 control') as $m) {
                $error($m);
            }
        }
        foreach ([[$s1, 'Schedule 1'], [$s2, 'Schedule 2']] as [$list, $key]) {
            foreach ($list as $x) {
                if (!array_key_exists(self::str($x['EMPLOYMENT_STATUS']), Spec::LOV['EMPLOYMENT_STATUS'])) {
                    $error("{$key} {$x['LAST_NAME']}: EMPLOYMENT_STATUS " . self::str($x['EMPLOYMENT_STATUS']) . ' is not in the list of values');
                }
                if (!array_key_exists($x['REASON_SEPARATION'], Spec::LOV['REASON_SEPARATION'])) {
                    $error("{$key} {$x['LAST_NAME']}: REASON_SEPARATION {$x['REASON_SEPARATION']} is not in the list of values");
                }
            }
        }
        $tins = [];
        foreach ([...$s1, ...$s2] as $x) {
            $key = "{$x['TIN']}-{$x['BRANCH_CODE']}";
            if ($x['TIN'] !== '' && isset($tins[$key])) {
                $error("Two employees share the TIN {$x['TIN']} (branch {$x['BRANCH_CODE']})", $x['LAST_NAME']);
            }
            $tins[$key] = true;
        }
        if (!$rows) {
            $error('There are no payslips for this year, so there is nothing to put in the alphalist');
        }

        // header, every detail (Schedule 1 then 2), then the controls
        $lines = [self::serializeRecord(Spec::header(), $header, 'header')];
        foreach ($s1 as $x) {
            $lines[] = self::serializeRecord(Spec::schedule1Detail(), $x, 'detail');
        }
        foreach ($s2 as $x) {
            $lines[] = self::serializeRecord(Spec::schedule2Detail(), $x, 'detail');
        }
        if ($c1) {
            $lines[] = self::serializeRecord(Spec::schedule1Control(), $c1, 'control');
        }
        if ($c2) {
            $lines[] = self::serializeRecord(Spec::schedule2Control(), $c2, 'control');
        }
        $content = implode(self::NEWLINE, $lines) . self::NEWLINE;

        // Defence in depth: re-read the bytes about to be written with the independent checker.
        $hasError = (bool) array_filter($problems, fn ($p) => $p['severity'] === 'error');
        $selfCheck = $rows && !$hasError ? Checker::check($content) : ['errors' => [], 'counts' => []];
        foreach ($selfCheck['errors'] as $e) {
            $error("Self-check of the written file, line {$e['line']}: {$e['message']}");
        }

        return [
            'fileName' => $et ? Spec::fileName($et['tin'], $branch, "1231{$year}") : null,
            'content' => $content,
            'selfCheck' => $selfCheck,
            'counts' => ['schedule1' => count($s1), 'schedule2' => count($s2)],
            'header' => $header, 'schedule1' => $s1, 'schedule2' => $s2, 'control1' => $c1, 'control2' => $c2,
            'problems' => $problems,
            'errors' => array_values(array_filter($problems, fn ($p) => $p['severity'] === 'error')),
            'assumptions' => self::ASSUMPTIONS,
        ];
    }
}

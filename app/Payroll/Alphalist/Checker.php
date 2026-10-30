<?php

namespace App\Payroll\Alphalist;

use App\Payroll\Csv;
use App\Payroll\Money;

/**
 * An independent checker for a BIR 1604-C alphalist .DAT file, working from the file's bytes alone (nothing from the
 * builder), so it also checks files made by other tools. It applies the annex's structure and the rules the official
 * Alphalist 7.4 module enforces: the header's employer TIN on every row, valid schedule numbers, text region codes,
 * combined/net taxable compensation, the Schedule 1 formulas, and the control totals.
 *
 * It is NOT the BIR module, and passing it does not prove the module will accept the file.
 */
class Checker
{
    private const NAME_REFUSED = ['"', '&', "'", ',', '|'];

    private static function kinds(): array
    {
        return [
            'D1' => ['fields' => Spec::schedule1Detail(), 'detail' => true],
            'D2' => ['fields' => Spec::schedule2Detail(), 'detail' => true],
            'C1' => ['fields' => Spec::schedule1Control(), 'detail' => false, 'detailFields' => Spec::schedule1Detail()],
            'C2' => ['fields' => Spec::schedule2Control(), 'detail' => false, 'detailFields' => Spec::schedule2Detail()],
        ];
    }

    /** A cell read as a number the way the validator reads it: blank is 0, anything not numeric is NaN. */
    private static function num(mixed $v): float
    {
        $s = trim((string) $v);
        if ($s === '') {
            return 0.0;
        }
        return is_numeric($s) ? (float) $s : NAN;
    }

    private static function cents(mixed $v): float
    {
        return Money::roundHalfUp(self::num($v) * 100);
    }

    private static function trunc(float $x): float
    {
        return $x < 0 ? ceil($x) : floor($x);
    }

    private static function quote(mixed $v): string
    {
        return $v === null ? 'undefined' : json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function fixed2(float $x): string
    {
        return is_nan($x) ? 'NaN' : Money::fixed2($x);
    }

    /** The validator's val_1604c form compares whole pesos (VFP int() truncates) and states these formulas for Schedule 1 only. */
    private static function schedule1Formulas(array $r, callable $fail): void
    {
        $sum = function (string ...$names) use ($r) {
            $a = 0.0;
            foreach ($names as $n) {
                $a += self::num($r[$n]);
            }
            return $a;
        };
        $eq = function (string $name, float $expect, string $msg) use ($r, $fail) {
            if (self::trunc(self::num($r[$name])) !== self::trunc($expect)) {
                $fail($msg);
            }
        };
        $eq('PREV_NONTAX_GROSS_COMP_INCOME', $sum('PREV_TOTAL_NONTAX_COMP_INCOME', 'PREV_TOTAL_TAXABLE'), 'PREV_NONTAX_GROSS_COMP_INCOME must equal PREV_TOTAL_NONTAX_COMP_INCOME plus PREV_TOTAL_TAXABLE');
        $eq('PREV_TOTAL_NONTAX_COMP_INCOME', $sum('PREV_NONTAX_BASIC_SMW', 'PREV_NONTAX_13TH_MONTH', 'PREV_NONTAX_DE_MINIMIS', 'PREV_NONTAX_SSS_ETC', 'PREV_NONTAX_SALARIES'), 'PREV_TOTAL_NONTAX_COMP_INCOME must equal its five components');
        $eq('PREV_TOTAL_TAXABLE', $sum('PREV_TAXABLE_BASIC_SALARY', 'PREV_TAXABLE_13TH_MONTH', 'PREV_TAXABLE_SALARIES'), 'PREV_TOTAL_TAXABLE must equal its three components');
        $eq('PRES_NONTAX_GROSS_COMP_INCOME', $sum('PRES_TOTAL_NONTAX_COMP_INCOME', 'PRES_TOTAL_COMP'), 'PRES_NONTAX_GROSS_COMP_INCOME must equal PRES_TOTAL_NONTAX_COMP_INCOME plus PRES_TOTAL_COMP');
        $eq('PRES_TOTAL_NONTAX_COMP_INCOME', $sum('NONTAX_BASIC_SAL', 'PRES_NONTAX_13TH_MONTH', 'PRES_NONTAX_DE_MINIMIS', 'PRES_NONTAX_SSS_ETC', 'PRES_NONTAX_SALARIES'), 'PRES_TOTAL_NONTAX_COMP_INCOME must equal its five components');
        $eq('PRES_TOTAL_COMP', $sum('TAX_BASIC_SAL', 'PRES_TAXABLE_13TH_MONTH', 'PRES_TAXABLES_SALARIES'), 'PRES_TOTAL_COMP must equal its three components');
        if (self::num($r['PREV_NONTAX_13TH_MONTH']) > 90000) {
            $fail('PREV_NONTAX_13TH_MONTH must not be greater than 90000');
        }
        $paid = $sum('PREV_TAX_WTHLD', 'PRES_TAX_WTHLD', 'TAX_CREDIT_PERA');
        $due = self::num($r['TAX_DUE']);
        $eq('ACTUAL_AMT_WTHLD', $due, 'ACTUAL_AMT_WTHLD must equal TAX_DUE');
        if (self::trunc(self::num($r['AMT_WTHLD_DEC'])) !== 0.0) {
            $eq('AMT_WTHLD_DEC', $due - $paid, 'AMT_WTHLD_DEC must equal TAX_DUE less the tax already withheld and the PERA credit');
        }
        if ($paid - $due >= 0) {
            $eq('OVER_WTHLD', $paid - $due, 'OVER_WTHLD must equal the tax withheld less TAX_DUE');
        }
        $actual = self::trunc(self::num($r['ACTUAL_AMT_WTHLD']));
        if ($actual !== self::trunc($paid - self::num($r['OVER_WTHLD'])) && $actual !== self::trunc($paid + self::num($r['AMT_WTHLD_DEC']))) {
            $fail('ACTUAL_AMT_WTHLD must equal the tax withheld less OVER_WTHLD, or plus AMT_WTHLD_DEC');
        }
    }

    /** @return array{errors: list<array{line:int,message:string}>, counts: array{D1:int,D2:int}} */
    public static function check(mixed $content): array
    {
        $errors = [];
        $fail = function (int $line, string $message) use (&$errors) {
            $errors[] = ['line' => $line, 'message' => $message];
        };
        $counts = ['D1' => 0, 'D2' => 0];

        if (!is_string($content) || $content === '') {
            $fail(0, 'The file is empty');
            return ['errors' => $errors, 'counts' => $counts];
        }
        if (!preg_match('/^[\x20-\x7E\r\n]*$/', $content)) {
            $fail(0, 'The file has characters outside printable ASCII');
        }
        if (!str_ends_with($content, "\r\n")) {
            $fail(0, 'The file must end with a CRLF');
        }
        if (preg_match('/(^|[^\r])\n/', $content)) {
            $fail(0, 'Every line must end with CRLF, not a bare LF');
        }
        $lines = explode("\r\n", preg_replace('/\r\n$/', '', $content));
        $rows = array_map(fn ($l) => Csv::parse($l)[0] ?? [], $lines);

        $head = $rows[0];
        if (count($head) !== count(Spec::header())) {
            $fail(1, 'The header has ' . count($head) . ' fields, expected ' . count(Spec::header()));
        }
        if (($head[0] ?? null) !== Spec::HEADER_CODE) {
            $fail(1, 'The header must start with H1604C');
        }
        if (!preg_match('/^\d{9}$/', $head[1] ?? '') || in_array($head[1] ?? null, ['999999999', '111111111'], true)) {
            $fail(1, 'The header employer TIN must be exactly 9 digits and not 999999999 or 111111111');
        }
        if (!preg_match('/^\d{4}$/', $head[2] ?? '')) {
            $fail(1, 'The header branch code must be exactly 4 digits');
        }
        if (!Spec::isRealDate($head[3] ?? '')) {
            $fail(1, 'The header return period must be a real MM/DD/YYYY date');
        }
        $identity = [$head[1] ?? null, $head[2] ?? null, $head[3] ?? null];

        $kinds = self::kinds();
        $details = ['D1' => [], 'D2' => []];
        $controls = [];
        $seen = [];
        $controlsStarted = false;
        $seq = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $cells = $rows[$i];
            $n = $i + 1;
            $kind = $cells[0] ?? null;
            $spec = $kinds[$kind] ?? null;
            if (!$spec) {
                $fail($n, 'Schedule Number ' . self::quote($kind) . ' is invalid');
                continue;
            }
            if (count($cells) !== count($spec['fields'])) {
                $fail($n, "{$kind} has " . count($cells) . ' fields, expected ' . count($spec['fields']));
                continue;
            }
            if ($cells[1] !== Spec::FORM_TYPE) {
                $fail($n, 'Form type must be ' . Spec::FORM_TYPE);
            }
            if ($cells[2] !== $identity[0]) {
                $fail($n, 'TIN of the withholding agent is not equal to the header TIN');
            }
            if ($cells[3] !== $identity[1]) {
                $fail($n, 'Employer branch code is not equal to the header branch code');
            }
            if ($cells[4] !== $identity[2]) {
                $fail($n, 'Return period is not equal to the header return period');
            }

            $rec = [];
            foreach ($spec['fields'] as $k => [$name, $type, $width]) {
                $v = $cells[$k];
                $rec[$name] = $v;
                if ($type === 'N') {
                    if (!preg_match('/^\d{11}\.\d{2}$/', $v)) {
                        $fail($n, "{$name} must be 14 characters, 11 digits, a point and 2 decimals (got " . self::quote($v) . ')');
                    }
                } elseif ($type === 'I') {
                    if (!preg_match("/^\\d{{$width}}$/", $v) || self::num($v) < 1) {
                        $fail($n, "{$name} must be {$width} digits and at least 1 (got " . self::quote($v) . ')');
                    }
                } elseif ($type === 'D') {
                    if (!Spec::isRealDate($v)) {
                        $fail($n, "{$name} must be a real MM/DD/YYYY date (got " . self::quote($v) . ')');
                    }
                } elseif (mb_strlen($v) > $width) {
                    $fail($n, "{$name} is longer than {$width} characters");
                }
            }

            if ($spec['detail']) {
                if ($controlsStarted) {
                    $fail($n, 'A detail line appears after a control line');
                }
                $counts[$kind]++;
                $details[$kind][] = ['rec' => $rec, 'n' => $n];
                if ($rec['SCHEDULE_NUM'] !== $kind) {
                    $fail($n, 'SCHEDULE_NUM does not match the line');
                }
                $want = str_pad((string) ++$seq, 6, '0', STR_PAD_LEFT);
                if ($rec['SEQ_NUM'] !== $want) {
                    $fail($n, "SEQ_NUM must run 000001, 000002, ... across the file (expected {$want}, got {$rec['SEQ_NUM']})");
                }
                if (!preg_match('/^\d{9}$/', $rec['TIN'])) {
                    $fail($n, 'Employee TIN must be exactly 9 digits');
                }
                if (!preg_match('/^\d{4}$/', $rec['BRANCH_CODE'])) {
                    $fail($n, 'Employee branch code must be exactly 4 digits');
                }
                $key = "{$rec['TIN']}-{$rec['BRANCH_CODE']}";
                if (isset($seen[$key])) {
                    $fail($n, "Duplicate employee TIN {$rec['TIN']}");
                }
                $seen[$key] = true;
                foreach (['LAST_NAME', 'FIRST_NAME', 'NATIONALITY'] as $f) {
                    if (trim($rec[$f]) === '') {
                        $fail($n, "{$f} must not be blank");
                    }
                }
                if (!in_array($rec['REGION_NUM'], Spec::REGION_CODES, true)) {
                    $fail($n, 'REGION_NUM ' . self::quote($rec['REGION_NUM']) . ' is not one of the validator\'s region codes');
                }
                foreach (['EMPLOYMENT_STATUS', 'REASON_SEPARATION', 'SUBS_FILING'] as $f) {
                    if (!array_key_exists($rec[$f], Spec::LOV[$f])) {
                        $fail($n, "{$f} {$rec[$f]} is not in the list of values");
                    }
                }
                if (self::cents($rec['GROSS_COMP_INCOME']) !== self::cents($rec['PREV_TOTAL_TAXABLE']) + self::cents($rec['PRES_TOTAL_COMP'])) {
                    $fail($n, 'GROSS_COMP_INCOME must equal PREV_TOTAL_TAXABLE plus PRES_TOTAL_COMP');
                }
                if (self::cents($rec['NET_TAXABLE_COMP_INCOME']) !== self::cents($rec['GROSS_COMP_INCOME'])) {
                    $fail($n, 'NET_TAXABLE_COMP_INCOME must equal GROSS_COMP_INCOME');
                }
                if ($kind === 'D1') {
                    self::schedule1Formulas($rec, fn ($m) => $fail($n, $m));
                }
                foreach (['LAST_NAME', 'FIRST_NAME', 'MIDDLE_NAME'] as $f) {
                    $bad = array_values(array_filter(mb_str_split($rec[$f] ?? ''), fn ($ch) => in_array($ch, self::NAME_REFUSED, true)));
                    if ($bad) {
                        $fail($n, "{$f} has characters the validator refuses (" . implode(' ', $bad) . ')');
                    }
                }
            } else {
                $controlsStarted = true;
                if (isset($controls[$kind])) {
                    $fail($n, "More than one {$kind} control line");
                }
                $controls[$kind] = ['rec' => $rec, 'n' => $n, 'spec' => $spec];
                if ($rec['SCHEDULE_NUM'] !== $kind) {
                    $fail($n, 'SCHEDULE_NUM does not match the line');
                }
            }
        }

        // every populated schedule needs its control, and the control must equal the re-summed detail columns
        foreach ([['D1', 'C1'], ['D2', 'C2']] as [$dKind, $cKind]) {
            $ds = $details[$dKind];
            $c = $controls[$cKind] ?? null;
            if ($ds && !$c) {
                $fail(count($lines), "{$dKind} lines have no {$cKind} control line");
                continue;
            }
            if (!$ds && $c) {
                $fail($c['n'], "{$cKind} control line without any {$dKind} lines");
                continue;
            }
            if (!$c) {
                continue;
            }
            foreach ($c['spec']['fields'] as [$name, $type]) {
                if ($type !== 'N') {
                    continue;
                }
                $col = Spec::detailColumnFor($name, $c['spec']['detailFields']);
                $sum = 0.0;
                foreach ($ds as $d) {
                    $sum += self::cents($d['rec'][$col]);
                }
                if (self::cents($c['rec'][$name]) !== $sum) {
                    $fail($c['n'], "{$cKind} {$name} is {$c['rec'][$name]} but its {$dKind} lines add up to " . self::fixed2($sum / 100));
                }
            }
        }
        if (!$counts['D1'] && !$counts['D2']) {
            $fail(count($lines), 'The file has no detail lines');
        }
        return ['errors' => $errors, 'counts' => $counts];
    }
}

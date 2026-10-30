<?php

namespace Tests\Payroll;

use App\Payroll\Alphalist\Builder;
use App\Payroll\Alphalist\Checker;
use App\Payroll\Contributions;
use App\Payroll\Holidays;
use App\Payroll\Loans\LoanException;
use App\Payroll\Loans\LoanLedger;
use App\Payroll\Loans\LoanStore;
use App\Payroll\Payslip;
use App\Payroll\Premium;
use App\Payroll\Profile;
use App\Payroll\ProfileEditor;
use App\Payroll\RunService;
use App\Payroll\Tax;
use App\Payroll\Thirteenth;
use App\Payroll\YearEnd;
use Throwable;

/**
 * Replays the recorded cases in tests/Payroll/golden: each holds an input and the exact output the payroll
 * rules must give for it (amounts to the centavo, labels and notes word for word, error messages included).
 */
class GoldenCases
{
    public static function suites(): array
    {
        return [
            'contributions' => fn ($i) => [
                'sss' => Contributions::sss($i['m']),
                'philhealth' => Contributions::philhealth($i['m']),
                'pagibig' => Contributions::pagibig($i['m']),
                'monthly' => Contributions::monthly($i['m']),
            ],
            'tax' => fn ($i) => Tax::withholding($i['x'], $i['f']),
            'premium' => fn ($i) => Premium::forLog($i['log'], $i['hourly'], Holidays::calendar($i['year'])),
            'thirteenth' => fn ($i) => Thirteenth::compute($i['b'], $i['o']),
            'mwe' => fn ($i) => Profile::minimumWageStatus($i['employee']),
            'payslip' => fn ($i) => Payslip::compute([...$i, 'calendar' => Holidays::calendar((int) substr($i['period']['start'], 0, 4))]),
            'loans' => fn ($i) => self::loanScenario($i['ops']),
            'thirteenthRun' => fn ($i) => YearEnd::thirteenthMonthRun($i['profiles'], 2026, $i['earnedBasic'] ?: [], $i['otherBonuses'] ?: []),
            'finalPay' => fn ($i) => YearEnd::finalPay($i),
            'alphalist' => fn ($i) => array_diff_key(Builder::build($i), ['assumptions' => true]),
            'alphalistCheck' => fn ($i) => Checker::check($i),
            'profileEdit' => fn ($i) => ProfileEditor::update($i['profile'], $i['patch']),
            'profileCsv' => fn ($i) => ProfileEditor::fromCsv($i['csv'], $i['existing']),
            'run' => function ($i) {
                $ledger = self::memoryLedger();
                foreach ($i['loans'] as $loan) {
                    $ledger->add($loan);
                }
                return RunService::compute([...$i, 'ledger' => $i['loans'] ? $ledger : null]);
            },
            'yearSummary' => function ($i) {
                $rows = array_map(
                    fn ($r, $k) => $k % 2 ? [...$r, 'ids' => ['tin' => '123456789', 'sss' => '34-1', 'philhealth' => '', 'pagibig' => 'x,"y"']] : $r,
                    $s = YearEnd::annualSummary($i['slips'], $i['year'], $i['mwe']),
                    array_keys($s),
                );
                return [
                    'rows' => $rows,
                    'csv' => YearEnd::annualCsv($rows),
                    'r2316' => array_map(YearEnd::annual2316Row(...), $rows),
                    'csv2316' => YearEnd::annual2316Csv($rows),
                    'adj' => YearEnd::yearEndAdjustmentRun($rows, $i['year']),
                ];
            },
        ];
    }

    /**
     * The alphalist files the official BIR validation module accepted, rebuilt from their recorded inputs: each must
     * come out byte for byte the same. Returns the names of the files that differ.
     */
    public static function birAccepted(): array
    {
        $failed = [];
        foreach (glob(__DIR__ . '/golden/bir-accepted/*-input.json') as $input) {
            $name = basename($input, '-input.json');
            $built = Builder::build(json_decode(file_get_contents($input), true, flags: JSON_THROW_ON_ERROR));
            $accepted = __DIR__ . "/golden/bir-accepted/{$name}-{$built['fileName']}";
            if (!is_file($accepted) || $built['errors'] || file_get_contents($accepted) !== $built['content']) {
                $failed[] = $name;
            }
        }
        return $failed;
    }

    /** Replays ledger operations against an in-memory store; loan ids are L1, L2, ... in creation order. */
    private static function loanScenario(array $ops): array
    {
        $ledger = self::memoryLedger();
        $results = [];
        foreach ($ops as $op) {
            try {
                $results[] = match ($op['kind']) {
                    'add' => $ledger->add($op['fields']),
                    'inputsFor' => $ledger->inputsFor($op['employeeId'], $op['period']),
                    'post' => $ledger->post($op['result'], $op['runId']),
                    'remove' => $ledger->remove($op['id']),
                };
            } catch (LoanException $e) {
                $results[] = ['error' => $e->getMessage(), 'status' => $e->getCode()];
            }
        }
        return ['results' => $results, 'final' => $ledger->list()];
    }

    /** A ledger kept in memory whose loan ids are L1, L2, ... in creation order. */
    private static function memoryLedger(): LoanLedger
    {
        $store = new class implements LoanStore {
            private array $loans = [];

            public function read(): array
            {
                return $this->loans;
            }

            public function write(array $loans): void
            {
                $this->loans = $loans;
            }
        };
        $n = 0;
        return new LoanLedger($store, function () use (&$n) {
            return 'L' . ++$n;
        });
    }

    /** @return array{0:int,1:list<string>} number of cases run and a description of each mismatch */
    public static function run(string $suite): array
    {
        $fn = self::suites()[$suite];
        $cases = json_decode(file_get_contents(__DIR__ . "/golden/{$suite}.json"), true, flags: JSON_THROW_ON_ERROR);
        $failures = [];
        foreach ($cases as $n => $case) {
            try {
                $actual = $fn($case['input']);
            } catch (Throwable $e) {
                $actual = ['error' => $e->getMessage()];
            }
            $diff = self::diff($case['output'], $actual, '');
            if ($diff !== null) {
                $failures[] = "{$suite}#{$n}{$diff}";
            }
        }
        return [count($cases), $failures];
    }

    /** Non-null keys in the order a JavaScript object lists them: integer-like keys ascending, then the rest as inserted. */
    private static function jsKeyOrder(array $a): array
    {
        $keys = array_keys(array_filter($a, fn ($v) => $v !== null));
        $ints = array_filter($keys, 'is_int');
        sort($ints);
        return [...$ints, ...array_filter($keys, fn ($k) => !is_int($k))];
    }

    /** First difference between expected and actual, or null. A key absent from expected matches null. */
    public static function diff(mixed $expected, mixed $actual, string $path): ?string
    {
        if (is_array($expected) && is_array($actual)) {
            if (array_is_list($expected) !== array_is_list($actual) && $expected !== [] && $actual !== []) {
                return "{$path}: shape differs";
            }
            if (array_is_list($expected) && count($expected) !== count($actual)) {
                return "{$path}: " . count($actual) . ' items, expected ' . count($expected);
            }
            foreach ($expected + $actual as $k => $_) {
                $d = self::diff($expected[$k] ?? null, $actual[$k] ?? null, "{$path}.{$k}");
                if ($d !== null) {
                    return $d;
                }
            }
            if (!array_is_list($expected) && self::jsKeyOrder($actual) !== self::jsKeyOrder($expected)) {
                return "{$path}: key order " . implode(',', array_keys($actual)) . ' expected ' . implode(',', array_keys($expected));
            }
            return null;
        }
        if ((is_int($expected) || is_float($expected)) && (is_int($actual) || is_float($actual))) {
            return (float) $expected === (float) $actual ? null : "{$path}: " . json_encode($actual) . ' expected ' . json_encode($expected);
        }
        return $expected === $actual ? null : "{$path}: " . json_encode($actual) . ' expected ' . json_encode($expected);
    }
}

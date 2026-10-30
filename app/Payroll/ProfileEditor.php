<?php

namespace App\Payroll;

use App\Payroll\Alphalist\Spec;
use InvalidArgumentException;

/**
 * Validated edits to an employee's pay profile, shared by the API and the CSV import. A refused edit throws
 * InvalidArgumentException with code 400 and a message fit to show the user.
 */
class ProfileEditor
{
    private const SECTORS = ['non_agri', 'agri_small'];
    private const MAX_RATE = ['monthly' => 10_000_000, 'daily' => 500_000, 'hourly' => 50_000];

    /** Days paid in a year, DOLE's equivalent-monthly factor: 261 for a 5-day week, 313 for 6 days, 365 when rest days are paid. */
    public const FACTOR_RANGE = [200, 366];
    private const ID_RULES = ['sss' => [10], 'philhealth' => [12], 'pagibig' => [12], 'tin' => [9, 12]];

    private static function fail(string $message): InvalidArgumentException
    {
        return new InvalidArgumentException($message, 400);
    }

    /** True for a real calendar date written YYYY-MM-DD. */
    public static function isRealDate(mixed $v): bool
    {
        if (!is_string($v) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * Government ID numbers, digits only after stripping dashes and spaces: SSS 10 digits, PhilHealth 12,
     * Pag-IBIG 12, TIN 9 or 12. Empty values are dropped.
     */
    public static function validIds(array $ids): array
    {
        $out = [];
        foreach (self::ID_RULES as $field => $lens) {
            $v = preg_replace('/[\s-]/', '', Alphalist\Builder::str($ids[$field] ?? null));
            if ($v === '') {
                continue;
            }
            if (!preg_match('/^\d+$/', $v) || !in_array(strlen($v), $lens, true)) {
                throw self::fail(strtoupper($field) . ' number must be ' . implode(' or ', $lens) . ' digits');
            }
            $out[$field] = $v;
        }
        return $out;
    }

    /**
     * Where net pay is deposited: `bankName`, `accountNumber` (6 to 20 digits after stripping dashes and spaces) and
     * optionally `accountName` when the account is not in the employee's name. An empty account number clears it.
     */
    public static function validBank(array $bank): ?array
    {
        $number = preg_replace('/[\s-]/', '', Alphalist\Builder::str($bank['accountNumber'] ?? null));
        if ($number === '') {
            return null;
        }
        if (!preg_match('/^\d{6,20}$/', $number)) {
            throw self::fail('The bank account number must be 6 to 20 digits');
        }
        $name = trim(Alphalist\Builder::str($bank['bankName'] ?? null));
        if ($name === '' || mb_strlen($name) > 60) {
            throw self::fail('The bank name is required with an account number, at most 60 characters');
        }
        $holder = trim(Alphalist\Builder::str($bank['accountName'] ?? null));
        if (mb_strlen($holder) > 100) {
            throw self::fail('The account name must be at most 100 characters');
        }
        return array_filter(['bankName' => $name, 'accountNumber' => $number, 'accountName' => $holder], fn ($v) => $v !== '');
    }

    private static function parseMwe(mixed $v): bool|string
    {
        return match (true) {
            $v === 'auto' => 'auto',
            $v === true, $v === 'true', $v === 'yes' => true,
            $v === false, $v === 'false', $v === 'no', $v === '', $v === null => false,
            default => throw self::fail('Minimum-wage status must be yes, no or auto'),
        };
    }

    /**
     * Apply a patch to a profile and return the updated copy. Accepts `payBasis`, a rate (`rate`, or the field named
     * for the basis: monthlySalary / dailyRate / hourlyRate), `hireDate`, `birthDate`, `region`, `sector`,
     * `minimumWageEarner` (yes / no / auto), name parts, `nationality`, `regionNumber`, `employmentStatus`, `ids` and `bank`.
     * Empty strings clear optional fields.
     */
    public static function update(array $profile, array $patch): array
    {
        $next = $profile;
        $basis = $patch['payBasis'] ?? $profile['payBasis'] ?? 'monthly';
        if (!in_array($basis, Profile::PAY_BASES, true)) {
            throw self::fail('Pay basis must be one of ' . implode(', ', Profile::PAY_BASES));
        }

        $field = Profile::RATE_FIELD[$basis];
        $rate = Money::parse($patch['rate'] ?? $patch[$field] ?? $profile[$field] ?? null);
        if (!($rate > 0 && $rate < self::MAX_RATE[$basis])) {
            throw self::fail("{$field} must be a positive number below " . number_format(self::MAX_RATE[$basis]));
        }
        $rounded = Money::round2($rate);
        if ($rounded !== (isset($profile[$field]) ? (float) $profile[$field] : null) || $basis !== ($profile['payBasis'] ?? 'monthly')) {
            $next['demo'] = false; // a real figure was entered
        }
        foreach (Profile::RATE_FIELD as $f) {
            unset($next[$f]);
        }
        $next[$field] = $rounded;
        $next['payBasis'] = $basis;

        foreach (['hireDate', 'birthDate'] as $key) {
            if (!array_key_exists($key, $patch)) {
                continue;
            }
            if ($patch[$key] === '' || $patch[$key] === null) {
                unset($next[$key]);
            } elseif (self::isRealDate($patch[$key])) {
                $next[$key] = $patch[$key];
            } else {
                throw self::fail("{$key} must be a real date written YYYY-MM-DD");
            }
        }
        if (!empty($next['hireDate']) && !empty($next['birthDate']) && $next['birthDate'] >= $next['hireDate']) {
            throw self::fail('The birth date must be before the hire date');
        }

        if (array_key_exists('region', $patch)) {
            if ($patch['region'] === '' || $patch['region'] === null) {
                unset($next['region']);
            } elseif (in_array($patch['region'], Profile::regions(), true)) {
                $next['region'] = $patch['region'];
            } else {
                throw self::fail("Unknown region \"{$patch['region']}\". Known: " . implode(', ', Profile::regions()));
            }
        }
        if (array_key_exists('sector', $patch)) {
            if ($patch['sector'] === '' || $patch['sector'] === null) {
                unset($next['sector']);
            } elseif (in_array($patch['sector'], self::SECTORS, true)) {
                $next['sector'] = $patch['sector'];
            } else {
                throw self::fail('Sector must be one of ' . implode(', ', self::SECTORS));
            }
        }
        if (array_key_exists('minimumWageEarner', $patch)) {
            $next['minimumWageEarner'] = self::parseMwe($patch['minimumWageEarner']);
        }
        if (($next['minimumWageEarner'] ?? null) === 'auto' && empty($next['region'])) {
            throw self::fail('Automatic minimum-wage status needs a region');
        }

        // Name parts, nationality and region number go into the BIR alphalist, whose files are comma-delimited:
        // a comma or a double quote inside a value would corrupt a line, so they are refused here.
        foreach (['lastName', 'firstName', 'middleName', 'nationality'] as $key) {
            if (!array_key_exists($key, $patch)) {
                continue;
            }
            $v = trim(Alphalist\Builder::str($patch[$key]));
            if ($v === '') {
                unset($next[$key]);
            } elseif (mb_strlen($v) > 30) {
                throw self::fail("{$key} can be at most 30 characters");
            } elseif (preg_match('/[",]/', $v)) {
                throw self::fail("{$key} cannot contain commas or double quotes");
            } else {
                $next[$key] = $v;
            }
        }
        if (array_key_exists('regionNumber', $patch)) {
            $v = mb_strtoupper(trim(Alphalist\Builder::str($patch['regionNumber'])));
            if ($v === '') {
                unset($next['regionNumber']);
            } elseif (in_array($v, Spec::REGION_CODES, true)) {
                $next['regionNumber'] = $v;
            } else {
                throw self::fail('regionNumber must be a region code the BIR validator accepts (' . implode(', ', Spec::REGION_CODES) . '); it is not zero-padded');
            }
        }
        if (array_key_exists('employmentStatus', $patch)) {
            $v = mb_strtoupper(trim(Alphalist\Builder::str($patch['employmentStatus'])));
            if ($v === '') {
                unset($next['employmentStatus']);
            } elseif (array_key_exists($v, Spec::LOV['EMPLOYMENT_STATUS'])) {
                $next['employmentStatus'] = $v;
            } else {
                throw self::fail('employmentStatus must be one of ' . implode(', ', array_keys(Spec::LOV['EMPLOYMENT_STATUS'])));
            }
        }

        if (array_key_exists('dailyRateFactor', $patch)) {
            $f = $patch['dailyRateFactor'];
            if ($f === '' || $f === null) {
                unset($next['dailyRateFactor']);
            } else {
                $n = Money::parse($f);
                if (!(floor($n) == $n && $n >= self::FACTOR_RANGE[0] && $n <= self::FACTOR_RANGE[1])) {
                    throw self::fail('dailyRateFactor must be a whole number of days between ' . self::FACTOR_RANGE[0] . ' and ' . self::FACTOR_RANGE[1] . ' (261 for a 5-day week, 313 for 6 days, 365 when rest days are paid)');
                }
                if ((int) $n !== (int) ($profile['dailyRateFactor'] ?? Profile::DEFAULT_DAILY_RATE_FACTOR)) {
                    $next['demo'] = false;
                }
                $next['dailyRateFactor'] = (int) $n;
            }
        }

        if (isset($patch['ids'])) {
            $next['ids'] = self::validIds($patch['ids']);
        }
        if (array_key_exists('bank', $patch)) {
            $bank = is_array($patch['bank']) ? self::validBank($patch['bank']) : null;
            if ($bank) {
                $next['bank'] = $bank;
            } else {
                unset($next['bank']);
            }
        }
        return $next;
    }

    private const ALIAS = [
        'employee' => 'name', 'employeename' => 'name', 'rate' => 'rate', 'salary' => 'rate', 'monthlysalary' => 'rate', 'dailyrate' => 'rate', 'hourlyrate' => 'rate', 'paybasis' => 'payBasis', 'basis' => 'payBasis',
        'hiredate' => 'hireDate', 'birthdate' => 'birthDate', 'region' => 'region', 'sector' => 'sector', 'minimumwageearner' => 'minimumWageEarner', 'mwe' => 'minimumWageEarner', 'minwage' => 'minimumWageEarner', 'minimumwage' => 'minimumWageEarner',
        'lastname' => 'lastName', 'surname' => 'lastName', 'familyname' => 'lastName', 'firstname' => 'firstName', 'givenname' => 'firstName', 'middlename' => 'middleName',
        'regionnumber' => 'regionNumber', 'regionno' => 'regionNumber', 'employmentstatus' => 'employmentStatus', 'nationality' => 'nationality',
        'sss' => 'sss', 'sssno' => 'sss', 'philhealth' => 'philhealth', 'philhealthno' => 'philhealth', 'pagibig' => 'pagibig', 'pagibigno' => 'pagibig', 'tin' => 'tin', 'id' => 'id', 'name' => 'name',
    ];

    /**
     * Load real pay data from a spreadsheet saved as CSV onto existing profiles. Header names are matched ignoring
     * case, spaces and punctuation; `id` or `name` finds the employee. Rows that match nobody or fail validation are
     * reported and skipped.
     *
     * @return array{profiles: array, report: array{updated: list<string>, skipped: list<array{row:int,reason:string}>}}
     */
    public static function fromCsv(string $text, array $existing): array
    {
        $rows = Csv::parse($text);
        $header = array_shift($rows);
        if (!$header) {
            throw new InvalidArgumentException('The CSV is empty', 400);
        }
        $cols = array_map(fn ($h) => self::ALIAS[preg_replace('/[^a-z0-9]/', '', mb_strtolower($h))] ?? null, $header);
        if (!in_array('id', $cols, true) && !in_array('name', $cols, true)) {
            throw new InvalidArgumentException('The CSV needs an id or name column to find each employee', 400);
        }
        if (!in_array('rate', $cols, true)) {
            throw new InvalidArgumentException('The CSV needs a rate column (monthly salary, daily rate or hourly rate)', 400);
        }

        $profiles = array_values($existing);
        $report = ['updated' => [], 'skipped' => []];
        foreach ($rows as $i => $cells) {
            $rowNo = $i + 2;
            $rec = [];
            foreach ($cols as $k => $c) {
                if ($c !== null && array_key_exists($k, $cells)) {
                    $rec[$c] = trim($cells[$k]);
                }
            }
            $id = $rec['id'] ?? '';
            $name = $rec['name'] ?? '';
            $at = null;
            foreach ($profiles as $j => $p) {
                if (($id !== '' && $p['id'] === $id) || ($id === '' && $name !== '' && mb_strtolower($p['name']) === mb_strtolower($name))) {
                    $at = $j;
                    break;
                }
            }
            if ($at === null) {
                $report['skipped'][] = ['row' => $rowNo, 'reason' => 'No existing employee matches ' . ($id ?: ($name ?: 'this row'))];
                continue;
            }
            $patch = ['payBasis' => ($rec['payBasis'] ?? '') !== '' ? $rec['payBasis'] : null, 'rate' => $rec['rate'] ?? null];
            foreach (['hireDate', 'birthDate', 'region', 'sector', 'lastName', 'firstName', 'middleName', 'regionNumber', 'employmentStatus', 'nationality'] as $k) {
                if (array_key_exists($k, $rec)) {
                    $patch[$k] = $rec[$k];
                }
            }
            if (array_key_exists('minimumWageEarner', $rec)) {
                $patch['minimumWageEarner'] = mb_strtolower($rec['minimumWageEarner']);
            }
            if (array_intersect(['sss', 'philhealth', 'pagibig', 'tin'], array_keys($rec))) {
                $patch['ids'] = ['sss' => $rec['sss'] ?? null, 'philhealth' => $rec['philhealth'] ?? null, 'pagibig' => $rec['pagibig'] ?? null, 'tin' => $rec['tin'] ?? null];
            }
            try {
                $profiles[$at] = self::update($profiles[$at], $patch);
                $report['updated'][] = $profiles[$at]['name'];
            } catch (InvalidArgumentException $e) {
                $report['skipped'][] = ['row' => $rowNo, 'reason' => $e->getMessage()];
            }
        }
        return ['profiles' => $profiles, 'report' => $report];
    }
}

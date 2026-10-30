<?php

namespace App\Console\Commands;

use App\Models\Payroll\PayrollProfile;
use App\Models\Payroll\PayrollRun;
use App\Payroll\Holidays;
use App\Payroll\PayrollService;
use App\Payroll\Profile;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Fills a LOCAL database with a fictional company so the app can be tried end to end: 80 people in six departments,
 * with hires and resignations along the way. Payroll covers last year in full (semi-monthly runs, final pay for
 * leavers, 13th month and the year-end adjustment, all paid) and this year to date (the latest period a draft, the
 * one before it waiting for approval). HR data adds supervisors, leave history for both years, and the last three
 * months of time records and overtime, which those months' runs are computed from. Logins: admin (prepares payroll),
 * approver (approves it) and employee. Every name and number is invented. Only the demo employees' rows are replaced.
 */
class HrisDemo extends Command
{
    protected $signature = 'hris:demo {--password=demo1234 : Password for the demo logins}';

    protected $description = 'Load a fictional company into a local database for trying out hris (never in production)';

    /** Months of time records up to today, this month included. Runs in those months are computed from them. */
    private const ATTENDANCE_MONTHS = 3;

    /** Pay-profile fields that belong to the HR record, not the payroll engine. */
    private const HR_ONLY = ['id', 'name', 'department', 'jobTitle', 'gender', 'separationDate', 'separationCause'];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('hris:demo only runs outside production.');
            return self::FAILURE;
        }
        $this->call('migrate', ['--force' => true]);
        $fullSchema = Schema::hasTable('tblemployee');
        if (!$fullSchema) {
            // A database without the app's MySQL schema gets the login table only; the HR side needs the schema.
            Schema::create('tbluser', function (Blueprint $t) {
                $t->id();
                $t->string('username')->unique();
                $t->string('password');
                $t->unsignedInteger('EmployeeID')->nullable();
                $t->unsignedTinyInteger('RoleID');
                $t->string('status')->default('Active');
            });
        }

        $people = json_decode(file_get_contents(resource_path('payroll/demo/profiles.json')), true);
        $today = now('Asia/Manila')->format('Y-m-d');
        $attendanceFrom = date('Y-m-01', strtotime(substr($today, 0, 7) . '-01 -' . (self::ATTENDANCE_MONTHS - 1) . ' months'));

        if ($fullSchema) {
            $this->hrData($people, $attendanceFrom, $today);
        }
        if (Schema::hasTable('tbluserrole')) {
            DB::table('tbluserrole')->insertOrIgnore([
                ['id' => 1, 'name' => 'ADMIN', 'description' => 'User with administration rights'],
                ['id' => 2, 'name' => 'EMPLOYEE', 'description' => 'User with an employee account'],
            ]);
        }
        $password = Hash::make($this->option('password'));
        foreach ([['admin', 1, 101], ['approver', 1, 102], ['employee', 2, 105]] as [$username, $role, $employee]) {
            DB::table('tbluser')->updateOrInsert(['username' => $username], ['password' => $password, 'RoleID' => $role, 'EmployeeID' => $employee, 'status' => 'Active']);
        }
        $admin = DB::table('tbluser')->where('username', 'admin')->value('id');
        $approver = DB::table('tbluser')->where('username', 'approver')->value('id');

        DB::table('payroll_loan_payments')->delete();
        DB::table('payroll_loans')->delete();
        PayrollRun::query()->delete();
        PayrollProfile::query()->delete();
        foreach ($people as $p) {
            PayrollProfile::create(['employee_id' => $p['id'], 'name' => $p['name'], 'data' => array_diff_key($p, array_flip(self::HR_ONLY))]);
        }
        foreach ([['101', 'SSS salary loan', 24000, 1000], ['107', 'Cash advance', 3000, 1500], ['112', 'Pag-IBIG multi-purpose loan', 60000, 1500], ['140', 'Company loan', 50000, 1250]] as [$id, $name, $principal, $amortization]) {
            PayrollService::ledger()->add(['employeeId' => $id, 'name' => $name, 'principal' => $principal, 'amortization' => $amortization]);
        }

        $runs = $this->payroll($people, $today, $attendanceFrom, $fullSchema, $admin, $approver);

        $this->info(sprintf('Demo company loaded: %d people, %d payroll runs from January %d to date%s.', count($people), $runs, (int) substr($today, 0, 4) - 1, $fullSchema ? ', leave history and the last ' . self::ATTENDANCE_MONTHS . ' months of time records' : ''));
        $this->line('Sign in as admin (prepares payroll), approver (approves it) or employee, password ' . $this->option('password') . '. Every name and number is invented.');
        return self::SUCCESS;
    }

    /** Semi-monthly runs from January last year, final pay on each resignation, and last year's 13th month and year-end adjustment. */
    private function payroll(array $people, string $today, string $attendanceFrom, bool $fullSchema, ?int $admin, ?int $approver): int
    {
        $finish = fn (PayrollRun $run) => PayrollService::pay(PayrollService::approve(PayrollService::submit($run, $admin), $approver));
        $year = (int) substr($today, 0, 4);
        $thisMonth = (int) substr($today, 5, 2);
        $count = 0;

        foreach ([$year - 1, $year] as $y) {
            for ($m = 1; $m <= ($y < $year ? 12 : $thisMonth); $m++) {
                $current = $y === $year && $m === $thisMonth;
                foreach ([[1, 15], [16, (int) date('t', mktime(0, 0, 0, $m, 1, $y))]] as $i => [$from, $to]) {
                    $start = sprintf('%d-%02d-%02d', $y, $m, $from);
                    $end = sprintf('%d-%02d-%02d', $y, $m, $to);
                    // Paid for the whole period: hired by its first day and still employed on its last.
                    $active = array_column(array_filter($people, fn ($p) => $p['hireDate'] <= $start && ($p['separationDate'] ?? '9999') >= $end), 'id');
                    $run = PayrollService::createRun([
                        'periodStart' => $start, 'periodEnd' => $end, 'frequency' => 'SEMI_MONTHLY', 'employeeIds' => $active,
                        'defaults' => ['daysWorked' => 11, 'hoursWorked' => 48], 'useAttendance' => $fullSchema && $start >= $attendanceFrom,
                    ], $admin);
                    $count++;
                    if ($current) {
                        if ($i === 0) {
                            PayrollService::submit($run, $admin); // waits for the approver; the latest period stays a draft
                        }
                        continue;
                    }
                    $finish($run);
                }
                foreach ($people as $p) {
                    if (substr($p['separationDate'] ?? '', 0, 7) === sprintf('%d-%02d', $y, $m)) {
                        $finish(PayrollService::finalPay($p['id'], ['separationDate' => $p['separationDate'], 'cause' => $p['separationCause'], 'unusedSilDays' => 2], $admin));
                        $count++;
                    }
                }
            }
            if ($y < $year) {
                $finish(PayrollService::thirteenthRun($y, $this->earnedBasic($people, $y), [], $admin));
                $count++;
                try {
                    $finish(PayrollService::yearEndAdjustment($y, $admin));
                    $count++;
                } catch (InvalidArgumentException) {
                    // nobody's withholding needed adjusting
                }
            }
        }
        return $count;
    }

    /** Basic pay earned in a year for the 13th-month run, by whole months employed. Leavers got theirs in final pay. */
    private function earnedBasic(array $people, int $year): array
    {
        $earned = [];
        foreach ($people as $p) {
            $from = max($p['hireDate'], "{$year}-01-01");
            $gone = ($p['separationDate'] ?? '9999') <= "{$year}-12-31";
            $earned[$p['id']] = $gone || $from > "{$year}-12-31" ? 0 : round(Profile::monthlyEquivalent($p) * (13 - (int) substr($from, 5, 2)), 2);
        }
        return $earned;
    }

    /** Employees and their placement, supervisors, schedule, leave for both years, and recent time records and overtime. */
    private function hrData(array $people, string $attendanceFrom, string $today): void
    {
        mt_srand(20261030);
        $ids = array_map(fn ($p) => (int) $p['id'], $people);
        foreach (['tblattendancerecord', 'tblleave', 'tblovertime', 'tblemployeeworkshift', 'tblleaveentitlement', 'tblemployeesupervisor'] as $t) {
            DB::table($t)->whereIn($t === 'tblemployeesupervisor' ? 'SubordinateID' : 'EmployeeID', $ids)->delete();
        }
        $lookup = fn (string $table, string $name, array $extra = []) => DB::table($table)->where('name', $name)->value('id') ?? DB::table($table)->insertGetId(['name' => $name, ...$extra]);
        $statuses = ['R' => 'Regular', 'P' => 'Probationary', 'C' => 'Casual', 'CP' => 'Contractual'];
        $cities = ['NCR' => ['Quezon City', 'Metro Manila'], 'Region III' => ['San Fernando', 'Pampanga'], 'Region IV-A' => ['Calamba', 'Laguna'], 'Region VI' => ['Iloilo City', 'Iloilo'], 'Region VII' => ['Cebu City', 'Cebu'], 'Region XI' => ['Davao City', 'Davao del Sur']];

        foreach ($people as $p) {
            [$city, $province] = $cities[$p['region']];
            DB::table('tblemployee')->updateOrInsert(['id' => (int) $p['id']], [
                'firstname' => $p['firstName'], 'lastname' => $p['lastName'], 'middlename' => $p['middleName'] ?? '',
                'birth_date' => $p['birthDate'], 'joined_date' => $p['hireDate'], 'gender' => $p['gender'], 'city' => $city, 'province' => $province,
                'DepartmentID' => $lookup('tbldepartment', $p['department']),
                'JobTitleID' => $lookup('tbljobtitle', $p['jobTitle'], ['description' => $p['jobTitle']]),
                'EmploymentStatusID' => $lookup('tblemploymentstatus', $statuses[$p['employmentStatus']]),
                'email' => strtolower(preg_replace('/\W/', '', explode(' ', $p['firstName'])[0]) . '.' . preg_replace('/\W/', '', $p['lastName'])) . '@example.test',
                'termination_date' => $p['separationDate'] ?? null, 'status' => isset($p['separationDate']) ? 'INACTIVE' : 'ACTIVE',
            ]);
        }

        // The best-paid person still employed heads each department and reports to the general manager (101).
        $heads = [];
        foreach ($people as $p) {
            $head = $heads[$p['department']] ?? null;
            if (!isset($p['separationDate']) && (!$head || Profile::monthlyEquivalent($p) > Profile::monthlyEquivalent($head))) {
                $heads[$p['department']] = $p;
            }
        }
        $supervisor = [];
        foreach ($people as $p) {
            $head = $heads[$p['department']]['id'];
            $supervisor[$p['id']] = $p['id'] === $head ? '101' : $head;
            if ($p['id'] !== '101') {
                DB::table('tblemployeesupervisor')->insert(['SupervisorID' => (int) $supervisor[$p['id']], 'SubordinateID' => (int) $p['id']]);
            }
        }

        $shift = DB::table('tblworkshift')->where('name', 'Office 8-5')->value('id')
            ?? DB::table('tblworkshift')->insertGetId(['name' => 'Office 8-5', 'hours_per_day' => 8, 'start_time1' => '08:00:00', 'end_time1' => '12:00:00', 'start_time2' => '13:00:00', 'end_time2' => '17:00:00']);
        if (!DB::table('tblworkweek')->exists()) {
            DB::table('tblworkweek')->insert(['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0]);
        }
        DB::table('tblemployeeworkshift')->insert(array_map(fn ($id) => ['EmployeeID' => $id, 'WorkShiftID' => $shift], $ids));

        $year = (int) substr($today, 0, 4);
        $calendars = [$year - 1 => Holidays::calendar($year - 1), $year => Holidays::calendar($year), $year + 1 => Holidays::calendar($year + 1)];
        $isWorkday = fn (string $d) => (int) date('N', strtotime($d)) < 6 && Holidays::type($d, $calendars[(int) substr($d, 0, 4)]) === null;
        $nextWorkday = function (string $d) use ($isWorkday) {
            do {
                $d = date('Y-m-d', strtotime("{$d} +1 day"));
            } while (!$isWorkday($d));
            return $d;
        };
        $leaveDays = $this->leave($people, $supervisor, $year, $today, $isWorkday, $nextWorkday);

        // Time records for the last few months: occasional absences and late arrivals, overtime in operations and sales.
        $records = [];
        $overtime = [];
        for ($d = $attendanceFrom; $d <= $today; $d = date('Y-m-d', strtotime("{$d} +1 day"))) {
            if (!$isWorkday($d)) {
                continue;
            }
            $dayNo = (int) substr($d, 8, 2);
            foreach ($people as $k => $p) {
                $id = (int) $p['id'];
                if ($p['hireDate'] > $d || ($p['separationDate'] ?? '9999') < $d || isset($leaveDays[$id][$d]) || ($k + $dayNo) % 23 === 0) {
                    continue;
                }
                $late = ($k * 7 + $dayNo) % 11 === 0 ? sprintf('08:%02d', 5 + ($k % 4) * 10) : '08:00';
                $ot = in_array($p['department'], ['Operations', 'Sales'], true) && ($k + $dayNo) % 9 === 0 && $d < $today;
                $records[] = [
                    'EmployeeID' => $id, 'date' => $d,
                    'time_in_session1' => "{$d} {$late}:00", 'time_out_session1' => "{$d} 12:00:00",
                    'time_in_session2' => "{$d} 13:00:00", 'time_out_session2' => "{$d} 17:00:00",
                    'time_in_overtime' => $ot ? "{$d} 17:00:00" : null, 'time_out_overtime' => $ot ? "{$d} 19:00:00" : null,
                ];
                if ($ot) {
                    $overtime[] = ['EmployeeID' => $id, 'reason' => $p['department'] === 'Sales' ? 'Store inventory count' : 'Late truck arrivals', 'duration' => 2, 'status' => 'APPROVED', 'date_created' => "{$d} 09:00:00", 'requested_date' => $d, 'reviewed_date' => "{$d} 11:00:00"];
                }
            }
        }
        foreach (array_chunk($records, 500) as $chunk) {
            DB::table('tblattendancerecord')->insert($chunk);
        }
        foreach (array_chunk($overtime, 500) as $chunk) {
            DB::table('tblovertime')->insert($chunk);
        }
        foreach ([[115, 'Month-end delivery backlog', 3], [109, 'Stock transfer to the Cebu branch', 2]] as [$id, $reason, $hours]) {
            DB::table('tblovertime')->insert(['EmployeeID' => $id, 'reason' => $reason, 'duration' => $hours, 'status' => 'PENDING', 'date_created' => now(), 'requested_date' => $nextWorkday($today)]);
        }
    }

    /**
     * Leave entitlements and requests for last year and this year: mostly approved, some rejected, and a few pending
     * requests for the coming days. Returns the approved leave days per employee so time records can skip them.
     */
    private function leave(array $people, array $supervisor, int $year, string $today, callable $isWorkday, callable $nextWorkday): array
    {
        $types = [
            'vacation' => [DB::table('tblleavetype')->where('name', 'Vacation leave')->value('id') ?? DB::table('tblleavetype')->insertGetId(['name' => 'Vacation leave', 'status' => 'ACTIVE']), 7, ['Family event', 'Out-of-town trip', 'Personal errands', 'Wedding of a relative', 'Child\'s school program']],
            'sick' => [DB::table('tblleavetype')->where('name', 'Sick leave')->value('id') ?? DB::table('tblleavetype')->insertGetId(['name' => 'Sick leave', 'status' => 'ACTIVE']), 5, ['Flu', 'Medical check-up', 'Dental procedure', 'Fever']],
        ];
        $days = [];
        $rows = [];
        foreach ([$year - 1, $year] as $y) {
            foreach ($people as $p) {
                $id = (int) $p['id'];
                $from = max($p['hireDate'], "{$y}-01-01");
                $until = min($p['separationDate'] ?? '9999', "{$y}-12-31", date('Y-m-d', strtotime("{$today} -1 day")));
                if ($from > $until) {
                    continue;
                }
                $used = [];
                foreach ($types as $kind => [$type, $entitlement]) {
                    $used[$kind] = 0;
                }
                for ($n = mt_rand(0, 4); $n > 0; $n--) {
                    $kind = mt_rand(1, 10) <= 7 ? 'vacation' : 'sick';
                    [$type, $entitlement, $reasons] = $types[$kind];
                    $start = date('Y-m-d', mt_rand(strtotime($from), strtotime($until)));
                    if (!$isWorkday($start)) {
                        $start = $nextWorkday($start);
                    }
                    $end = $kind === 'vacation' && mt_rand(0, 1) ? $nextWorkday($start) : $start;
                    $length = $end === $start ? 1 : 2;
                    $approved = mt_rand(1, 100) <= 88;
                    if ($end > $until || isset($days[$id][$start]) || isset($days[$id][$end]) || ($approved && $used[$kind] + $length > $entitlement)) {
                        continue;
                    }
                    if ($approved) {
                        $used[$kind] += $length;
                        $days[$id][$start] = $days[$id][$end] = true;
                    }
                    $filed = date('Y-m-d H:i:s', strtotime("{$start} 09:00 -" . mt_rand(2, 14) . ' days'));
                    $rows[] = ['EmployeeID' => $id, 'LeaveTypeID' => $type, 'no_of_days' => $length, 'start_date' => $start, 'end_date' => $end, 'reason' => $reasons[array_rand($reasons)], 'date_created' => $filed, 'status' => $approved ? 'APPROVED' : 'REJECTED', 'reviewed_date' => date('Y-m-d H:i:s', strtotime("{$filed} +1 day")), 'ReviewerID' => (int) ($supervisor[$p['id']] ?? 101)];
                }
                foreach ($types as $kind => [$type, $entitlement]) {
                    DB::table('tblleaveentitlement')->insert(['EmployeeID' => $id, 'LeaveTypeID' => $type, 'entitlement' => $entitlement, 'days_used' => $used[$kind], 'valid_from' => "{$y}-01-01", 'valid_to' => "{$y}-12-31", 'date_created' => "{$y}-01-01 08:00:00"]);
                }
            }
        }
        // Requests waiting for a decision, including one from the employee login.
        $next = $nextWorkday($today);
        foreach ([[105, 'vacation', 2], [119, 'vacation', 1], [137, 'sick', 1], [158, 'vacation', 2]] as [$id, $kind, $length]) {
            [$type, , $reasons] = $types[$kind];
            $rows[] = ['EmployeeID' => $id, 'LeaveTypeID' => $type, 'no_of_days' => $length, 'start_date' => $next, 'end_date' => $length === 2 ? $nextWorkday($next) : $next, 'reason' => $reasons[0], 'date_created' => now(), 'status' => 'PENDING', 'reviewed_date' => null, 'ReviewerID' => null];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('tblleave')->insert($chunk);
        }
        return $days;
    }
}

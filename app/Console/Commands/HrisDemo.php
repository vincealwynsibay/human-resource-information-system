<?php

namespace App\Console\Commands;

use App\Models\Payroll\PayrollProfile;
use App\Models\Payroll\PayrollRun;
use App\Payroll\Holidays;
use App\Payroll\PayrollService;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Fills a LOCAL database with a fictional company so the app can be tried end to end: 24 employees with departments,
 * positions, a Monday-to-Friday shift and this month's time records (some late, absent, on leave or on approved
 * overtime); their pay profiles; a loan; and payroll runs in every status. Logins: admin (prepares payroll), approver
 * (approves it) and employee. Every name and number is invented. Only the demo employees' rows are replaced.
 */
class HrisDemo extends Command
{
    protected $signature = 'hris:demo {--password=demo1234 : Password for the demo logins} {--year= : Year to fill (default: this year)}';

    protected $description = 'Load a fictional company into a local database for trying out hris (never in production)';

    private const FIRST_ID = 101;

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

        $profiles = json_decode(file_get_contents(resource_path('payroll/demo/profiles.json')), true);
        $ids = array_map(fn ($p) => (int) $p['id'], $profiles);
        $year = (int) ($this->option('year') ?: now('Asia/Manila')->year);
        $today = now('Asia/Manila')->format('Y-m-d');
        $month = $year < (int) substr($today, 0, 4) ? 12 : (int) substr($today, 5, 2);

        if ($fullSchema) {
            $this->hrData($profiles, $ids, $year, $month, min($today, sprintf('%d-12-31', $year)));
        }
        if (Schema::hasTable('tbluserrole')) {
            DB::table('tbluserrole')->insertOrIgnore([
                ['id' => 1, 'name' => 'ADMIN', 'description' => 'User with administration rights'],
                ['id' => 2, 'name' => 'EMPLOYEE', 'description' => 'User with an employee account'],
            ]);
        }
        $password = Hash::make($this->option('password'));
        foreach ([['admin', 1, self::FIRST_ID], ['approver', 1, self::FIRST_ID + 1], ['employee', 2, self::FIRST_ID + 4]] as [$username, $role, $employee]) {
            DB::table('tbluser')->updateOrInsert(['username' => $username], ['password' => $password, 'RoleID' => $role, 'EmployeeID' => $employee, 'status' => 'Active']);
        }
        $admin = DB::table('tbluser')->where('username', 'admin')->value('id');
        $approver = DB::table('tbluser')->where('username', 'approver')->value('id');

        DB::table('payroll_loan_payments')->delete();
        DB::table('payroll_loans')->delete();
        PayrollRun::query()->delete();
        PayrollProfile::query()->delete();
        foreach ($profiles as $p) {
            $data = $p;
            unset($data['id'], $data['name']);
            PayrollProfile::create(['employee_id' => $p['id'], 'name' => $p['name'], 'data' => $data]);
        }
        PayrollService::ledger()->add(['employeeId' => '101', 'name' => 'SSS salary loan', 'principal' => 24000, 'amortization' => 2000]);
        PayrollService::ledger()->add(['employeeId' => '107', 'name' => 'Cash advance', 'principal' => 3000, 'amortization' => 1500]);

        // Semi-monthly runs from January. Months with time records use them; earlier months use standard days.
        for ($m = 1; $m <= $month; $m++) {
            foreach ([[1, 15], [16, (int) date('t', mktime(0, 0, 0, $m, 1, $year))]] as $i => [$from, $to]) {
                $run = PayrollService::createRun([
                    'periodStart' => sprintf('%d-%02d-%02d', $year, $m, $from), 'periodEnd' => sprintf('%d-%02d-%02d', $year, $m, $to),
                    'frequency' => 'SEMI_MONTHLY', 'defaults' => ['daysWorked' => 11, 'hoursWorked' => 48],
                    'useAttendance' => $fullSchema && $m === $month,
                ], $admin);
                if ($m === $month && $i === 1) {
                    continue; // the latest period stays a draft
                }
                PayrollService::submit($run, $admin);
                if ($m === $month) {
                    continue; // and the one before it waits for the approver
                }
                PayrollService::pay(PayrollService::approve($run, $approver));
            }
        }

        $this->info("Demo company loaded: 24 employees, {$year} payroll through " . date('F', mktime(0, 0, 0, $month, 1)) . ($fullSchema ? ', with this month\'s time records.' : '.'));
        $this->line('Sign in as admin (prepares payroll), approver (approves it) or employee, password ' . $this->option('password') . '. Every name and number is invented.');
        return self::SUCCESS;
    }

    /** Employees, placement, schedule, and this month's time records, leave and overtime for the demo company. */
    private function hrData(array $profiles, array $ids, int $year, int $month, string $until): void
    {
        foreach (['tblattendancerecord', 'tblleave', 'tblovertime', 'tblemployeeworkshift', 'tblleaveentitlement', 'tblemployeesupervisor'] as $t) {
            DB::table($t)->whereIn($t === 'tblemployeesupervisor' ? 'SubordinateID' : 'EmployeeID', $ids)->delete();
        }
        $dept = fn ($name) => DB::table('tbldepartment')->where('name', $name)->value('id') ?? DB::table('tbldepartment')->insertGetId(['name' => $name]);
        $title = fn ($name) => DB::table('tbljobtitle')->where('name', $name)->value('id') ?? DB::table('tbljobtitle')->insertGetId(['name' => $name, 'description' => $name]);
        $status = DB::table('tblemploymentstatus')->where('name', 'Regular')->value('id') ?? DB::table('tblemploymentstatus')->insertGetId(['name' => 'Regular']);
        $placements = [['Management', 'General Manager'], ['Finance', 'Accountant'], ['Sales', 'Sales Associate'], ['Operations', 'Warehouse Staff'], ['Operations', 'Driver'], ['Sales', 'Store Supervisor']];

        foreach ($profiles as $i => $p) {
            [$d, $t] = $i === 0 ? $placements[0] : $placements[1 + $i % 5];
            DB::table('tblemployee')->updateOrInsert(['id' => (int) $p['id']], [
                'firstname' => $p['firstName'], 'lastname' => $p['lastName'], 'middlename' => $p['middleName'] ?? '',
                'birth_date' => $p['birthDate'] ?? null, 'joined_date' => $p['hireDate'] ?? "{$year}-01-01",
                'DepartmentID' => $dept($d), 'JobTitleID' => $title($t), 'EmploymentStatusID' => $status,
                'email' => strtolower($p['firstName'][0] . preg_replace('/\W/', '', $p['lastName'])) . '@example.test',
            ]);
            if ($i > 0) {
                DB::table('tblemployeesupervisor')->insert(['SupervisorID' => $ids[0], 'SubordinateID' => (int) $p['id']]);
            }
        }

        $shift = DB::table('tblworkshift')->where('name', 'Office 8-5')->value('id')
            ?? DB::table('tblworkshift')->insertGetId(['name' => 'Office 8-5', 'hours_per_day' => 8, 'start_time1' => '08:00:00', 'end_time1' => '12:00:00', 'start_time2' => '13:00:00', 'end_time2' => '17:00:00']);
        DB::table('tblworkweek')->insert(['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0]);
        DB::table('tblemployeeworkshift')->insert(array_map(fn ($id) => ['EmployeeID' => $id, 'WorkShiftID' => $shift], $ids));

        $leaveType = DB::table('tblleavetype')->where('name', 'Vacation leave')->value('id') ?? DB::table('tblleavetype')->insertGetId(['name' => 'Vacation leave', 'status' => 'ACTIVE']);
        $sick = DB::table('tblleavetype')->where('name', 'Sick leave')->value('id') ?? DB::table('tblleavetype')->insertGetId(['name' => 'Sick leave', 'status' => 'ACTIVE']);
        foreach ($ids as $id) {
            foreach ([[$leaveType, 5], [$sick, 5]] as [$type, $days]) {
                DB::table('tblleaveentitlement')->insert(['EmployeeID' => $id, 'LeaveTypeID' => $type, 'entitlement' => $days, 'days_used' => 0, 'valid_from' => "{$year}-01-01", 'valid_to' => "{$year}-12-31", 'date_created' => now()]);
            }
        }

        // This month's time records. A few patterns so the screens and the attendance-based runs have something to show.
        $calendar = Holidays::calendar($year);
        $first = sprintf('%d-%02d-01', $year, $month);
        $records = [];
        for ($d = $first; $d <= $until; $d = date('Y-m-d', strtotime("{$d} +1 day"))) {
            if ((int) date('N', strtotime($d)) >= 6 || Holidays::type($d, $calendar) !== null) {
                continue;
            }
            foreach ($ids as $k => $id) {
                $dayNo = (int) substr($d, 8, 2);
                if (($k + $dayNo) % 23 === 0) {
                    continue; // an occasional absence
                }
                if ($k === 4 && in_array($dayNo, [8, 9], true)) {
                    continue; // the employee login's two days of approved leave (below)
                }
                $late = ($k * 7 + $dayNo) % 11 === 0 ? sprintf('08:%02d', 5 + ($k % 4) * 10) : '08:00';
                $ot = $k % 6 === 1 && $dayNo % 7 === 3;
                $records[] = [
                    'EmployeeID' => $id, 'date' => $d,
                    'time_in_session1' => "{$d} {$late}:00", 'time_out_session1' => "{$d} 12:00:00",
                    'time_in_session2' => "{$d} 13:00:00", 'time_out_session2' => "{$d} 17:00:00",
                    'time_in_overtime' => $ot ? "{$d} 17:00:00" : null, 'time_out_overtime' => $ot ? "{$d} 19:30:00" : null,
                ];
                if ($ot) {
                    DB::table('tblovertime')->insert(['EmployeeID' => $id, 'reason' => 'Inventory count', 'duration' => 2, 'status' => 'APPROVED', 'date_created' => now(), 'requested_date' => $d, 'reviewed_date' => now()]);
                }
            }
        }
        foreach (array_chunk($records, 200) as $chunk) {
            DB::table('tblattendancerecord')->insert($chunk);
        }
        $employee = $ids[4];
        DB::table('tblleave')->insert([
            ['EmployeeID' => $employee, 'LeaveTypeID' => $leaveType, 'no_of_days' => 2, 'start_date' => sprintf('%d-%02d-08', $year, $month), 'end_date' => sprintf('%d-%02d-09', $year, $month), 'reason' => 'Family event', 'date_created' => now(), 'status' => 'APPROVED', 'reviewed_date' => now(), 'ReviewerID' => $ids[0]],
            ['EmployeeID' => $ids[7], 'LeaveTypeID' => $sick, 'no_of_days' => 1, 'start_date' => $until, 'end_date' => $until, 'reason' => 'Check-up', 'date_created' => now(), 'status' => 'PENDING', 'reviewed_date' => null, 'ReviewerID' => null],
        ]);
        DB::table('tblleaveentitlement')->where(['EmployeeID' => $employee, 'LeaveTypeID' => $leaveType])->update(['days_used' => 2]);
        DB::table('tblovertime')->insert(['EmployeeID' => $ids[9], 'reason' => 'Month-end close', 'duration' => 3, 'status' => 'PENDING', 'date_created' => now(), 'requested_date' => $until]);
    }
}

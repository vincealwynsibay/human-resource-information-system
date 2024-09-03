<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

test('create a payroll on day 15', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    // set date to day 15
    $date = Carbon::create(now()->year, now()->month, 15);
    Carbon::setTestNow($date);
    // create employee
    $employeeId = DB::table("tblemployee")->insertGetId([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'middlename' => fake()->email,
        'joined_date' => now(),
        'sss_contribution' => 100,
        'philhealth_contribution' => 100,
        'pagibig_contribution' => 100,
    ]);

    DB::table("tblemployeeworkshift")->insert([
        'EmployeeID' => $employeeId,
        'WorkShiftID' => 1,
    ]);

    // assign basic salary to employee
    DB::table('tblbasicsalary')->insert([
        'EmployeeID' => $employeeId,
        'PayGradeID' => 1,
        'amount' => 1000,
        'change_reason' => 'Initial basic salary',
    ]);
    // create attendance from day 1 to 9
    for ($i = 1; $i <= 9; $i++) {
        DB::table('tblattendancerecord')->insert([
            'EmployeeID' => $employeeId,
            'date' => Carbon::create(now()->year, now()->month, $i),
            'time_in_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 8, 0, 0),
            'time_out_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 12, 0, 0),
            'time_in_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 13, 0, 0),
            'time_out_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 17, 0, 0),
        ]);
    }
    // absent on day 10 to day 12
    // create undertime attendance from day 13 
    DB::table('tblattendancerecord')->insert([
        'EmployeeID' => $employeeId,
        'date' => Carbon::create(now()->year, now()->month, 13),
        'time_in_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 8, 0, 0),
        'time_out_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 12, 0, 0),
        'time_in_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 13, 0, 0),
        'time_out_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 17, 0, 0),
    ]);

    // create overtime attendance from day 14 to day 15
    DB::table('tblattendancerecord')->insert([
        'EmployeeID' => $employeeId,
        'date' => Carbon::create(now()->year, now()->month, 14),
        'time_in_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 8, 0, 0),
        'time_out_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 12, 0, 0),
        'time_in_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 13, 0, 0),
        'time_out_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 17, 0, 0),
    ]);


    DB::table('tblovertime')->insert([
        'EmployeeID' => $employeeId,
        'requested_date' => Carbon::create(now()->year, now()->month, 15),
        'reason' => 'Overtime on day 31',
        'status' => 'APPROVED',
        'duration' => 1,
    ]);

    // create overtime attendance from day 14 to day 15
    DB::table('tblattendancerecord')->insert([
        'EmployeeID' => $employeeId,
        'date' => Carbon::create(now()->year, now()->month, 15),
        'time_in_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 8, 0, 0),
        'time_out_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 12, 0, 0),
        'time_in_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 13, 0, 0),
        'time_out_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 17, 0, 0),
        'time_in_overtime' => Date::now()->setDateTime(2024, now()->month, intval($i), 18, 0, 0),
        'time_out_overtime' => Date::now()->setDateTime(2024, now()->month, intval($i), 19, 0, 0),
    ]);

    // add payroll items bonus and deduction
    DB::table('tblpayrollitem')->insert([
        'EmployeeID' => $employeeId,
        'item_type' => 'BONUS',
        'amount' => 1000,
        'description' => 'Christmas bonus',
        'created_date' => Date::now()->setDate(now()->year, now()->month, 13),
    ]);

    $response = $this->post('/api/payroll/' . $employeeId, [
        'employeeId' => $employeeId,
    ]);

    $response->assertStatus(201);
});

test('create a payroll on day 31', function () {

    // set date to end of month
    $date = Carbon::create(2024, now()->month, 30);
    Carbon::setTestNow($date);
    // create employee

    $user = User::factory()->create();
    $this->actingAs($user);
    // set date to day 15
    // create employee
    $employeeId = DB::table("tblemployee")->insertGetId([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'middlename' => fake()->email,
        'joined_date' => now(),
        'sss_contribution' => 100,
        'philhealth_contribution' => 100,
        'pagibig_contribution' => 100,
    ]);

    // dd(DB::table('tblemployee')->get());

    DB::table("tblemployeeworkshift")->insert([
        'EmployeeID' => $employeeId,
        'WorkShiftID' => 1,
    ]);

    // assign basic salary to employee
    // hourly rate = 11.53846153846154
    DB::table('tblbasicsalary')->insert([
        'EmployeeID' => $employeeId,
        'PayGradeID' => 1,
        'amount' => 1000,
        'change_reason' => 'Initial basic salary',
    ]);

    // create attendance from day 16 to 23
    for ($i = 16; $i <= 23; $i++) {
        DB::table('tblattendancerecord')->insert([
            'EmployeeID' => $employeeId,
            'date' => Carbon::create(now()->year, now()->month, $i),
            'time_in_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 8, 0, 0),
            'time_out_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 12, 0, 0),
            'time_in_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 13, 0, 0),
            'time_out_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 17, 0, 0),
        ]);
    }

    // create absences from day 24 to day 26

    // create undertime attendance from day 27
    DB::table('tblattendancerecord')->insert([
        'EmployeeID' => $employeeId,
        'date' => Carbon::create(now()->year, now()->month, 27),
        'time_in_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 8, 0, 0),
        'time_out_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 12, 0, 0),
        'time_in_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 13, 0, 0),
        'time_out_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 15, 0, 0),
    ]);


    for ($i = 28; $i <= 29; $i++) {
        DB::table('tblattendancerecord')->insert([
            'EmployeeID' => $employeeId,
            'date' => Carbon::create(now()->year, now()->month, $i),
            'time_in_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 8, 0, 0),
            'time_out_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 12, 0, 0),
            'time_in_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 13, 0, 0),
            'time_out_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 17, 0, 0),
        ]);
    }

    DB::table('tblovertime')->insert([
        'EmployeeID' => $employeeId,
        'requested_date' => Carbon::create(now()->year, now()->month, 30),
        'reason' => 'Overtime on day 31',
        'status' => 'APPROVED',
        'duration' => 1,
    ]);

    // overtime rate =   * 1.5 = 17.31
    DB::table('tblattendancerecord')->insert([
        'EmployeeID' => $employeeId,
        'date' => Carbon::create(now()->year, now()->month, 30),
        'time_in_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 8, 0, 0),
        'time_out_session1' => Date::now()->setDateTime(2024, now()->month, intval($i), 12, 0, 0),
        'time_in_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 13, 0, 0),
        'time_out_session2' => Date::now()->setDateTime(2024, now()->month, intval($i), 18, 0, 0),
        'time_in_overtime' => Date::now()->setDateTime(2024, now()->month, intval($i), 18, 0, 0),
        'time_out_overtime' => Date::now()->setDateTime(2024, now()->month, intval($i), 19, 0, 0),
    ]);

    // add payroll items bonus and deduction
    DB::table('tblpayrollitem')->insert([
        'EmployeeID' => $employeeId,
        'item_type' => 'BONUS',
        'amount' => 1000,
        'description' => 'Christmas bonus',
        'created_date' => Date::now()->setDate(2024, now()->month, 25),
    ]);

    $response = $this->post('/api/payroll/' . $employeeId, [
        'employeeId' => $employeeId,
    ]);


    $response->assertStatus(201);
});

test('get employee payroll', function () {

    $user = User::factory()->create();
    $this->actingAs($user);

    $employeeId = DB::table('tblemployee')->insert([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'middlename' => fake()->email,
        'joined_date' => now(),
        'sss_contribution' => 100,
        'philhealth_contribution' => 100,
        'pagibig_contribution' => 100,
    ]);

    $payrollId = DB::table('tblpayroll')->insertGetId([
        'EmployeeID' => $employeeId,
        'pay_period_start' => Date::now()->startOfMonth(),
        'pay_period_end' => Date::now()->setDay(31),
        'gross_salary' => 1000,
        'deductions' => 100,
        'net_salary' => 900,
        'created_date' => Date::now()
    ]);

    $response = $this->get('/api/payroll/' . $payrollId);
    $response->assertStatus(200);
});

test('get all payroll', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/api/payroll');
    $response->assertStatus(200);
});

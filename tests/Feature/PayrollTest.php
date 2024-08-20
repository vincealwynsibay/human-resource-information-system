<?php

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

test('create a payroll on day 15', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    // set date to day 15
    $date = Carbon::create(null, null, 15);
    Carbon::setTestNow($date);
    // create employee
    $employeeId = DB::table("tblemployee")->insert([
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
    ]);
    // create attendance from day 1 to 9
    for ($i = 1; $i <= 9; $i++) {
        DB::table('tblattendancerecord')->insert([
            'EmployeeID' => $employeeId,
            'date' => Carbon::create(null, null, $i),
            'time_in_session1' => '08:00:00',
            'time_out_session1' => '12:00:00',
            'time_in_session2' => '13:00:00',
            'time_out_session2' => '17:00:00',
        ]);
    }
    // absent on day 10 to day 12
    // create undertime attendance from day 13 
    DB::table('tblattendancerecord')->insert([
        'EmployeeID' => $employeeId,
        'date' => Carbon::create(null, null, 13),
        'time_in_session1' => '08:00:00',
        'time_out_session1' => '12:00:00',
        'time_in_session2' => '13:00:00',
        'time_out_session2' => '16:00:00',
    ]);
    // create overtime attendance from day 14 to day 15
    for ($i = 14; $i <= 15; $i++) {
        DB::table('tblattendancerecord')->insert([
            'EmployeeID' => $employeeId,
            'date' => Carbon::create(null, null, $i),
            'time_in_session1' => '08:00:00',
            'time_out_session1' => '12:00:00',
            'time_in_session2' => '13:00:00',
            'time_out_session2' => '18:00:00',
        ]);
    }

    // add payroll items bonus and deduction
    DB::table('tblpayrollitem')->insert([
        'EmployeeID' => $employeeId,
        'item_type' => 'BONUS',
        'amount' => 1000,
        'description' => 'Christmas bonus',
        'created_date' => now(),
    ]);

    $response = $this->post('/api/payroll/' . $employeeId, [
        'employeeId' => $employeeId,
    ]);
    $response->assertStatus(201);


});

test('create a payroll on day 31', function () {

    // set date to end of month
    $date = Carbon::create(null, null, 31);
    Carbon::setTestNow($date);
    // create employee

    $user = User::factory()->create();
    $this->actingAs($user);
    // set date to day 15
    // create employee
    $employeeId = DB::table("tblemployee")->insert([
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
    ]);

    // create attendance from day 16 to 23
    for ($i = 16; $i <= 23; $i++) {
        DB::table('tblattendancerecord')->insert([
            'EmployeeID' => $employeeId,
            'date' => Carbon::create(null, null, $i),
            'time_in_session1' => '08:00:00',
            'time_out_session1' => '12:00:00',
            'time_in_session2' => '13:00:00',
            'time_out_session2' => '17:00:00',
        ]);
    }

    // create absences from day 24 to day 26
    // create undertime attendance from day 27 
    DB::table('tblattendancerecord')->insert([
        'EmployeeID' => $employeeId,
        'date' => Carbon::create(null, null, 27),
        'time_in_session1' => '08:00:00',
        'time_out_session1' => '12:00:00',
        'time_in_session2' => '13:00:00',
        'time_out_session2' => '16:00:00',
    ]);

    // create overtime attendance from day 28 to last day of month
    for ($i = 28; $i <= 31; $i++) {
        DB::table('tblattendancerecord')->insert([
            'EmployeeID' => $employeeId,
            'date' => Carbon::create(null, null, $i),
            'time_in_session1' => '08:00:00',
            'time_out_session1' => '12:00:00',
            'time_in_session2' => '13:00:00',
            'time_out_session2' => '18:00:00',
        ]);
    }

    // add payroll items bonus and deduction
    DB::table('tblpayrollitem')->insert([
        'EmployeeID' => $employeeId,
        'item_type' => 'BONUS',
        'amount' => 1000,
        'description' => 'Christmas bonus',
        'created_date' => now(),
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

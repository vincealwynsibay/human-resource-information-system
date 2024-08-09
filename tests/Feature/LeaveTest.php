<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('get all leave as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/leave');
    $response->assertStatus(200);
});

test('get all employee leave as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/leave/?employeeId=1');
    $response->assertStatus(200);
});

test('get all employee leave with from date as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/leave/?employeeId=1&fromDate=2021-01-01');
    $response->assertStatus(200);
});

test('get all employee leave with to date as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/leave/?employeeId=1&toDate=2021-01-01');
    $response->assertStatus(200);
});

test('get all employee leave between dates as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/leave/?employeeId=1&fromDate=2021-01-01&toDate=2021-01-01');
    $response->assertStatus(200);
});

test('get all employee leave with status as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/leave/?employeeId=1&status=APPROVED');
    $response->assertStatus(200);
});

test('get all employee leave with leave type as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/leave/?employeeId=1&leaveType=1');
    $response->assertStatus(200);
});

test('get all employee leave with all filters as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/leave/?employeeId=1&fromDate=2021-01-01&toDate=2021-01-01&status=APPROVED&leaveType=1');
    $response->assertStatus(200);
});

test('get own leave as employee', function () {
    $user = User::factory()->create([
        'RoleID' => 3
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/leave/me');
    $response->assertStatus(200);
});

test('apply for a leave as employee', function () {
    $user = User::factory()->create([
        'RoleID' => 3
    ]);
    $this->actingAs($user);

    // create a leave entitlement
    DB::table('tblleaveentitlement')->insert([
        'EmployeeID' => 1,
        'LeaveTypeID' => 1,
        'entitlement' => 10,
        'days_used' => 0,
        'valid_from' => '2021-01-01',
        'valid_to' => '2021-12-31',
        'date_created' => now(),
    ]);

    $response = $this->post('/api/leave', [
        'employeeId' => 1,
        'leaveType' => 1,
        'startDate' => '2021-01-01',
        'endDate' => '2021-01-01',
        'reason' => 'sick'
    ]);
    $response->assertStatus(200);
});


test('update leave as employee', function () {
    $user = User::factory()->create([
        'RoleID' => 3
    ]);
    $this->actingAs($user);

    $leaveId = DB::table('tblleave')->insertGetId([
        'EmployeeID' => 1,
        'LeaveTypeID' => 1,
        'start_date' => '2021-01-01',
        'end_date' => '2021-01-01',
        'reason' => 'sick',
        'status' => 'PENDING',
        'no_of_days' => 1,
    ]);

    $response = $this->put('/api/leave/' . $leaveId, [
        'startDate' => '2021-01-01',
        'endDate' => '2021-01-01',
        'reason' => 'sick'
    ]);
    $response->assertStatus(200);
});

test('delete leave as employee', function () {
    $user = User::factory()->create([
        'RoleID' => 3
    ]);
    $this->actingAs($user);

    $leaveId = DB::table('tblleave')->insertGetId([
        'EmployeeID' => 1,
        'LeaveTypeID' => 1,
        'start_date' => '2021-01-01',
        'end_date' => '2021-01-01',
        'reason' => 'sick',
        'status' => 'PENDING',
        'no_of_days' => 1,
    ]);

    $response = $this->delete('/api/leave/' . $leaveId);
    $response->assertStatus(200);
});
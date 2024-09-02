<?php


use App\Models\User;
use Carbon\Carbon;

// time in am  as employee
test('time in am as employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::create(2021, 1, 1, 8, 0, 0));

    $response = $this->post('/api/employee/attendance/timeIn');
    $response->assertStatus(200);
});

// time out am as employee
test('time out am as employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::create(2021, 1, 1, 12, 0, 0));

    $response = $this->post('/api/employee/attendance/timeOut');
    $response->assertStatus(200);
});

// time in pm as employee
test('time in pm as employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::create(2021, 1, 1, 13, 0, 0));

    $response = $this->post('/api/employee/attendance/timeIn');
    $response->assertStatus(200);
});

// time out pm as employee
test('time out pm as employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::create(2021, 1, 1, 17, 0, 0));

    $response = $this->post('/api/employee/attendance/timeOut');
    $response->assertStatus(200);
});

// late attendance am as employee
test('late attendance am as employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::create(2021, 1, 1, 8, 30, 0));

    $response = $this->post('/api/employee/attendance/timeIn');
    $response->assertStatus(200);

    Carbon::setTestNow(Carbon::create(2021, 1, 1, 12, 0, 0));

    $response = $this->post('/api/employee/attendance/timeOut');
    $response->assertStatus(200);
});

// undertime attendance am as employee
test('undertime attendance am as employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::create(2021, 1, 1, 8, 0, 0));

    $response = $this->post('/api/employee/attendance/timeIn');
    $response->assertStatus(200);

    Carbon::setTestNow(Carbon::create(2021, 1, 1, 11, 30, 0));

    $response = $this->post('/api/employee/attendance/timeOut');
    $response->assertStatus(200);
});

// late attendance pm as employee
test('late attendance pm as employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::create(2021, 1, 1, 13, 30, 0));

    $response = $this->post('/api/employee/attendance/timeIn');
    $response->assertStatus(200);

    Carbon::setTestNow(Carbon::create(2021, 1, 1, 17, 0, 0));

    $response = $this->post('/api/employee/attendance/timeOut');
    $response->assertStatus(200);
});

// undertime attendance pm as employee
test('undertime attendance pm as employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Carbon::setTestNow(Carbon::create(2021, 1, 1, 13, 0, 0));

    $response = $this->post('/api/employee/attendance/timeIn');
    $response->assertStatus(200);

    Carbon::setTestNow(Carbon::create(2021, 1, 1, 16, 30, 0));

    $response = $this->post('/api/employee/attendance/timeOut');
    $response->assertStatus(200);
});


// get all attendance records as admin
test('get all attendance records as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // with params as date (from and to)
    $response = $this->get('/api/admin/attendance?fromDate=2021-01-01&toDate=2021-12-31');
    $response->assertStatus(200);
});

// get employee all attendance records as admin
test('get employee all attendance records as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);


    $employee = User::factory()->create([
        'RoleID' => 2
    ]);

    $response = $this->get("/api/employee/{$employee->EmployeeID}/attendance?range=TODAY");
    $response->assertStatus(200);
});

// get employee attendance summary as admin
test('get employee attendance summary as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);


    $employee = User::factory()->create([
        'RoleID' => 2
    ]);

    $response = $this->get("/api/employee/{$employee->EmployeeID}/attendanceSummary?range=TODAY");
    $response->assertStatus(200);
});

// get attendance summary as admin by date range
test('get attendance summary by date range', function () {
    $user = User::factory()->create();
    $this->actingAs($user);


    $employee = User::factory()->create([
        'RoleID' => 2
    ]);

    $response = $this->get("/api/employee/{$employee->EmployeeID}/attendanceSummary?range=TODAY");
    $response->assertStatus(200);
});


// get own attendance summary as employee
test('get own attendance summary as employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get("/api/employee/{$user->EmployeeID}/attendanceSummary?range=TODAY");
    $response->assertStatus(200);
});

// update time in and time out as admin
// update time in and time out as employee

// delete attendance record as admin
// delete attendance record as employee
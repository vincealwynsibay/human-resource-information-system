<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('get all overtime as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/overtime');
    $response->assertStatus(200);
});

test('get all employee overtime as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/overtime/?employeeId=1');
    $response->assertStatus(200);
});

test('get all employee overtime with date as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/overtime/?employeeId=1&date=2021-01-01');
    $response->assertStatus(200);
});

test('get all employee overtime with status as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/overtime/?employeeId=1&status=APPROVED');
    $response->assertStatus(200);
});

test('get all employee overtime with all filters as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/overtime/?employeeId=1&date=2021-01-01&status=APPROVED');
    $response->assertStatus(200);
});


test('get own overtime as employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/overtime/me');
    $response->assertStatus(200);
});

test('get own overtime with date as employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/overtime/me?date=2021-01-01');
    $response->assertStatus(200);
});

test('get own overtime with status as employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/overtime/me?status=APPROVED');
    $response->assertStatus(200);
});

test('get own overtime with all filters as employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/overtime/me?date=2021-01-01&status=APPROVED');
    $response->assertStatus(200);
});

test('get subordinates overtime as supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/overtime/');
    $response->assertStatus(200);
});

test('apply for overtime', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->post('/api/overtime', [
        'employeeId' => 1,
        'duration' => 2,
        'requestedDate' => '2021-01-01',
        'reason' => 'Need to work extra hours',
    ]);

    $response->assertJson(['message' => 'Overtime request submitted successfully']);
    $response->assertStatus(200);
});

test('update overtime', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->put('/api/overtime/1', [
        'requestedDate' => '2021-01-01',
        'duration' => 2,
        'reason' => 'Need to work extra hours',
    ]);

    $response->assertJson(['message' => 'Overtime updated successfully']);
    $response->assertStatus(200);
});

test('delete overtime', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->delete('/api/overtime/1');
    $response->assertJson(['message' => 'Overtime deleted successfully']);
    $response->assertStatus(200);
});


test('approve overtime', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $overtimeId = DB::table('tblovertime')->insertGetId([
        'EmployeeID' => 1,
        'duration' => 2,
        'requested_date' => '2021-01-01',
        'reason' => 'Need to work extra hours',
        'status' => 'PENDING',
        'date_created' => now(),
    ]);

    $response = $this->post('/api/overtime/approve', [
        'overtimeId' => $overtimeId,
    ]);


    $response->assertStatus(200);
});

test('reject overtime', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $overtimeId = DB::table('tblovertime')->insertGetId([
        'EmployeeID' => 1,
        'duration' => 2,
        'requested_date' => '2021-01-01',
        'reason' => 'Need to work extra hours',
        'status' => 'PENDING',
        'date_created' => now(),
    ]);

    $response = $this->post('/api/overtime/reject', [
        'overtimeId' => $overtimeId,
    ]);

    $response->assertStatus(200);
});



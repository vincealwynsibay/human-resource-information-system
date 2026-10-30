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

function pendingOvertime(int $employeeId, string $status = 'PENDING'): int
{
    return DB::table('tblovertime')->insertGetId([
        'EmployeeID' => $employeeId, 'reason' => 'Month-end close', 'duration' => 2,
        'status' => $status, 'date_created' => now(), 'requested_date' => '2021-01-01',
    ]);
}

test('update overtime', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = pendingOvertime($user->EmployeeID);
    $response = $this->put('/api/overtime/' . $id, [
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
    $response = $this->delete('/api/overtime/' . pendingOvertime($user->EmployeeID));
    $response->assertJson(['message' => 'Overtime deleted successfully']);
    $response->assertStatus(200);
});

test('an approved overtime request cannot be changed or withdrawn', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = pendingOvertime($user->EmployeeID, 'APPROVED');
    $this->putJson('/api/overtime/' . $id, ['requestedDate' => '2021-01-02', 'duration' => 9, 'reason' => 'x'])->assertStatus(400);
    $this->deleteJson('/api/overtime/' . $id)->assertStatus(400);
    expect((float) DB::table('tblovertime')->where('id', $id)->value('duration'))->toEqual(2.0);
});

test('an employee cannot request overtime for someone else', function () {
    $user = User::factory()->create(['RoleID' => 2]);
    $this->actingAs($user);
    $this->postJson('/api/overtime', ['employeeId' => 2, 'reason' => 'x', 'duration' => 1, 'requestedDate' => '2021-01-01'])->assertStatus(403);
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



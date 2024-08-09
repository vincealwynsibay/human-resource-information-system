<?php

// Route::get('/', [LeaveEntitlementController::class, 'index']);

use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

test('get leave entitlements by employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);


    $employeeId = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $employee = DB::table('tblemployee')->where('id', $employeeId)->first();


    $response = $this->getJson('/api/leaveentitlement', [
        'name' => $employee->firstname . ' ' . $employee->lastname,
    ]);

    $response->assertStatus(200);
});
// Route::get('/me', [LeaveEntitlementController::class, 'getUserLeaveEntitlements']);
test('get leave entitlements by user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->getJson('/api/leaveentitlement/me');

    $response->assertStatus(200);
});

// Route::post('/', [LeaveEntitlementController::class, 'store']);
test('create a leave entitlement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $employeeId = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $response = $this->post('/api/leaveentitlement', [
        'employeeId' => $employeeId,
        'leaveTypeId' => 1,
        'entitlement' => 10,
        'validFrom' => Date::now(),
        'validTo' => Date::now()->addYear(),
    ]);

    $response->assertStatus(200);

});

test('update a leave entitlement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $employeeId = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $validFrom = Date::now();
    $validTo = Date::now()->addYear();
    $leaveEntitlementId = DB::table('tblleaveentitlement')->insertGetId([
        'EmployeeID' => $employeeId,
        'LeaveTypeID' => 1,
        'Entitlement' => 10,
        'valid_from' => $validFrom,
        'valid_to' => $validTo,
    ]);

    $response = $this->put('/api/leaveentitlement/' . $leaveEntitlementId, [
        'entitlement' => 20,
        'validFrom' => $validFrom,
        'validTo' => $validTo,

    ]);

    $response->assertStatus(200);
});

test('update leave entitlement days used', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $employeeId = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $leaveEntitlementId = DB::table('tblleaveentitlement')->insertGetId([
        'EmployeeID' => $employeeId,
        'LeaveTypeID' => 1,
        'Entitlement' => 10,
        'valid_from' => Date::now(),
        'valid_to' => Date::now()->addYear(),
    ]);

    $response = $this->put('/api/leaveentitlement/' . $leaveEntitlementId . '/daysUsed', [
        'daysUsed' => 5
    ]);

    $response->assertStatus(200);
});

test('delete a leave entitlement', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $employeeId = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $leaveEntitlementId = DB::table('tblleaveentitlement')->insertGetId([
        'EmployeeID' => $employeeId,
        'LeaveTypeID' => 1,
        'Entitlement' => 10,
        'valid_from' => Date::now(),
        'valid_to' => Date::now()->addYear(),
    ]);

    $response = $this->delete('/api/leaveentitlement/' . $leaveEntitlementId);

    $response->assertStatus(200);
});
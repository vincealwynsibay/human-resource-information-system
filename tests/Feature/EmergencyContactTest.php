<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('get all employee emergency contact', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);
    $response = $this->get("/api/employee/{$employee->EmployeeID}/emergencyContacts");
    $response->assertStatus(200);
});

test('add new emergency contact', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);
    $response = $this->post("/api/employee/{$employee->EmployeeID}/emergencyContacts", [
        'name' => 'John Doe',
        'relationship' => 'Father',
        'mobile_no' => '09123456789',
        'email' => fake()->safeEmail()
    ]);
    $response->assertStatus(201);
});

test('update emergency contact', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);

    $emergencyContactId = DB::table('tblemergencycontact')->insertGetId([
        'employeeId' => $employee->EmployeeID,
        'name' => 'John Doe',
        'relationship' => 'Father',
        'mobile_no' => '09123456789',
        'email' => fake()->safeEmail()
    ]);

    $response = $this->put("/api/employee/{$employee->EmployeeID}/emergencyContacts/{$emergencyContactId}", [
        'name' => 'Jane Doe',
        'relationship' => 'Mother',
        'mobile_no' => '09123456789',
        'email' => fake()->safeEmail()
    ]);
    $response->assertStatus(200);
});

test('delete emergency contact', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);

    $emergencyContactId = DB::table('tblemergencycontact')->insertGetId([
        'employeeId' => $employee->EmployeeID,
        'name' => 'John Doe',
        'relationship' => 'Father',
        'mobile_no' => '09123456789',
        'email' => fake()->safeEmail()
    ]);
    $response = $this->delete("/api/employee/{$employee->EmployeeID}/emergencyContacts/{$emergencyContactId}");
    $response->assertStatus(200);
});


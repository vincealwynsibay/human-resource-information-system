<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('get all employee certificates', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);
    $response = $this->get("/api/employee/{$employee->EmployeeID}/certificates");
    $response->assertStatus(200);
});

test('add new certificate', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);
    $response = $this->post("/api/employee/{$employee->EmployeeID}/certificates", [
        'name' => 'Certificate A',
        'issuedBy' => 'Company A',
        'issuedDate' => '2020-01-01'
    ]);
    $response->assertStatus(201);
});

test('update certificate', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);

    $certificateId = DB::table('tblemployeecertificate')->insertGetId([
        'employeeId' => $employee->EmployeeID,
        'name' => 'Certificate A',
        'issued_by' => 'Company A',
        'issued_date' => '2020-01-01'
    ]);

    $response = $this->put("/api/employee/{$employee->EmployeeID}/certificates/{$certificateId}", [
        'name' => 'Certificate B',
        'issuedBy' => 'Company A',
        'issuedDate' => '2020-01-01'
    ]);
    $response->assertStatus(200);
});

test('delete certificate', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);

    $certificateId = DB::table('tblemployeecertificate')->insertGetId([
        'employeeId' => $employee->EmployeeID,
        'name' => 'Certificate A',
        'issued_by' => 'Company A',
        'issued_date' => '2020-01-01'
    ]);
    $response = $this->delete("/api/employee/{$employee->EmployeeID}/certificates/{$certificateId}");
    $response->assertStatus(200);
});
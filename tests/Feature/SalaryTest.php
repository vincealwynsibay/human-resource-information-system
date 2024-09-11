<?php


use App\Models\User;
use Illuminate\Support\Facades\DB;

test('get salary', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);
    $response = $this->get("/api/employee/{$employee->EmployeeID}/salary");
    $response->assertStatus(200);
});

test('add salary', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $employee = User::factory()->create();

    $response = $this->post("/api/employee/{$employee->EmployeeID}/salary", [
        'paygradeId' => '1',
        'amount' => 16000,
        'change_reason' => 'Promotion'
    ]);

    $response->assertStatus(201);
});

test('update salary', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);

    $salaryId = DB::table('tblbasicsalary')->insertGetId([
        'employeeId' => $employee->EmployeeID,
        'PayGradeId' => 1,
        'amount' => 15000,
        'change_reason' => 'Initial Salary',
    ]);

    $response = $this->put("/api/employee/{$employee->EmployeeID}/salary/{$salaryId}", [
        'paygradeId' => '1',
        'amount' => '19000',
        'change_reason' => 'Promotion'
    ]);


    $response->assertStatus(200);
});

test('delete salary', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);

    $salaryId = DB::table('tblbasicsalary')->insertGetId([
        'employeeId' => $employee->EmployeeID,
        'PayGradeId' => 1,
        'amount' => 10000,
        'change_reason' => 'Initial Salary',
    ]);
    $response = $this->delete("/api/employee/{$employee->EmployeeID}/salary/{$salaryId}");
    $response->assertStatus(200);
});
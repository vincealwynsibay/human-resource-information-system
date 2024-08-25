<?php

use App\Models\User;

use Illuminate\Support\Facades\DB;

test('get all employee work experience', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);
    $response = $this->get("/api/employee/{$employee->EmployeeID}/workExperiences");
    $response->assertStatus(200);
});

test('add new work experience', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);
    $response = $this->post("/api/employee/{$employee->EmployeeID}/workExperiences", [
        'employer' => 'Company A',
        'position' => 'Software Engineer',
        'from' => '2020-01-01',
        'to' => '2021-01-01'
    ]);
    $response->assertStatus(201);
});


test('update work experience', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);

    $workExperienceId = DB::table('tblemployeeworkexperience')->insertGetId([
        'employeeId' => $employee->EmployeeID,
        'employer' => 'Company A',
        'position' => 'Software Engineer',
        'from_date' => '2020-01-01',
        'to_date' => '2021-01-01'
    ]);

    $response = $this->put("/api/employee/{$employee->EmployeeID}/workExperiences/{$workExperienceId}", [
        'employer' => 'Company B',
        'position' => 'Software Engineer',
        'from' => '2020-01-01',
        'to' => '2021-01-01'
    ]);
    $response->assertStatus(200);
});

test('delete work experience', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);

    $workExperienceId = DB::table('tblemployeeworkexperience')->insertGetId([
        'employeeId' => $employee->EmployeeID,
        'employer' => 'Company A',
        'position' => 'Software Engineer',
        'from_date' => '2020-01-01',
        'to_date' => '2021-01-01'
    ]);
    $response = $this->delete("/api/employee/{$employee->EmployeeID}/workExperiences/{$workExperienceId}");
    $response->assertStatus(200);
});


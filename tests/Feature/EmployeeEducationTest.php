<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('get all employee education', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);
    $response = $this->get("/api/employee/{$employee->EmployeeID}/education");
    $response->assertStatus(200);
});

test('add new education', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);
    $response = $this->post("/api/employee/{$employee->EmployeeID}/education", [
        'education' => 2,
        'institute' => 'University of the Philippines',
        'major' => 'Computer Science',
        'yearGraduated' => 2020,
        'gpa' => 3.5,
        'startDate' => '2016-01-01',
        'endDate' => '2020-01-01',
    ]);


    $response->assertStatus(201);
});

test('update education', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);

    $educationId = DB::table('tblemployeeeducation')->insertGetId([
        'educationId' => 2,
        'institute' => 'University of the Philippines',
        'major' => 'Computer Science',
        'year_graduated' => 2020,
        'gpa' => 3.5,
        'start_date' => '2016-01-01',
        'end_date' => '2020-01-01',
        'EmployeeID' => $employee->EmployeeID
    ]);

    $response = $this->put("/api/employee/{$employee->EmployeeID}/education/{$educationId}", [
        'education' => 3,
        'institute' => 'University of the Philippines',
        'major' => 'Computer Science',
        'yearGraduated' => 2020,
        'gpa' => 3.5,
        'startDate' => '2016-01-01',
        'endDate' => '2020-01-01',
    ]);

    $response->assertStatus(200);
});

test('delete education', function () {
    $employee = User::factory()->create();
    $this->actingAs($employee);

    $educationId = DB::table('tblemployeeeducation')->insertGetId([
        'educationId' => 2,
        'institute' => 'University of the Philippines',
        'major' => 'Computer Science',
        'year_graduated' => 2020,
        'gpa' => 3.5,
        'start_date' => '2016-01-01',
        'end_date' => '2020-01-01',
        'EmployeeID' => $employee->EmployeeID
    ]);

    $response = $this->delete("/api/employee/{$employee->EmployeeID}/education/{$educationId}");

    $response->assertStatus(200);
});


<?php

use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

test('get all employees', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/api/admin/employee');
    $response->assertStatus(200);
});

test('search for employees by full name', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/admin/employee?name=John Doe');
    $response->assertStatus(200);
});

test('search for employees by employment status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/admin/employee?employmentStatus=1');
    $response->assertStatus(200);
});

test('search for employees by job title', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/admin/employee?jobTitle=1');
    $response->assertStatus(200);
});

test('search for employees by department', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/admin/employee?department=1');
    $response->assertStatus(200);
});

test('search for employees by id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/admin/employee?id=1');
    $response->assertStatus(200);
});

test('create a new employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->post('/api/admin/employee', [
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName
    ]);
    $response->assertStatus(201);
});

test('get an employee by id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/admin/employee/1');
    $response->assertStatus(200);
});

test('update an employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $response = $this->put('/api/admin/employee/' . $id, [
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName
    ]);
    $response->assertStatus(200);
});

test('update an employee\'s personal details', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $response = $this->put('/api/admin/employee/' . $id . '/personalDetails', [
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName
    ]);
    $response->assertStatus(200);
});

test('update an employee\'s contact details', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $response = $this->put('/api/admin/employee/' . $id . '/contactDetails', [
        'email' => fake()->email,
        'phone' => fake()->phoneNumber,
        'address' => fake()->address
    ]);
    $response->assertStatus(200);
});

test('update an employee\'s job details', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $response = $this->put('/api/admin/employee/' . $id . '/jobDetails', [
        'employmentStatus' => 1,
        'jobTitle' => 1,
        'department' => 1
    ]);
    $response->assertStatus(200);
});

test('delete an employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $response = $this->delete('/api/admin/employee/' . $id);
    $response->assertStatus(200);
});

test('get an employee by id as an employee', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/employee/1');
    $response->assertStatus(200);
});

test('update an employee\'s personal details as an employee', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $id = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $response = $this->put('/api/employee/' . $id . '/personalDetails', [
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName
    ]);
    $response->assertStatus(200);
});

test('update an employee\'s contact details as an employee', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $id = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $response = $this->put('/api/employee/' . $id . '/contactDetails', [
        'email' => fake()->email,
        'phone' => fake()->phoneNumber,
        'address' => fake()->address
    ]);
    $response->assertStatus(200);
});
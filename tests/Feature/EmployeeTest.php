<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use App\Enum\RoleEnum;

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

test('update an employee\'s image', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $response = $this->put('/api/admin/employee/' . $id . '/image', [
        'image' => UploadedFile::fake()->image('avatar.jpg')
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


// SUPERVISOR ROLE
test('get all assigned employees as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/supervisor/employee');
    $response->assertStatus(200);
});

test('search for assigned employees by full name as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/supervisor/employee?name=John Doe');
    $response->assertStatus(200);
});

test('search for assigned employees by employment status as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/supervisor/employee?employmentStatus=1');
    $response->assertStatus(200);
});

test('search for assigned employees by job title as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/supervisor/employee?jobTitle=1');
    $response->assertStatus(200);
});

test('search for assigned employees by department as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/supervisor/employee?department=1');
    $response->assertStatus(200);
});

test('search for assigned employees by id as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/supervisor/employee?id=1');
    $response->assertStatus(200);
});

test('get an assigned employee by id as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/supervisor/employee/1');
    $response->assertStatus(200);
});

test('update an assigned employee as a supervisor', function () {
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

    $response = $this->put('/api/supervisor/employee/' . $id, [
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName
    ]);
    $response->assertStatus(200);
});

test('update an assigned employee\'s personal details as a supervisor', function () {
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

    $response = $this->put('/api/supervisor/employee/' . $id . '/personalDetails', [
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName
    ]);
    $response->assertStatus(200);
});

test('update an assigned employee\'s contact details as a supervisor', function () {
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

    $response = $this->put('/api/supervisor/employee/' . $id . '/contactDetails', [
        'email' => fake()->email,
        'phone' => fake()->phoneNumber,
        'address' => fake()->address
    ]);
    $response->assertStatus(200);
});

test('update an assigned employee\'s job details as a supervisor', function () {
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

    $response = $this->put('/api/supervisor/employee/' . $id . '/jobDetails', [
        'employmentStatus' => 1,
        'jobTitle' => 1,
        'department' => 1
    ]);
    $response->assertStatus(200);
});

test('update an assigned employee\'s image as a supervisor', function () {
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

    $response = $this->put('/api/supervisor/employee/' . $id . '/image', [
        'image' => UploadedFile::fake()->image('avatar.jpg')
    ]);
    $response->assertStatus(200);
});

test('delete an assigned employee as a supervisor', function () {
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

    $response = $this->delete('/api/supervisor/employee/' . $id);
    $response->assertStatus(200);
});


// EMPLOYEE ROLE (SELF SERVICE)

test('get own employee details as an employee', function () {
    $user = User::factory()->create([
        'RoleID' => 3
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/employee/' . $user->EmployeeID);
    $response->assertStatus(200);
});

test('update own image as an employee', function () {
    $user = User::factory()->create([
        'RoleID' => 3
    ]);
    $this->actingAs($user);

    $response = $this->put('/api/employee/' . $user->EmployeeID . '/image', [
        'image' => UploadedFile::fake()->image('avatar.jpg')
    ]);
    $response->assertStatus(200);
});

test('update own details as an employee', function () {
    $user = User::factory()->create([
        'RoleID' => 3
    ]);
    $this->actingAs($user);

    $response = $this->put('/api/employee/' . $user->EmployeeID . '/personalDetails', [
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName
    ]);
    $response->assertStatus(200);
});

test('update an employee\'s contact details as an employee', function () {
    $user = User::factory()->create([
        'RoleID' => 3
    ]);
    $this->actingAs($user);

    $response = $this->put('/api/employee/' . $user->EmployeeID . '/contactDetails', [
        'email' => fake()->email,
        'phone' => fake()->phoneNumber,
        'address' => fake()->address
    ]);
    $response->assertStatus(200);
});
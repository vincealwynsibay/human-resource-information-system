<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use App\Enum\RoleEnum;

test('get all employees', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/api/employee');
    $response->assertStatus(200);
});

test('search for employees by full name', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/employee?name=John Doe');
    $response->assertStatus(200);
});

test('search for employees by employment status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/employee?employmentStatus=1');
    $response->assertStatus(200);
});

test('search for employees by job title', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/employee?jobTitle=1');
    $response->assertStatus(200);
});

test('search for employees by department', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/employee?department=1');
    $response->assertStatus(200);
});

test('search for employees by id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/employee?id=1');
    $response->assertStatus(200);
});

test('create a new employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->post('/api/employee', [
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName
    ]);
    $response->assertStatus(201);
});

test('get an employee by id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/employee/1');
    $response->assertStatus(200);
});

test('assign employee to a supervisor as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $employeeId = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $supervisorId = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $response = $this->put('/api/employee/' . $employeeId . '/assignSupervisor', [
        'supervisor' => $supervisorId
    ]);
    $response->assertStatus(200);
});

test('remove assigned supervisor from an employee', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $employeeId = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $supervisorId = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $this->put('/api/employee/' . $employeeId . '/assignSupervisor', [
        'supervisor' => $supervisorId
    ]);

    $response = $this->put('/api/employee/' . $employeeId . '/removeSupervisor', [
        'supervisor' => $supervisorId
    ]);
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

    $response = $this->put('/api/employee/' . $id, [
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

    $response = $this->put('/api/employee/' . $id . '/personalDetails', [
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

    $response = $this->put('/api/employee/' . $id . '/contactDetails', [
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

    $response = $this->put('/api/employee/' . $id . '/jobDetails', [
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

    $response = $this->put('/api/employee/' . $id . '/image', [
        'image' => UploadedFile::fake()->image('avatar.jpg')
    ]);
    $response->assertStatus(200);
});

test('assign work shift as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $employeeId = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $workShiftId = DB::table('tblworkshift')->insertGetId([
        'name' => 'Morning',
        'hours_per_day' => 8,
        'start_time1' => '08:00:00',
        'end_time1' => '17:00:00'
    ]);

    $response = $this->put('/api/employee/' . $employeeId . '/assignWorkShift', [
        'workShift' => $workShiftId
    ]);
    $response->assertStatus(200);
});

test('remove work shift as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $employeeId = DB::table('tblemployee')->insertGetId([
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName,
        'joined_date' => Date::now()
    ]);

    $workShiftId = DB::table('tblworkshift')->insertGetId([
        'name' => 'Morning',
        'hours_per_day' => 8,
        'start_time1' => '08:00:00',
        'end_time1' => '17:00:00'
    ]);

    $this->put('/api/employee/' . $employeeId . '/assignWorkShift', [
        'workShift' => $workShiftId
    ]);

    $response = $this->put('/api/employee/' . $employeeId . '/removeWorkShift', [
        'workShift' => $workShiftId
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

    $response = $this->delete('/api/employee/' . $id);
    $response->assertStatus(200);
});


// SUPERVISOR ROLE
test('get all assigned employees as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/employee');
    $response->assertStatus(200);
});

test('search for assigned employees by full name as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/employee?name=John Doe');
    $response->assertStatus(200);
});

test('search for assigned employees by employment status as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/employee?employmentStatus=1');
    $response->assertStatus(200);
});

test('search for assigned employees by job title as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/employee?jobTitle=1');
    $response->assertStatus(200);
});

test('search for assigned employees by department as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/employee?department=1');
    $response->assertStatus(200);
});

test('search for assigned employees by id as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/employee?id=1');
    $response->assertStatus(200);
});

test('get an assigned employee by id as a supervisor', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/employee/1');
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
    DB::table('tblemployeesupervisor')->insert(['SupervisorID' => $user->EmployeeID, 'SubordinateID' => $id]);

    $response = $this->put('/api/employee/' . $id . '/personalDetails', [
        'firstname' => fake()->firstName,
        'lastname' => fake()->lastName,
        'middlename' => fake()->firstName
    ]);
    $response->assertStatus(200);
});

test('an employee cannot update an employee they do not supervise', function () {
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

    $response = $this->putJson('/api/employee/' . $id . '/personalDetails', ['firstname' => 'Changed', 'lastname' => 'Changed']);
    $response->assertStatus(404);
    expect(DB::table('tblemployee')->where('id', $id)->value('firstname'))->not->toBe('Changed');
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
    DB::table('tblemployeesupervisor')->insert(['SupervisorID' => $user->EmployeeID, 'SubordinateID' => $id]);

    $response = $this->put('/api/employee/' . $id . '/contactDetails', [
        'email' => fake()->email,
        'phone' => fake()->phoneNumber,
        'address' => fake()->address
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

    $response = $this->put('/api/employee/' . $id . '/image', [
        'image' => UploadedFile::fake()->image('avatar.jpg')
    ]);
    $response->assertStatus(200);
});

// EMPLOYEE ROLE (SELF SERVICE)
test('get own employee details as an employee', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);
    $response = $this->get('/api/employee/' . $user->EmployeeID);
    $response->assertStatus(200);
});

test('update own image as an employee', function () {
    $user = User::factory()->create([
        'RoleID' => 2
    ]);
    $this->actingAs($user);

    $response = $this->put('/api/employee/' . $user->EmployeeID . '/image', [
        'image' => UploadedFile::fake()->image('avatar.jpg')
    ]);
    $response->assertStatus(200);
});

test('update own details as an employee', function () {
    $user = User::factory()->create([
        'RoleID' => 2
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
        'RoleID' => 2
    ]);
    $this->actingAs($user);

    $response = $this->put('/api/employee/' . $user->EmployeeID . '/contactDetails', [
        'email' => fake()->email,
        'phone' => fake()->phoneNumber,
        'address' => fake()->address
    ]);
    $response->assertStatus(200);
});
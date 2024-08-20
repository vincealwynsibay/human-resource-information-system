<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('get user payroll items as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // create an employee
    $employeeId = DB::table("tblemployee")->insert([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'middlename' => fake()->email,
        'joined_date' => now()
    ]);

    // assign payroll items to the employee
    DB::table('tblpayrollitem')->insert([
        'EmployeeID' => $employeeId,
        'item_type' => 'BONUS',
        'amount' => 1000,
        'description' => 'Christmas bonus',
        'created_date' => now(),
    ]);

    DB::table('tblpayrollitem')->insert([
        'EmployeeID' => $employeeId,
        'item_type' => 'DEDUCTION',
        'amount' => 500,
        'description' => 'Tax deduction',
        'created_date' => now(),
    ]);

    // get the payroll items of the employee
    $response = $this->get('api/admin/payrollitem?employeeId=' . $employeeId);
    // assert the response
    $response->assertStatus(200);


});

test('get own payroll items', function () {
    $employee = User::factory()->create([
        'RoleID' => 3
    ]);
    $this->actingAs($employee);


    $employeeId = DB::table("tblemployee")->insert([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'middlename' => fake()->email,
        'joined_date' => now()
    ]);

    // assign payroll items to the employee
    DB::table('tblpayrollitem')->insert([
        'EmployeeID' => $employeeId,
        'item_type' => 'BONUS',
        'amount' => 1000,
        'description' => 'Christmas bonus',
        'created_date' => now(),
    ]);

    DB::table('tblpayrollitem')->insert([
        'EmployeeID' => $employeeId,
        'item_type' => 'DEDUCTION',
        'amount' => 500,
        'description' => 'Tax deduction',
        'created_date' => now(),
    ]);

    // get the payroll items of the employee
    $response = $this->get('api/payrollitem/me/');
    // assert the response
    $response->assertStatus(200);

});

test('add a payroll item to an employee as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // create an employee
    $employeeId = DB::table("tblemployee")->insert([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'middlename' => fake()->email,
        'joined_date' => now()
    ]);

    $response = $this->post('/api/admin/payrollitem/', [
        'employeeId' => $employeeId,
        'itemType' => 'BONUS',
        'amount' => 1000,
        'description' => 'Christmas bonus',
    ]);
    $response->assertStatus(201);

});

test('add a bonus payroll item to an employee as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // create an employee
    $employeeId = DB::table("tblemployee")->insert([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'middlename' => fake()->email,
        'joined_date' => now()
    ]);

    $employeeId = DB::table("tblemployee")->insert([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'middlename' => fake()->email,
        'joined_date' => now()
    ]);

    $response = $this->post('/api/admin/payrollitem/bonus', [
        'employeeId' => $employeeId,
        'amount' => 1000,
        'description' => 'Christmas bonus',
    ]);
    $response->assertStatus(201);
});

test('add a deduction payroll item to an employee as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // create an employee

    $employeeId = DB::table("tblemployee")->insert([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'middlename' => fake()->email,
        'joined_date' => now()
    ]);

    $response = $this->post('/api/admin/payrollitem/deduction', [
        'employeeId' => $employeeId,
        'amount' => 500,
        'description' => 'Tax deduction',
    ]);
    $response->assertStatus(201);
});

test('add a allowance payroll item to an employee as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // create an employee

    $employeeId = DB::table("tblemployee")->insert([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'middlename' => fake()->email,
        'joined_date' => now()
    ]);

    $response = $this->post('/api/admin/payrollitem/allowance', [
        'employeeId' => $employeeId,
        'amount' => 500,
        'description' => 'Housing allowance',
    ]);
    $response->assertStatus(201);
});

test('add a commission payroll item to an employee as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // create an employee

    $employeeId = DB::table("tblemployee")->insert([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'middlename' => fake()->email,
        'joined_date' => now()
    ]);

    $response = $this->post('/api/admin/payrollitem/commission', [
        'employeeId' => $employeeId,
        'amount' => 500,
        'description' => 'Sales commission',
    ]);
    $response->assertStatus(201);
});


test('update an existing payroll item of an employee as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // create an employee

    $employeeId = DB::table("tblemployee")->insert([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'middlename' => fake()->email,
        'joined_date' => now()
    ]);

    // assign payroll items to the employee
    $payrollItemId = DB::table('tblpayrollitem')->insertGetId([
        'EmployeeID' => $employeeId,
        'item_type' => 'bonus',
        'amount' => 1000,
        'description' => 'Christmas bonus',
        'created_date' => now(),
    ]);

    $response = $this->put('/api/admin/payrollitem/' . $payrollItemId, [
        'employeeId' => $employeeId,
        'itemType' => 'BONUS',
        'amount' => 2000,
        'description' => 'Christmas bonus',
    ]);

    $response->assertStatus(200);

});

test('delete an existing payroll item of an employee as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // create an employee

    $employeeId = DB::table("tblemployee")->insert([
        'firstname' => 'John',
        'lastname' => 'Doe',
        'middlename' => fake()->email,
        'joined_date' => now()
    ]);

    // assign payroll items to the employee
    $payrollItemid = DB::table('tblpayrollitem')->insertGetId([
        'EmployeeID' => $employeeId,
        'item_type' => 'bonus',
        'amount' => 1000,
        'description' => 'Christmas bonus',
        'created_date' => now(),
    ]);

    $response = $this->delete('/api/admin/payrollitem/' . $payrollItemid);
    $response->assertStatus(200);
});

<?php

// get, post, put, delete

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('get all departments', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/department');
    $response->assertStatus(200);
});

test('create a new department', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->post('/api/department', [
        'name' => fake()->company
    ]);
    $response->assertStatus(201);
});

test('update a department', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tbldepartment')->insertGetId([
        'name' => 'Department 1'
    ]);
    $response = $this->put('/api/department/' . $id, [
        'name' => fake()->company
    ]);
    $response->assertStatus(201);
});

test('delete a department', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tbldepartment')->insertGetId([
        'name' => 'Department 1'
    ]);
    $response = $this->delete('/api/department/' . $id);
    $response->assertStatus(200);
});
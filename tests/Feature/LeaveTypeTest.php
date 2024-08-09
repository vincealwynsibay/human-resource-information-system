<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('get all leave types', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/leavetype');
    $response->assertStatus(200);
});

test('create a new leave type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->post('/api/leavetype', [
        'name' => fake()->company
    ]);
    $response->assertStatus(201);
});

test('update a leave type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tblleavetype')->insertGetId([
        'name' => 'Leave Type 1'
    ]);
    $response = $this->put('/api/leavetype/' . $id, [
        'name' => fake()->company
    ]);
    $response->assertStatus(201);
});

test('delete a leave type', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tblleavetype')->insertGetId([
        'name' => 'Leave Type 1'
    ]);
    $response = $this->delete('/api/leavetype/' . $id);
    $response->assertStatus(200);
});
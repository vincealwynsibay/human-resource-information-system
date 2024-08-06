<?php

// get, post, put, delete

use App\Models\User;

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
    $response = $this->put('/api/department/1', [
        'name' => fake()->company
    ]);
    $response->assertStatus(201);
});

test('delete a department', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->delete('/api/department/1');
    $response->assertStatus(200);
});
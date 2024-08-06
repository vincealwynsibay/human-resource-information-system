<?php

use App\Models\User;

test('get all employment status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/api/employmentstatus');
    $response->assertStatus(200);
});

test('create a new employment status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post('/api/employmentstatus', [
        'name' => fake()->jobTitle
    ]);
    $response->assertStatus(201);
});

test('update an employment status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->put('/api/employmentstatus/1', [
        'name' => fake()->jobTitle
    ]);
    $response->assertStatus(201);
});

test('delete an employment status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->delete('/api/employmentstatus/1');
    $response->assertStatus(200);
});
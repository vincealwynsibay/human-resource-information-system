<?php

use App\Models\User;

test('get all job titles', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get('/api/jobtitle');
    $response->assertStatus(200);
});

test('create a new job title', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post('/api/jobtitle', [
        'name' => fake()->jobTitle
    ]);
    $response->assertStatus(201);
});

test('update a job title', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->put('/api/jobtitle/1', [
        'name' => fake()->jobTitle
    ]);
    $response->assertStatus(201);
});

test('delete a job title', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->delete('/api/jobtitle/1');
    $response->assertStatus(200);
});
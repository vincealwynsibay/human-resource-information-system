<?php

use App\Models\User;

test('get work week', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/workweek/nice');

    $response->assertStatus(200);
});

test('update monday to full day', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->put('/api/workweek', [
        'mon' => 1,
        'tue' => 0,
        'wed' => 0,
        'thu' => 0,
        'fri' => 0,
        'sat' => 0,
        'sun' => 0,
    ]);

    $response->assertJson(['message' => 'Work week updated successfully']);

    $response->assertStatus(200);
});

test('update monday to friday to full day', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->put('/api/workweek', [
        'mon' => 1,
        'tue' => 1,
        'wed' => 1,
        'thu' => 1,
        'fri' => 1,
        'sat' => 0,
        'sun' => 0,
    ]);

    $response->assertJson(['message' => 'Work week updated successfully']);

    $response->assertStatus(200);
});

// test('update monday to friday to half day', function () {
//     $user = User::factory()->create();
//     $this->actingAs($user);
//     $response = $this->put('/api/workweek', [
//         'mon' => 1,
//         'tue' => 1,
//         'wed' => 1,
//         'thu' => 1,
//         'fri' => 1,
//         'sat' => 0,
//         'sun' => 0,
//     ]);

//     $response->assertJson(['message' => 'Work week updated successfully']);

//     $response->assertStatus(200);
// });

test('update saturday and sunday to no work', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->put('/api/workweek', [
        'mon' => 1,
        'tue' => 1,
        'wed' => 1,
        'thu' => 1,
        'fri' => 1,
        'sat' => 0,
        'sun' => 0,
    ]);

    $response->assertStatus(200);
});
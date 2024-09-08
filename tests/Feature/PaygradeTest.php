<?php


use App\Models\User;

use Illuminate\Support\Facades\DB;

test('get all paygrades', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/paygrade');
    $response->assertStatus(200);
});

test('create paygrade', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->post('/api/paygrade', [
        'name' => 'Paygrade 1',
        'description' => 'Paygrade 1 description',
        'min_salary' => 1000,
        'max_salary' => 2000,
    ]);

    $response->assertJson(['message' => 'Paygrade created']);
    $response->assertStatus(201);
});

test('update paygrade', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->put('/api/paygrade/1', [
        'name' => 'Paygrade 1',
        'description' => 'Paygrade 1 description',
        'min_salary' => 1000,
        'max_salary' => 2000,
    ]);

    $response->assertJson(['message' => 'Paygrade updated']);
    $response->assertStatus(200);
});

test('delete paygrade', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->delete('/api/paygrade/1');
    $response->assertJson(['message' => 'Paygrade deleted']);
    $response->assertStatus(200);
});
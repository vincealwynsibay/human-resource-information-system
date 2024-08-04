<?php

use App\Models\User;

test('test admin', function () {

    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/admin');
    $response->assertStatus(200);
});

test('test employee cannot access admin', function () {

    $user = User::factory()->create(["RoleID" => 2]);
    $this->actingAs($user);
    $response = $this->get('/api/admin');
    $response->assertStatus(403);
});

test('test employee', function () {
    $user = User::factory()->create(["RoleID" => 2]);
    $this->actingAs($user);
    $response = $this->get('/api/employee');
    $response->assertStatus(200);
});




<?php

use App\Models\User;

test('test admin', function () {

    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/admin');
    $response->assertStatus(200);
});

test('test admin2', function () {

    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/role/admin');
    $response->assertStatus(200);
});

test('test employee cannot access admin', function () {

    $user = User::factory()->create(["RoleID" => 3]);
    $this->actingAs($user);
    $response = $this->get('/api/admin');
    $response->assertStatus(403);
});





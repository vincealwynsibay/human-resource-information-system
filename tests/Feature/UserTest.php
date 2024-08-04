<?php

use App\Models\User;

test('add new user as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->post('/api/user', [
        'username' => "New User",
        'password' => "Password",
        'firstName' => "New",
        'middleName' => "",
        'lastName' => "User",
        'role' => 1,
    ]);

    $response->assertStatus(200);
});

test('get all users as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/user');
    $response->assertStatus(200);
});

test('add new user as not admin', function () {
    $user = User::factory()->create(["RoleID" => 2]);
    $this->actingAs($user);
    $response = $this->post('/api/user', [
        'username' => "New User",
        'password' => "Password",
        'firstName' => "New",
        'middleName' => "",
        'lastName' => "User",
        'role' => 1,
    ]);

    $response->assertStatus(403);
});

test('remove a user as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user2 = User::factory()->create();
    $response = $this->delete('/api/user/' . $user2->id);
    $response->assertStatus(200);

});

test('change password as user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->put('/api/user', [
        'oldPassword' => "password",
        'newPassword' => "password1.",
        'newPassword_confirmation' => "password1.",
    ]);
    $response->assertStatus(200);
});
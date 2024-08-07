<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

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
    $id = DB::table('tblemploymentstatus')->insertGetId([
        'name' => 'Employment Status 1'
    ]);
    $response = $this->put('/api/employmentstatus/' . $id, [
        'name' => fake()->jobTitle
    ]);
    $response->assertStatus(201);
});

test('delete an employment status', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tblemploymentstatus')->insertGetId([
        'name' => 'Employment Status 1'
    ]);
    $response = $this->delete('/api/employmentstatus/' . $id);
    $response->assertStatus(200);
});
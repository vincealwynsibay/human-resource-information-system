<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('example', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('get all holidays', function () {

    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/holiday');
    $response->assertStatus(200);
});

test('create a new holiday', function () {

    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->post('/api/holiday', [
        'name' => fake()->company,
        'date' => fake()->date,
        'is_full_day' => 0,
        'is_recurring' => false
    ]);
    $response->assertStatus(201);
});

test('update a holiday', function () {

    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tblholiday')->insertGetId([
        'name' => 'Holiday 1',
        'date' => '2022-01-01',
        'is_full_day' => 1,
        'is_recurring' => false
    ]);
    $response = $this->put('/api/holiday/' . $id, [
        'name' => fake()->company,
        'date' => fake()->date,
        'is_full_day' => 1,
        'is_recurring' => false
    ]);
    $response->assertStatus(200);
});


test('delete a holiday', function () {

    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tblholiday')->insertGetId([
        'name' => 'Holiday 1',
        'date' => '2022-01-01',
        'is_full_day' => 1,
        'is_recurring' => false
    ]);
    $response = $this->delete('/api/holiday/' . $id);
    $response->assertStatus(200);
});

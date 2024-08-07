<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('get all work shifts', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $response = $this->get('/api/workshift');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        '*' => [
            'id',
            'name',
            'hours_per_day',
            'start_time',
            'end_time',
            'created_at',
            'updated_at'
        ]
    ]);
});

test('create a new work shift', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post('/api/workshift', [
        'name' => 'Morning Shift',
        'hours_per_day' => 8,
        'start_time' => '08:00',
        'end_time' => '16:00'
    ]);

    $response->assertStatus(201);
    $response->assertJson([
        'message' => 'New Work Shift created successfully'
    ]);
});

test('update a work shift', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $id = DB::table('tblworkshift')->insertGetId([
        'name' => 'Morning Shift',
        'hours_per_day' => 8,
        'start_time' => '08:00',
        'end_time' => '16:00'
    ]);

    $response = $this->put("/api/workshift/" . $id, [
        'name' => 'Night Shift',
        'hours_per_day' => 10,
        'start_time' => '08:00',
        'end_time' => '17:00'
    ]);


    $response->assertStatus(201);
});

test('delete a work shift', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tblworkshift')->insertGetId([
        'name' => 'Morning Shift',
        'hours_per_day' => 8,
        'start_time' => '08:00',
        'end_time' => '16:00'
    ]);
    $response = $this->delete("/api/workshift/" . $id);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'Work Shift deleted successfully'
    ]);
});


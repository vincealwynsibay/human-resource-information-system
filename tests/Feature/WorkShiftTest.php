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
            'start_time1',
            'end_time1',
            'start_time2',
            'end_time2',
        ]
    ]);
});

test('create a new work shift', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post('/api/workshift', [
        'name' => 'Morning Shift',
        'hours_per_day' => 8,
        'start_time1' => '08:00',
        'end_time1' => '16:00'
    ]);

    $response->assertStatus(201);
    $response->assertJson([
        'message' => 'New Work Shift created successfully'
    ]);
});
test('create a new work shift with break time', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post('/api/workshift', [
        'name' => 'Morning Shift',
        'hours_per_day' => 8,
        'start_time1' => '08:00',
        'end_time1' => '16:00',
        'start_time2' => '12:00',
        'end_time2' => '13:00'
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
        'start_time1' => '08:00',
        'end_time1' => '16:00',
        'start_time2' => '12:00',
        'end_time2' => '13:00'
    ]);

    $response = $this->put("/api/workshift/" . $id, [
        'name' => 'Night Shift',
        'hours_per_day' => 10,
        'start_time1' => '08:00',
        'end_time1' => '17:00',
        'start_time2' => '12:00',
        'end_time2' => '14:00'
    ]);


    $response->assertStatus(201);
});

test('delete a work shift', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $id = DB::table('tblworkshift')->insertGetId([
        'name' => 'Morning Shift',
        'hours_per_day' => 8,
        'start_time1' => '08:00',
        'end_time1' => '16:00'
    ]);
    $response = $this->delete("/api/workshift/" . $id);

    $response->assertStatus(200);
    $response->assertJson([
        'message' => 'Work Shift deleted successfully'
    ]);
});


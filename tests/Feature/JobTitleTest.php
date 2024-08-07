<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

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
    $id = DB::table('tbljobtitle')->insertGetId([
        'name' => 'Job Title 1'
    ]);

    $response = $this->put('/api/jobtitle/' . $id, [
        'name' => fake()->jobTitle
    ]);
    $response->assertStatus(201);
});

test('delete a job title', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $id = DB::table('tbljobtitle')->insertGetId([
        'name' => 'Job Title 1'
    ]);

    $response = $this->delete('/api/jobtitle/' . $id);
    $response->assertStatus(200);
});
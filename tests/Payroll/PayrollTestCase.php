<?php

namespace Tests\Payroll;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Payroll API tests run on an in-memory SQLite database with the migrations only: they do not need the app's MySQL
 * schema or its stored procedures. Users are built in memory with a role; nothing is written to tbluser.
 */
abstract class PayrollTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $k => $v) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $_SERVER[$k] = $v;
        }
        parent::setUp();
        // The app's users live in tbluser, part of the MySQL schema dump rather than the migrations.
        \Illuminate\Support\Facades\Schema::create('tbluser', function (\Illuminate\Database\Schema\Blueprint $t) {
            $t->id();
            $t->string('username');
            $t->string('password')->default('');
            $t->unsignedInteger('EmployeeID')->nullable();
            $t->unsignedTinyInteger('RoleID');
            $t->string('status')->default('Active');
        });
        foreach ([1, 2, 3] as $id) {
            \Illuminate\Support\Facades\DB::table('tbluser')->insert(['id' => $id, 'username' => "admin{$id}", 'RoleID' => 1]);
        }
    }

    protected function actAs(string $role, int $id = 1): User
    {
        $user = new User(['username' => "{$role}{$id}", 'RoleID' => $role === 'admin' ? 1 : 2]);
        $user->id = $id;
        $this->actingAs($user, "sanctum");
        return $user;
    }

    /** A saved pay profile through the API. */
    protected function profile(string $employeeId, array $fields): array
    {
        return $this->putJson("/api/payroll/profiles/{$employeeId}", $fields)->assertOk()->json();
    }
}

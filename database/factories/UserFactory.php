<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $newEmployeeId = DB::table('tblemployee')->insertGetId([
            "firstname" => fake()->firstName(),
            "lastname" => fake()->lastName(),
            "joined_date" => Date::now()
        ]);

        DB::table('tblemployeeworkshift')->insert([
            'EmployeeID' => $newEmployeeId,
            'WorkShiftID' => 1,
        ]);

        return [
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'firstName' => fake()->firstName(),
            'lastName' => fake()->lastName(),
            'middleName' => fake()->lastName(),
            'EmployeeID' => $newEmployeeId,
            'RoleID' => 1,
            'status' => "Active",
        ];
    }

}

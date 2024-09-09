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


        $newEmployeeId = DB::select('CALL procInsertEmployee(?, ?, ?, ?)', [
            $this->faker->firstName(),
            $this->faker->lastName(),
            "",
            Date::now()
        ]);

        $newEmployeeId = 1;

        DB::select('CALL procAssignWorkShift(?,?)', [
            $newEmployeeId,
            1
        ]);

        return [
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'EmployeeID' => $newEmployeeId,
            'RoleID' => 1,
            'status' => "Active",
        ];
    }

}

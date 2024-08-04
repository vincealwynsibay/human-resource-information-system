<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('tbluserrole')->insert([
            'id' => 1,
            'name' => "ADMIN",
            'description' => "User with administration rights"
        ]);

        DB::table('tbluserrole')->insert([
            'id' => 2,
            'name' => "EMPLOYEE",
            'description' => "User with an employee account"
        ]);
    }
}

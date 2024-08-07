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
            'name' => "SUPERVISOR",
            'description' => "User that supervises employees"
        ]);

        DB::table('tbluserrole')->insert([
            'id' => 3,
            'name' => "EMPLOYEE",
            'description' => "User with an employee account"
        ]);

        DB::table('tblemployee')->insert([
            'id' => 1,
            'firstname' => "John",
            'lastname' => "Doe",
            'middlename' => "Smith",
            'joined_date' => now()
        ]);

        DB::table('tblemployee')->insert([
            'id' => 2,
            'firstname' => "Jane",
            'lastname' => "Doe",
            'middlename' => "Smith",
            'joined_date' => now()
        ]);

        DB::table('tblemployee')->insert([
            'id' => 3,
            'firstname' => "John",
            'lastname' => "Smith",
            'middlename' => "Doe",
            'joined_date' => now()
        ]);

        DB::table('tblemploymentstatus')->insert([
            'id' => 1,
            'name' => "Full Time"
        ]);

        DB::table('tbljobtitle')->insert([
            'id' => 1,
            'name' => "Software Developer",
            'description' => "Develops software applications"
        ]);

        DB::table('tbldepartment')->insert([
            'id' => 1,
            'name' => "IT"
        ]);


    }
}

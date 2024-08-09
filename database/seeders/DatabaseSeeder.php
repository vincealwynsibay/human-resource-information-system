<?php

namespace Database\Seeders;

use App\Enum\RoleEnum;
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
            'id' => RoleEnum::ADMIN->value,
            'name' => "ADMIN",
            'description' => "User with administration rights"
        ]);


        DB::table('tbluserrole')->insert([
            'id' => RoleEnum::SUPERVISOR->value,
            'name' => "SUPERVISOR",
            'description' => "User that supervises employees"
        ]);

        DB::table('tbluserrole')->insert([
            'id' => RoleEnum::EMPLOYEE->value,
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

        DB::table('tblworkshift')->insert([
            'id' => 1,
            'name' => "Full Time",
            'hours_per_day' => 8,
            'start_time' => "08:00:00",
            'end_time' => "17:00:00",
            'start_break_time' => "12:00:00",
            'end_break_time' => "13:00:00"
        ]);

        DB::table('tblworkshift')->insert([
            'id' => 2,
            'name' => "Afternoon",
            'hours_per_day' => 8,
            'start_time' => "13:00:00",
            'end_time' => "22:00:00"
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

        DB::table('tblleavetype')->insert([
            'id' => 1,
            'name' => "Annual Leave",
        ]);


    }
}

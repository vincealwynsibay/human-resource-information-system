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
            'start_time1' => "08:00:00",
            'end_time1' => "17:00:00",
            'start_time2' => "13:00:00",
            'end_time2' => "16:00:00"
        ]);

        DB::table('tblworkshift')->insert([
            'id' => 2,
            'name' => "Afternoon",
            'hours_per_day' => 8,
            'start_time1' => "13:00:00",
            'end_time1' => "21:00:00"
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

        DB::table('tblleavetype')->insert([
            'id' => 2,
            'name' => 'Vacation Leave'
        ]);

        // 5 days per year
        DB::table('tblleavetype')->insert([
            'id' => 3,
            'name' => 'Sick Leave'
        ]);

        // 105 days of leave paid 
        DB::table('tblleavetype')->insert([
            'id' => 4,
            'name' => 'Maternity Leave'
        ]);

        // 7 days of leave paid (up to 14 days) 
        DB::table('tblleavetype')->insert([
            'id' => 5,
            'name' => 'Paternity Leave'
        ]);

        // 7 days of leave paid (up to 14 days) 
        DB::table('tblleavetype')->insert([
            'id' => 6,
            'name' => 'Parental Leave for Solo Parents'
        ]);

        DB::table('tblworkweek')->insert([
            'id' => 1,
            'mon' => 1,
            'tue' => 1,
            'wed' => 1,
            'thu' => 1,
            'fri' => 1,
            'sat' => 0,
            'sun' => 0
        ]);

        DB::table('tblpayperiod')->insert([
            'id' => 1,
            'name' => "Weekly",
        ]);

        DB::table('tblpayperiod')->insert([
            'id' => 2,
            'name' => "Bi Weekly",
        ]);

        DB::table('tblpayperiod')->insert([
            'id' => 3,
            'name' => "Semi Monthly",
        ]);

        DB::table('tblpayperiod')->insert([
            'id' => 4,
            'name' => "Monthly",
        ]);

        DB::table('tblpayperiod')->insert([
            'id' => 5,
            'name' => "Monthly on first pay of month.",
        ]);

        DB::table('tblpayperiod')->insert([
            'id' => 6,
            'name' => "Hourly",
        ]);

        // DB::table('tblpayroll')->insert([
        //     'id' => 1,
        //     'pay_period_id' => 1,
        //     'start_date' => now(),
        //     'end_date' => now(),
        //     'pay_date' => now(),
        //     'status' => "PENDING"
        // ]);
        DB::table('tblpaygrade')->insert([
            'id' => 1,
            'name' => "Grade 1",
            'min_salary' => 10000,
            'max_salary' => 20000
        ]);

        DB::table('tbleducation')->insert([
            'id' => 1,
            'name' => "High School"
        ]);

        DB::table('tbleducation')->insert([
            'id' => 2,
            'name' => "Bachelor's Degree"
        ]);

        DB::table('tbleducation')->insert([
            'id' => 3,
            'name' => "Master's Degree"
        ]);



    }
}

<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

// A payroll run computed from the HR time records: absences, lateness and approved overtime reach the payslip.

function attendanceDay(int $employeeId, string $date, string $in1 = '08:00', ?string $otIn = null, ?string $otOut = null): array
{
    return [
        'EmployeeID' => $employeeId, 'date' => $date,
        'time_in_session1' => "{$date} {$in1}:00", 'time_out_session1' => "{$date} 12:00:00",
        'time_in_session2' => "{$date} 13:00:00", 'time_out_session2' => "{$date} 17:00:00",
        'time_in_overtime' => $otIn ? "{$date} {$otIn}:00" : null, 'time_out_overtime' => $otOut ? "{$date} {$otOut}:00" : null,
    ];
}

test('a payroll run takes absences, lateness and approved overtime from the time records', function () {
    $this->actingAs(User::factory()->create());

    // Employee 3 works Monday to Friday, 8 to 12 and 1 to 5, and earns 26,100 a month (1,200 a day, 150 an hour).
    $shift = DB::table('tblworkshift')->insertGetId(['name' => 'Office', 'hours_per_day' => 8, 'start_time1' => '08:00:00', 'end_time1' => '12:00:00', 'start_time2' => '13:00:00', 'end_time2' => '17:00:00']);
    DB::table('tblemployeeworkshift')->insert(['EmployeeID' => 3, 'WorkShiftID' => $shift]);
    DB::table('tblworkweek')->insert(['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0]);
    $this->putJson('/api/payroll/profiles/3', ['name' => 'John Smith', 'payBasis' => 'monthly', 'monthlySalary' => 26100, 'region' => 'NCR'])->assertOk();

    // September 1-15, 2026: present every weekday except Monday the 7th; 30 minutes late on the 2nd; 2 hours of
    // approved overtime on the 3rd.
    foreach (['2026-09-01', '2026-09-04', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11', '2026-09-14', '2026-09-15'] as $d) {
        DB::table('tblattendancerecord')->insert(attendanceDay(3, $d));
    }
    DB::table('tblattendancerecord')->insert(attendanceDay(3, '2026-09-02', '08:30'));
    DB::table('tblattendancerecord')->insert(attendanceDay(3, '2026-09-03', '08:00', '17:00', '19:00'));
    DB::table('tblovertime')->insert(['EmployeeID' => 3, 'reason' => 'Month-end close', 'duration' => 2, 'status' => 'APPROVED', 'date_created' => now(), 'requested_date' => '2026-09-03']);

    $run = $this->postJson('/api/payroll/runs', [
        'periodStart' => '2026-09-01', 'periodEnd' => '2026-09-15', 'frequency' => 'SEMI_MONTHLY', 'useAttendance' => true,
    ])->assertCreated()->json();

    $slip = $run['payslips'][0]['data'];
    $line = fn (array $lines, string $code) => collect($lines)->firstWhere('code', $code);
    expect($line($slip['deductions'], 'ABSENCE'))->toMatchArray(['label' => 'Absences (1 day/s)', 'amount' => 1200]);
    expect($line($slip['deductions'], 'LATE'))->toMatchArray(['label' => 'Late (30 min)', 'amount' => 75]);
    expect($line($slip['deductions'], 'UNDERTIME'))->toBeNull();
    expect($line($slip['earnings'], 'OVERTIME')['amount'])->toEqual(375); // 2 hours x 150 x 1.25

    $summary = $run['inputs']['attendance']['3'];
    expect($summary)->toMatchArray(['scheduledDays' => 11, 'daysPresent' => 10, 'absentDays' => 1, 'lateMinutes' => 30]);
    expect((float) $summary['overtimeHours'])->toEqual(2.0);
});

test('a run without useAttendance ignores the time records', function () {
    $this->actingAs(User::factory()->create());
    $this->putJson('/api/payroll/profiles/3', ['name' => 'John Smith', 'payBasis' => 'monthly', 'monthlySalary' => 26100, 'region' => 'NCR'])->assertOk();
    $run = $this->postJson('/api/payroll/runs', ['periodStart' => '2026-09-01', 'periodEnd' => '2026-09-15', 'frequency' => 'SEMI_MONTHLY'])->assertCreated()->json();
    expect(collect($run['payslips'][0]['data']['deductions'])->pluck('code')->all())->not->toContain('ABSENCE');
    expect($run['inputs']['attendance'])->toBeNull();
});

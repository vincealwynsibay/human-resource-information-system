<?php

namespace Tests\Payroll;

use App\Payroll\AttendanceInputs;
use App\Payroll\Holidays;
use PHPUnit\Framework\TestCase;

class AttendanceInputsTest extends TestCase
{
    private const WEEK = ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0];
    private const SHIFT = ['hours' => 8, 'start1' => '08:00:00', 'end1' => '12:00:00', 'start2' => '13:00:00', 'end2' => '17:00:00'];

    private static function day(string $date, string $in1 = '08:00', string $out1 = '12:00', string $in2 = '13:00', string $out2 = '17:00', ?string $otIn = null, ?string $otOut = null): array
    {
        $at = fn ($t) => $t ? "{$date} {$t}:00" : null;
        return ['date' => $date, 'time_in_session1' => $at($in1), 'time_out_session1' => $at($out1), 'time_in_session2' => $at($in2), 'time_out_session2' => $at($out2),
            'time_in_overtime' => $at($otIn), 'time_out_overtime' => $at($otOut)];
    }

    /** Oct 1-15, 2026 (Thursday to Thursday) for a Monday-to-Friday, 8-to-5 employee. */
    private static function fortnight(string $basis): array
    {
        return AttendanceInputs::compute([
            'period' => ['start' => '2026-10-01', 'end' => '2026-10-15'], 'payBasis' => $basis, 'calendar' => Holidays::calendar(2026),
            'workWeek' => self::WEEK, 'shift' => self::SHIFT, 'today' => '2026-10-15',
            'records' => [
                self::day('2026-10-01'),
                self::day('2026-10-02', '08:15'),                              // 15 minutes late
                // 10-05 no record: absent; 10-06 no record: on leave
                self::day('2026-10-07', out2: '16:00'),                        // left an hour early
                self::day('2026-10-08', otIn: '17:00', otOut: '20:00'),        // 3 hours of overtime, 2 approved
                // 10-09 no record: a holiday in the HR calendar
                self::day('2026-10-10'),                                       // Saturday, approved
                self::day('2026-10-11'),                                       // Sunday, not approved
                self::day('2026-10-12'), self::day('2026-10-13'), self::day('2026-10-14'), self::day('2026-10-15'),
            ],
            'leaveDates' => ['2026-10-06'],
            'overtime' => ['2026-10-08' => 2, '2026-10-10' => 8],
            'hrHolidays' => ['2026-10-09'],
        ]);
    }

    public function test_a_fortnight_is_counted_the_way_the_hr_rules_read(): void
    {
        $out = self::fortnight('monthly');
        $s = $out['summary'];
        $this->assertSame(10, $s['scheduledDays']); // 11 weekdays less the HR holiday
        $this->assertSame(8, $s['daysPresent']);
        $this->assertSame(1, $s['absentDays']);
        $this->assertSame(1, $s['leaveDays']);
        $this->assertSame(1, $s['holidaysOff']);
        $this->assertSame(15, $s['lateMinutes']);
        $this->assertSame(60, $s['undertimeMinutes']);
        $this->assertEquals(2, $s['overtimeHours']);
        $this->assertEquals(8, $s['restDayHours']);
        $this->assertEquals(8, $s['unapprovedHours']);
        $this->assertSame(['lateMinutes' => 15, 'undertimeMinutes' => 60, 'absentDays' => 1], $out['input']['attendance']);
        $this->assertEquals([
            ['date' => '2026-10-08', 'overtimeHours' => 2],
            ['date' => '2026-10-10', 'isRestDay' => true, 'regularHours' => 8],
        ], $out['input']['workLogs']);
    }

    public function test_daily_and_hourly_staff_are_paid_for_days_and_hours_present_plus_leave(): void
    {
        $this->assertSame(9, self::fortnight('daily')['input']['attendance']['daysWorked']); // 8 present + 1 leave
        // 8 days present: six full days, 7.75 hours on the late day, 7 on the early one; plus 8 hours of leave
        $this->assertEquals(6 * 8 + 7.75 + 7 + 8, self::fortnight('hourly')['input']['attendance']['hoursWorked']);
    }

    public function test_night_hours_and_days_not_yet_reached(): void
    {
        $out = AttendanceInputs::compute([
            'period' => ['start' => '2026-10-12', 'end' => '2026-10-16'], 'payBasis' => 'monthly', 'calendar' => Holidays::calendar(2026),
            'workWeek' => self::WEEK, 'shift' => ['hours' => 8, 'start1' => '22:00:00', 'end1' => '02:00:00', 'start2' => '03:00:00', 'end2' => '07:00:00'],
            'today' => '2026-10-13',
            'records' => [[
                'date' => '2026-10-12', 'time_in_session1' => '2026-10-12 22:00:00', 'time_out_session1' => '2026-10-13 02:00:00',
                'time_in_session2' => '2026-10-13 03:00:00', 'time_out_session2' => '2026-10-13 07:00:00',
            ]],
        ]);
        $this->assertEquals(7, $out['summary']['nightHours']); // 10pm-2am and 3am-6am
        $this->assertSame(1, $out['summary']['absentDays']); // the 13th; the 14th to 16th have not happened yet
        $this->assertEquals([['date' => '2026-10-12', 'regularHours' => 8, 'nightHours' => 7]], $out['input']['workLogs']);
    }

    public function test_work_on_a_regular_holiday_is_logged_for_its_premium(): void
    {
        // Dec 25, 2026 is a regular holiday (Friday)
        $out = AttendanceInputs::compute([
            'period' => ['start' => '2026-12-21', 'end' => '2026-12-25'], 'payBasis' => 'daily', 'calendar' => Holidays::calendar(2026),
            'workWeek' => self::WEEK, 'shift' => self::SHIFT, 'today' => '2026-12-31',
            'records' => array_map(fn ($d) => self::day($d), ['2026-12-21', '2026-12-22', '2026-12-23', '2026-12-24', '2026-12-25']),
            'overtime' => ['2026-12-25' => 8],
        ]);
        // Dec 24 is a special non-working day (worked without approval: not counted) and Dec 25 a regular holiday
        // (worked with approval: logged for its premium; holiday pay itself comes with the payslip)
        $this->assertSame(['lateMinutes' => 0, 'undertimeMinutes' => 0, 'daysWorked' => 3], $out['input']['attendance']);
        $this->assertEquals([['date' => '2026-12-25', 'regularHours' => 8]], $out['input']['workLogs']);
        $this->assertEquals(8, $out['summary']['holidayHours']);
        $this->assertEquals(8, $out['summary']['unapprovedHours']);
    }
}

<?php

namespace App\Payroll;

/**
 * Turns one employee's time records for a pay period into what the payslip needs: days or hours worked, absences,
 * late and undertime minutes, and work logs for the premium-paid hours (rest days, holidays, approved overtime, night
 * work). Pure: the caller supplies the records, schedule, leaves, approved overtime and holidays.
 *
 * Rules, matching how the HR side already treats time:
 * - A scheduled work day with no time record is an absence, unless it is a holiday (in either calendar) or approved leave.
 * - Work on a rest day or holiday, and hours past the shift, count only when overtime was approved for that date.
 * - Late is clock-in after each session's start; undertime is the rest of the shortfall against the shift's hours.
 * - Night hours are the worked hours between 10pm and 6am.
 * - Days after $today are not counted as absences, so a period can be computed before it ends.
 */
class AttendanceInputs
{
    private const DAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    /**
     * @param array $a period (start, end), payBasis, calendar (engine holiday calendar), workWeek (mon..sun => 1|0),
     *                 shift (start1, end1, start2, end2 as H:i:s, hours) or null, records (rows of tblattendancerecord),
     *                 leaveDates (Y-m-d list), overtime (Y-m-d => approved hours), hrHolidays (Y-m-d list), today (Y-m-d)
     * @return array{input: array, summary: array}
     */
    public static function compute(array $a): array
    {
        $shift = $a['shift'] ?? null;
        $shiftMinutes = (int) round((float) ($shift['hours'] ?? 8) * 60);
        $records = [];
        foreach ($a['records'] ?? [] as $r) {
            $records[substr($r['date'], 0, 10)] = $r;
        }
        $leave = array_flip($a['leaveDates'] ?? []);
        $hrHolidays = array_flip($a['hrHolidays'] ?? []);
        $overtime = $a['overtime'] ?? [];
        $today = $a['today'] ?? '9999-12-31';

        $s = ['scheduledDays' => 0, 'daysPresent' => 0, 'absentDays' => 0, 'leaveDays' => 0, 'holidaysOff' => 0,
            'lateMinutes' => 0, 'undertimeMinutes' => 0, 'regularHours' => 0, 'overtimeHours' => 0, 'restDayHours' => 0,
            'holidayHours' => 0, 'nightHours' => 0, 'unapprovedHours' => 0, 'missingSchedule' => $shift === null];
        $logs = [];

        for ($d = $a['period']['start']; $d <= $a['period']['end']; $d = Payslip::addDays($d, 1)) {
            $scheduled = (int) ($a['workWeek'][self::DAYS[(int) gmdate('w', strtotime("{$d}T00:00:00Z"))]] ?? 0) === 1;
            $engineHoliday = Holidays::type($d, $a['calendar']);
            $isHoliday = $engineHoliday !== null || isset($hrHolidays[$d]);
            $rec = $records[$d] ?? null;
            if ($scheduled && !$isHoliday) {
                $s['scheduledDays']++;
            }

            if (!$rec) {
                if (!$scheduled) {
                    continue;
                }
                if ($isHoliday) {
                    $s['holidaysOff']++;
                } elseif (isset($leave[$d])) {
                    $s['leaveDays']++;
                } elseif ($d <= $today) {
                    $s['absentDays']++;
                }
                continue;
            }

            $sessions = self::sessions($rec, ['in1' => 'time_in_session1', 'out1' => 'time_out_session1', 'in2' => 'time_in_session2', 'out2' => 'time_out_session2']);
            $workedMin = min(array_sum(array_map(fn ($x) => $x[1] - $x[0], $sessions)), $shiftMinutes);
            $nightMin = min(self::nightMinutes($sessions), $workedMin);
            $approved = isset($overtime[$d]) ? (float) $overtime[$d] : null;
            $otSessions = self::sessions($rec, ['in1' => 'time_in_overtime', 'out1' => 'time_out_overtime']);
            $otMin = $approved === null ? 0 : (int) min(array_sum(array_map(fn ($x) => $x[1] - $x[0], $otSessions)), round($approved * 60));
            $nightOtMin = min(self::nightMinutes($otSessions), $otMin);

            if ($scheduled && !$isHoliday) {
                // An ordinary work day: the base pay covers it; late, undertime and overtime adjust it.
                $s['daysPresent']++;
                $late = self::late($rec, $shift);
                $s['lateMinutes'] += $late;
                $s['undertimeMinutes'] += max(0, $shiftMinutes - $workedMin - $late);
                $s['regularHours'] += $workedMin / 60;
                if ($otMin || $nightMin) {
                    $logs[] = self::log($d, false, $nightMin ? $workedMin : 0, $nightMin, $otMin, $nightOtMin);
                }
            } elseif ($approved !== null) {
                // Rest day or holiday work, paid at the premium rate because it was approved.
                $s[$isHoliday ? 'holidayHours' : 'restDayHours'] += $workedMin / 60;
                $logs[] = self::log($d, !$scheduled, $workedMin, $nightMin, $otMin, $nightOtMin);
            } else {
                $s['unapprovedHours'] += ($workedMin + array_sum(array_map(fn ($x) => $x[1] - $x[0], $otSessions))) / 60;
                if ($scheduled) {
                    $s['holidaysOff']++;
                }
            }
            $s['overtimeHours'] += $otMin / 60;
            $s['nightHours'] += ($nightMin + $nightOtMin) / 60;
        }

        $attendance = ['lateMinutes' => $s['lateMinutes'], 'undertimeMinutes' => $s['undertimeMinutes']];
        match ($a['payBasis'] ?? 'monthly') {
            'daily' => $attendance['daysWorked'] = $s['daysPresent'] + $s['leaveDays'],
            'hourly' => $attendance['hoursWorked'] = Money::round2($s['regularHours'] + $s['leaveDays'] * $shiftMinutes / 60),
            default => $attendance['absentDays'] = $s['absentDays'],
        };
        foreach (['regularHours', 'overtimeHours', 'restDayHours', 'holidayHours', 'nightHours', 'unapprovedHours'] as $k) {
            $s[$k] = Money::round2($s[$k]);
        }
        return ['input' => ['attendance' => $attendance, 'workLogs' => $logs], 'summary' => $s];
    }

    private static function log(string $date, bool $restDay, int $regularMin, int $nightMin, int $otMin, int $nightOtMin): array
    {
        return array_filter([
            'date' => $date, 'isRestDay' => $restDay,
            'regularHours' => $regularMin / 60, 'nightHours' => $nightMin / 60,
            'overtimeHours' => $otMin / 60, 'nightOvertimeHours' => $nightOtMin / 60,
        ], fn ($v) => $v !== 0 && $v !== 0.0 && $v !== false);
    }

    /** A stored date-time as seconds, read as wall-clock time whatever the server's time zone. */
    private static function ts(string $dateTime): int
    {
        return strtotime(substr($dateTime, 0, 19) . ' UTC');
    }

    /** Clock-in/clock-out pairs as [start, end] minutes since the Unix epoch, ignoring incomplete pairs. */
    private static function sessions(array $rec, array $cols): array
    {
        $out = [];
        foreach ([['in1', 'out1'], ['in2', 'out2']] as [$in, $outKey]) {
            if (!isset($cols[$in])) {
                continue;
            }
            $from = $rec[$cols[$in]] ?? null;
            $to = $rec[$cols[$outKey]] ?? null;
            if ($from && $to && self::ts($to) > self::ts($from)) {
                $out[] = [intdiv(self::ts($from), 60), intdiv(self::ts($to), 60)];
            }
        }
        return $out;
    }

    /** Minutes of the sessions that fall between 10pm and 6am. */
    private static function nightMinutes(array $sessions): int
    {
        $n = 0;
        foreach ($sessions as [$from, $to]) {
            for ($m = $from; $m < $to; $m++) {
                $hour = intdiv(($m % 1440 + 1440) % 1440, 60);
                if ($hour >= 22 || $hour < 6) {
                    $n++;
                }
            }
        }
        return $n;
    }

    /** Minutes clocked in after each session's start time. */
    private static function late(array $rec, ?array $shift): int
    {
        if (!$shift) {
            return 0;
        }
        $late = 0;
        foreach ([['time_in_session1', 'start1'], ['time_in_session2', 'start2']] as [$col, $start]) {
            $in = $rec[$col] ?? null;
            if ($in && !empty($shift[$start])) {
                $due = self::ts(substr($in, 0, 10) . ' ' . $shift[$start]);
                $late += max(0, intdiv(self::ts($in) - $due, 60));
            }
        }
        return $late;
    }
}

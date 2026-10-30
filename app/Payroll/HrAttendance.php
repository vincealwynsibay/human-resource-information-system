<?php

namespace App\Payroll;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads the HR side's time data (tblattendancerecord, tblworkweek, tblworkshift, tblleave, tblovertime, tblholiday)
 * for a pay period and turns it into payslip inputs with AttendanceInputs. A pay profile's employee id is the
 * tblemployee id.
 */
class HrAttendance
{
    public static function available(): bool
    {
        return Schema::hasTable('tblattendancerecord') && Schema::hasTable('tblworkweek');
    }

    /**
     * @param array $profiles engine profiles (id = tblemployee id, payBasis)
     * @return array<string, array{input: array, summary: array}> by employee id
     */
    public static function forPeriod(array $profiles, array $period, array $calendar, ?string $today = null): array
    {
        if (!$profiles || !self::available()) {
            return [];
        }
        $ids = array_map(fn ($p) => (int) $p['id'], $profiles);
        $from = $period['start'];
        $to = $period['end'];

        $week = (array) (DB::table('tblworkweek')->orderByDesc('id')->first() ?? []);
        $shifts = DB::table('tblemployeeworkshift as ews')->join('tblworkshift as ws', 'ws.id', '=', 'ews.WorkShiftID')
            ->whereIn('ews.EmployeeID', $ids)->orderBy('ews.id')
            ->get(['ews.EmployeeID', 'ws.hours_per_day', 'ws.start_time1', 'ws.end_time1', 'ws.start_time2', 'ws.end_time2'])
            ->keyBy('EmployeeID'); // the latest assignment wins
        $records = DB::table('tblattendancerecord')->whereIn('EmployeeID', $ids)->whereBetween('date', [$from, $to])->get()->groupBy('EmployeeID');
        $leaves = DB::table('tblleave')->whereIn('EmployeeID', $ids)->where('status', 'APPROVED')
            ->where('start_date', '<=', "{$to} 23:59:59")->where('end_date', '>=', $from)->get()->groupBy('EmployeeID');
        $overtime = DB::table('tblovertime')->whereIn('EmployeeID', $ids)->where('status', 'APPROVED')
            ->whereBetween('requested_date', [$from, "{$to} 23:59:59"])->get()->groupBy('EmployeeID');
        $hrHolidays = self::holidays($from, $to);

        $out = [];
        foreach ($profiles as $p) {
            $id = (int) $p['id'];
            $shift = $shifts[$id] ?? null;
            $leaveDates = [];
            foreach ($leaves[$id] ?? [] as $l) {
                for ($d = max(substr($l->start_date, 0, 10), $from); $d <= min(substr($l->end_date, 0, 10), $to); $d = Payslip::addDays($d, 1)) {
                    $leaveDates[] = $d;
                }
            }
            $ot = [];
            foreach ($overtime[$id] ?? [] as $o) {
                $d = substr($o->requested_date, 0, 10);
                $ot[$d] = ($ot[$d] ?? 0) + (float) $o->duration;
            }
            $out[(string) $p['id']] = AttendanceInputs::compute([
                'period' => $period, 'payBasis' => $p['payBasis'] ?? 'monthly', 'calendar' => $calendar,
                'workWeek' => $week,
                'shift' => $shift ? ['hours' => (float) $shift->hours_per_day, 'start1' => $shift->start_time1, 'end1' => $shift->end_time1, 'start2' => $shift->start_time2, 'end2' => $shift->end_time2] : null,
                'records' => array_map(fn ($r) => (array) $r, ($records[$id] ?? collect())->all()),
                'leaveDates' => $leaveDates, 'overtime' => $ot, 'hrHolidays' => $hrHolidays,
                'today' => $today ?? now('Asia/Manila')->format('Y-m-d'),
            ]);
        }
        return $out;
    }

    /** Holiday dates from tblholiday within the period, recurring ones placed in the period's years. */
    private static function holidays(string $from, string $to): array
    {
        $dates = [];
        foreach (DB::table('tblholiday')->get(['date', 'is_recurring']) as $h) {
            $date = substr($h->date, 0, 10);
            if ($h->is_recurring) {
                foreach (range((int) substr($from, 0, 4), (int) substr($to, 0, 4)) as $y) {
                    $dates[] = $y . substr($date, 4);
                }
            } else {
                $dates[] = $date;
            }
        }
        return array_values(array_filter(array_unique($dates), fn ($d) => $d >= $from && $d <= $to));
    }
}

<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollRun;
use App\Payroll\Holidays;
use App\Payroll\PayrollService;
use App\Payroll\Payslip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Everything the admin overview shows, in one request. */
class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $today = now('Asia/Manila')->format('Y-m-d');
        $year = (int) ($request->query('year') ?: substr($today, 0, 4));
        $hr = Schema::hasTable('tblemployee');

        return response()->json([
            'today' => $today,
            'year' => $year,
            'payrollByMonth' => $this->payrollByMonth($year),
            'runs' => [
                'waiting' => PayrollRun::whereIn('status', PayrollService::OPEN)->withCount('payslips')->orderBy('period_start')->get(['id', 'kind', 'status', 'period_start', 'period_end', 'total_net', 'created_by', 'submitted_by']),
                'lastPaid' => PayrollRun::where('status', PayrollRun::PAID)->withCount('payslips')->orderByDesc('period_end')->first(),
            ],
            'people' => $hr ? $this->people() : null,
            'attendanceToday' => $hr ? $this->attendanceToday($today) : null,
            'pending' => $hr ? [
                'leave' => DB::table('tblleave')->where('status', 'PENDING')->count(),
                'overtime' => DB::table('tblovertime')->where('status', 'PENDING')->count(),
            ] : null,
            'upcoming' => $this->upcoming($today, $hr),
            // Leavers keep their pay profiles for the year-end filings; "on payroll" counts the people still employed.
            'profiles' => ['count' => $hr
                ? DB::table('payroll_profiles')->whereIn('employee_id', $this->employed($today)->pluck('id')->map(fn ($id) => (string) $id))->count()
                : DB::table('payroll_profiles')->count()],
        ]);
    }

    /** What waits on the signed-in administrator: requests to decide and runs to move on. */
    public function inbox(Request $request)
    {
        $userId = $request->user()->id;
        $items = [];
        if (Schema::hasTable('tblleave')) {
            $items[] = ['kind' => 'leave', 'label' => 'Leave requests to decide', 'count' => DB::table('tblleave')->where('status', 'PENDING')->count(), 'href' => '/leave'];
            $items[] = ['kind' => 'overtime', 'label' => 'Overtime requests to decide', 'count' => DB::table('tblovertime')->where('status', 'PENDING')->count(), 'href' => '/overtime'];
        }
        $awaiting = PayrollRun::where('status', PayrollRun::PENDING_APPROVAL)->get();
        $items[] = ['kind' => 'approve', 'label' => 'Payroll runs to approve', 'count' => $awaiting->filter(fn ($r) => PayrollService::mayApprove($r, $userId))->count(), 'href' => '/payroll/runs?status=PENDING_APPROVAL'];
        $items[] = ['kind' => 'pay', 'label' => 'Approved runs to pay', 'count' => PayrollRun::where('status', PayrollRun::APPROVED)->count(), 'href' => '/payroll/runs?status=APPROVED'];
        $items[] = ['kind' => 'draft', 'label' => 'Draft runs to submit', 'count' => PayrollRun::where('status', PayrollRun::DRAFT)->count(), 'href' => '/payroll/runs?status=DRAFT'];
        return response()->json(['items' => array_values(array_filter($items, fn ($i) => $i['count'] > 0)), 'total' => array_sum(array_column($items, 'count'))]);
    }

    /** Gross, net, employee deductions and employer share by month of the period start, runs not cancelled. */
    private function payrollByMonth(int $year): array
    {
        $rows = PayrollRun::where('status', '!=', PayrollRun::CANCELLED)
            ->whereBetween('period_start', ["{$year}-01-01", "{$year}-12-31"])
            ->get(['period_start', 'status', 'total_gross', 'total_net', 'total_deductions', 'total_employer_cost']);
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = ['month' => $m, 'gross' => 0, 'net' => 0, 'deductions' => 0, 'employer' => 0, 'unpaid' => 0];
        }
        foreach ($rows as $r) {
            $m = (int) substr($r->period_start, 5, 2);
            $months[$m]['gross'] += $r->total_gross;
            $months[$m]['net'] += $r->total_net;
            $months[$m]['deductions'] += $r->total_deductions;
            $months[$m]['employer'] += $r->total_employer_cost;
            if ($r->status !== PayrollRun::PAID) {
                $months[$m]['unpaid']++;
            }
        }
        return array_map(fn ($x) => [...$x, 'gross' => round($x['gross'], 2), 'net' => round($x['net'], 2), 'deductions' => round($x['deductions'], 2), 'employer' => round($x['employer'], 2)], array_values($months));
    }

    private function people(): array
    {
        $byDept = DB::table('tblemployee as e')->leftJoin('tbldepartment as d', 'd.id', '=', 'e.DepartmentID')
            ->whereNull('e.termination_date')
            ->selectRaw("COALESCE(d.name, 'No department') as name, COUNT(*) as count")->groupBy('name')->orderByDesc('count')->get();
        return ['headcount' => (int) $byDept->sum('count'), 'byDepartment' => $byDept];
    }

    /** Employees not separated as of a date. */
    private function employed(string $date)
    {
        return DB::table('tblemployee')->where(fn ($q) => $q->whereNull('termination_date')->orWhere('termination_date', '>=', $date));
    }

    /** Present, late, on leave and absent today, for employees still employed with a work shift; absent only once the shift has started. */
    private function attendanceToday(string $today): array
    {
        $week = (array) (DB::table('tblworkweek')->orderByDesc('id')->first() ?? []);
        $dow = strtolower(date('D', strtotime($today)));
        $isWorkDay = (int) ($week[$dow] ?? 0) === 1 && Holidays::type($today, Holidays::calendar((int) substr($today, 0, 4))) === null;
        $shifts = DB::table('tblemployeeworkshift as ews')->join('tblworkshift as ws', 'ws.id', '=', 'ews.WorkShiftID')
            ->whereIn('ews.EmployeeID', $this->employed($today)->select('id'))
            ->orderBy('ews.id')->get(['ews.EmployeeID', 'ws.start_time1'])->keyBy('EmployeeID');
        $records = DB::table('tblattendancerecord')->where('date', $today)->get()->keyBy('EmployeeID');
        $onLeave = DB::table('tblleave')->where('status', 'APPROVED')->where('start_date', '<=', "{$today} 23:59:59")->where('end_date', '>=', $today)->pluck('EmployeeID')->flip();
        $now = now('Asia/Manila')->format('H:i:s');

        $out = ['workDay' => $isWorkDay, 'scheduled' => 0, 'present' => 0, 'late' => 0, 'onLeave' => 0, 'absent' => 0, 'notYet' => 0];
        if (!$isWorkDay) {
            return $out;
        }
        foreach ($shifts as $employeeId => $s) {
            $out['scheduled']++;
            $r = $records[$employeeId] ?? null;
            if ($r && $r->time_in_session1) {
                $late = $s->start_time1 && substr($r->time_in_session1, 11) > $s->start_time1;
                $out[$late ? 'late' : 'present']++;
            } elseif (isset($onLeave[$employeeId])) {
                $out['onLeave']++;
            } elseif ($s->start_time1 && $now < $s->start_time1) {
                $out['notYet']++;
            } else {
                $out['absent']++;
            }
        }
        return $out;
    }

    /** Birthdays and work anniversaries in the next 30 days, holidays in the next 60, and the next pay date. */
    private function upcoming(string $today, bool $hr): array
    {
        $events = [];
        $until30 = Payslip::addDays($today, 30);
        if ($hr) {
            foreach (DB::table('tblemployee')->whereNull('termination_date')->get(['id', 'firstname', 'lastname', 'birth_date', 'joined_date']) as $e) {
                foreach ([['birth_date', 'birthday'], ['joined_date', 'anniversary']] as [$col, $kind]) {
                    if (!$e->$col) {
                        continue;
                    }
                    $md = substr($e->$col, 5, 5);
                    $next = substr($today, 0, 4) . "-{$md}";
                    if ($next < $today) {
                        $next = ((int) substr($today, 0, 4) + 1) . "-{$md}";
                    }
                    $years = (int) substr($next, 0, 4) - (int) substr($e->$col, 0, 4);
                    if ($next <= $until30 && ($kind === 'birthday' || $years >= 1)) {
                        $events[] = ['date' => $next, 'kind' => $kind, 'employeeId' => $e->id, 'name' => "{$e->firstname} {$e->lastname}", 'years' => $years];
                    }
                }
            }
        }
        $year = (int) substr($today, 0, 4);
        $until60 = Payslip::addDays($today, 60);
        foreach ([$year, $year + 1] as $y) {
            foreach (Holidays::calendar($y)['holidays'] as $h) {
                if ($h['date'] >= $today && $h['date'] <= $until60) {
                    $events[] = ['date' => $h['date'], 'kind' => 'holiday', 'name' => $h['name'] ?? 'Holiday', 'type' => $h['type']];
                }
            }
        }
        $day = (int) substr($today, 8, 2);
        $payDay = $day <= 15 ? substr($today, 0, 8) . '15' : date('Y-m-t', strtotime($today));
        $events[] = ['date' => $payDay, 'kind' => 'payday', 'name' => 'Pay day'];
        usort($events, fn ($a, $b) => strcmp($a['date'], $b['date']));
        return array_slice($events, 0, 12);
    }
}

<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollPayslip;
use App\Models\Payroll\PayrollRun;
use App\Models\User;
use App\Payroll\PayrollService;
use Illuminate\Http\Request;

class RunController extends Controller
{
    use Respond;

    private const PERIOD_RULES = [
        'periodStart' => 'required|date_format:Y-m-d',
        'periodEnd' => 'required|date_format:Y-m-d',
        'frequency' => 'required|in:MONTHLY,SEMI_MONTHLY,WEEKLY',
        'payDate' => 'nullable|date_format:Y-m-d',
        'schedule' => 'nullable|in:split,second_cutoff',
        'employeeIds' => 'nullable|array',
        'employeeIds.*' => 'string',
        'inputs' => 'nullable|array',
        'defaults' => 'nullable|array',
        'notes' => 'nullable|string|max:2000',
        'useAttendance' => 'nullable|boolean',
    ];

    /** Runs, newest period first, filtered by status, kind, frequency and period, a page at a time. */
    public function index(Request $request)
    {
        $f = $request->validate([
            'status' => 'nullable|in:DRAFT,PENDING_APPROVAL,APPROVED,PAID,CANCELLED',
            'kind' => 'nullable|in:' . implode(',', PayrollRun::KINDS),
            'frequency' => 'nullable|string',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);
        $q = PayrollRun::query()
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['kind'] ?? null, fn ($q, $v) => $q->where('kind', $v))
            ->when($f['frequency'] ?? null, fn ($q, $v) => $q->where('frequency', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('period_end', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('period_start', '<=', $v));
        $page = (int) ($f['page'] ?? 1);
        $limit = (int) ($f['limit'] ?? 20);
        $total = (clone $q)->count();
        $items = $q->withCount('payslips')->orderByDesc('period_start')->orderByDesc('created_at')->forPage($page, $limit)->get();
        return response()->json(['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    public function show(Request $request, PayrollRun $run)
    {
        return response()->json($this->withPeople($run->load('payslips'), $request));
    }

    /** The run with who prepared, submitted and approved it, and whether the person asking may approve it. */
    private function withPeople(PayrollRun $run, Request $request): array
    {
        $ids = array_filter([$run->created_by, $run->submitted_by, $run->approved_by]);
        $names = $ids ? User::whereIn('id', $ids)->pluck('username', 'id') : collect();
        return [
            ...$run->toArray(),
            'created_by_name' => $names[$run->created_by] ?? null,
            'submitted_by_name' => $names[$run->submitted_by] ?? null,
            'approved_by_name' => $names[$run->approved_by] ?? null,
            'can_approve' => PayrollService::mayApprove($run, $request->user()?->id),
        ];
    }

    /** One line per employee: gross, deductions, net and the employer's share, without the itemised breakdown. */
    public function summary(PayrollRun $run)
    {
        return response()->json([
            'run' => $run->only(['id', 'kind', 'status', 'period_start', 'period_end', 'pay_date', 'frequency', 'total_gross', 'total_deductions', 'total_net', 'total_employer_cost']),
            'employees' => $run->payslips->map(fn ($s) => [
                'employeeId' => $s->employee_id, 'name' => $s->name, 'gross' => $s->gross, 'deductions' => $s->total_deductions,
                'net' => $s->net, 'employerCost' => $s->data['employer']['total'] ?? 0, 'notes' => $s->data['notes'] ?? [],
            ]),
        ]);
    }

    /** Compute a period without saving it, to check the figures before creating the run. */
    public function preview(Request $request)
    {
        $data = $request->validate(self::PERIOD_RULES);
        return $this->attempt(fn () => PayrollService::computeRegular(
            PayrollService::profiles($data['employeeIds'] ?? null),
            ['start' => $data['periodStart'], 'end' => $data['periodEnd'], 'frequency' => $data['frequency']],
            $data['schedule'] ?? 'split', $data['inputs'] ?? [], $data['defaults'] ?? null, null, (bool) ($data['useAttendance'] ?? false),
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate(self::PERIOD_RULES);
        return $this->attempt(fn () => PayrollService::createRun($data, $request->user()?->id), 201);
    }

    public function recompute(Request $request, PayrollRun $run)
    {
        return $this->attempt(fn () => $this->withPeople(PayrollService::recompute($run), $request));
    }

    /** Replace one employee's manual allowances and other deductions on a draft; the run is recomputed. */
    public function adjust(Request $request, PayrollRun $run, string $employeeId)
    {
        $data = $request->validate(['allowances' => 'present|array', 'otherDeductions' => 'present|array']);
        return $this->attempt(fn () => $this->withPeople(PayrollService::adjust($run, $employeeId, $data['allowances'], $data['otherDeductions']), $request));
    }

    public function submit(Request $request, PayrollRun $run)
    {
        return $this->attempt(fn () => $this->withPeople(PayrollService::submit($run, $request->user()?->id), $request));
    }

    public function approve(Request $request, PayrollRun $run)
    {
        return $this->attempt(fn () => $this->withPeople(PayrollService::approve($run, $request->user()?->id), $request));
    }

    public function pay(Request $request, PayrollRun $run)
    {
        return $this->attempt(fn () => $this->withPeople(PayrollService::pay($run), $request));
    }

    public function cancel(Request $request, PayrollRun $run)
    {
        return $this->attempt(fn () => $this->withPeople(PayrollService::cancel($run), $request));
    }

    /** An employee's own payslips from approved and paid runs. In this app the employee id is the user's id. */
    public function mine(Request $request)
    {
        $slips = PayrollPayslip::with('run:id,kind,status,period_start,period_end,pay_date,frequency')
            ->where('employee_id', (string) ($request->user()->EmployeeID ?? $request->user()->id))
            ->whereHas('run', fn ($q) => $q->whereIn('status', [PayrollRun::APPROVED, PayrollRun::PAID]))
            ->orderByDesc('id')->get();
        return response()->json(['items' => $slips]);
    }
}

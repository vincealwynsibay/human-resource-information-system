{{-- A month's SSS, PhilHealth and Pag-IBIG contributions, one agency per page. $company, $r (App\Payroll\Filings\Remittances::forMonth). --}}
@php
    $peso = fn ($v) => $v === null ? '—' : number_format((float) $v, 2);
    $month = date('F Y', mktime(0, 0, 0, $r['month'], 1, $r['year']));
    $titles = App\Payroll\Filings\Remittances::TITLES;
    $numberLabel = ['sss' => 'SS number', 'philhealth' => 'PhilHealth no.', 'pagibig' => 'Pag-IBIG MID'];
    $person = fn ($row) => $row['lastName'] ? trim("{$row['lastName']}, {$row['firstName']} {$row['middleName']}") : $row['name'];
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { size: A4 landscape; margin: 14mm 14mm; }
    body { margin: 0; font: 9pt/1.4 "Segoe UI", "Helvetica Neue", Arial, sans-serif; color: #1b2420; }
    .agency { page-break-after: always; }
    .agency:last-child { page-break-after: auto; }
    header { border-bottom: 2px solid #1b2420; padding-bottom: 6px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: flex-end; }
    .company { font-size: 13pt; font-weight: 700; }
    .title { font-size: 11pt; font-weight: 600; text-align: right; }
    .muted { color: #5d6a64; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 3px 0 3px 8px; border-bottom: 1px solid #e2e5e0; text-align: left; }
    td:first-child, th:first-child { padding-left: 0; }
    th { font-size: 7.5pt; text-transform: uppercase; letter-spacing: .04em; color: #5d6a64; font-weight: 600; }
    .amt { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    tr.total td { border-top: 1.5px solid #1b2420; border-bottom: none; font-weight: 600; }
    .warn { background: #fff3d6; color: #8a5a00; padding: 6px 8px; border-radius: 5px; margin-bottom: 8px; }
    footer { margin-top: 12px; font-size: 7.5pt; color: #8a958f; }
</style>
</head>
<body>
@foreach (App\Payroll\Filings\Remittances::AGENCIES as $agency)
    @php($a = $r[$agency])
    <section class="agency">
        <header>
            <div><div class="company">{{ $company }}</div><div class="muted">Contributions for {{ $month }} · {{ count($a['rows']) }} employees</div></div>
            <div class="title">{{ $titles[$agency] }}<br><span class="muted" style="font-weight:400">{{ $month }}</span></div>
        </header>
        @if ($r['openRuns'] > 0)
            <div class="warn">{{ $r['openRuns'] }} {{ $r['openRuns'] === 1 ? 'run' : 'runs' }} for {{ $month }} {{ $r['openRuns'] === 1 ? 'is' : 'are' }} not approved yet and left out.</div>
        @endif
        @if ($a['missingNumbers'])
            <div class="warn">No {{ $numberLabel[$agency] }} on file for {{ implode(', ', $a['missingNumbers']) }}.</div>
        @endif
        <table>
            <tr>
                <th>{{ $numberLabel[$agency] }}</th><th>Name</th>
                @if ($agency === 'sss')
                    <th class="amt">Salary credit</th><th class="amt">Employee</th><th class="amt">Employer</th><th class="amt">EC</th><th class="amt">MPF (EE / ER)</th>
                @else
                    <th class="amt">{{ $agency === 'philhealth' ? 'Monthly basic' : 'Compensation (capped)' }}</th><th class="amt">{{ $agency === 'philhealth' ? 'Personal share' : 'Employee' }}</th><th class="amt">Employer</th>
                @endif
                <th class="amt">Total</th>
            </tr>
            @forelse ($a['rows'] as $row)
                <tr>
                    <td>{{ $row['number'] ?? '—' }}</td><td>{{ $person($row) }}</td>
                    @if ($agency === 'sss')
                        <td class="amt">{{ $peso($row['msc']) }}</td><td class="amt">{{ $peso($row['employee']) }}</td><td class="amt">{{ $peso($row['employer']) }}</td><td class="amt">{{ $peso($row['ec']) }}</td>
                        <td class="amt">{{ $row['employeeMpf'] > 0 ? $peso($row['employeeMpf']) . ' / ' . $peso($row['employerMpf']) : '—' }}</td>
                    @else
                        <td class="amt">{{ $peso($row['base']) }}</td><td class="amt">{{ $peso($row['employee']) }}</td><td class="amt">{{ $peso($row['employer']) }}</td>
                    @endif
                    <td class="amt">{{ $peso($row['total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">No approved or paid runs for {{ $month }}.</td></tr>
            @endforelse
            @if ($a['rows'])
                <tr class="total">
                    <td></td><td>Total to remit</td>
                    @if ($agency === 'sss')
                        <td></td><td class="amt">{{ $peso($a['totals']['employee']) }}</td><td class="amt">{{ $peso($a['totals']['employer']) }}</td><td class="amt">{{ $peso($a['totals']['ec']) }}</td><td></td>
                    @else
                        <td></td><td class="amt">{{ $peso($a['totals']['employee']) }}</td><td class="amt">{{ $peso($a['totals']['employer']) }}</td>
                    @endif
                    <td class="amt">{{ $peso($a['totals']['total']) }}</td>
                </tr>
            @endif
        </table>
        <footer>The contributions deducted on approved payslips for {{ $month }}, for entering in the agency's portal. Not the agency's own form or upload file. Talia</footer>
    </section>
@endforeach
</body>
</html>

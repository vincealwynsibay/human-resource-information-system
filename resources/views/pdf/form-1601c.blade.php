{{-- BIR Form 1601-C worksheet for one month. $company, $w (App\Payroll\Filings\Form1601C::forMonth). --}}
@php
    $peso = fn ($v) => $v < 0 ? '(' . number_format(-$v, 2) . ')' : number_format((float) $v, 2);
    $month = date('F Y', mktime(0, 0, 0, $w['month'], 1, $w['year']));
    $kinds = ['regular' => 'Regular', 'thirteenth' => '13th month', 'final_pay' => 'Final pay', 'year_end_adjustment' => 'Year-end adjustment'];
    $strong = [21, 22, 24, 25, 27];
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { size: A4; margin: 18mm 16mm; }
    body { margin: 0; font: 10pt/1.45 "Segoe UI", "Helvetica Neue", Arial, sans-serif; color: #1b2420; }
    header { border-bottom: 2px solid #1b2420; padding-bottom: 8px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: flex-end; }
    .company { font-size: 14pt; font-weight: 700; }
    .title { font-size: 12pt; font-weight: 600; text-align: right; }
    .muted { color: #5d6a64; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 5px 0; border-bottom: 1px solid #e2e5e0; text-align: left; vertical-align: top; }
    th { font-size: 8.5pt; text-transform: uppercase; letter-spacing: .05em; color: #5d6a64; font-weight: 600; }
    .no { width: 34px; color: #5d6a64; }
    .amt { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; padding-left: 12px; }
    tr.strong td { font-weight: 600; }
    h2 { font-size: 9pt; text-transform: uppercase; letter-spacing: .06em; color: #5d6a64; margin: 22px 0 6px; }
    .warn { background: #fff3d6; color: #8a5a00; padding: 8px 10px; border-radius: 6px; margin-bottom: 12px; }
    .notes { margin-top: 14px; font-size: 9pt; color: #5d6a64; }
    .notes p { margin: 2px 0; }
    footer { margin-top: 24px; font-size: 8pt; color: #8a958f; }
</style>
</head>
<body>
<header>
    <div>
        <div class="company">{{ $company }}</div>
        <div class="muted">{{ $w['employees'] }} employees · due {{ date('F j, Y', strtotime($w['dueDate'])) }}</div>
    </div>
    <div class="title">BIR Form 1601-C worksheet<br><span class="muted" style="font-weight:400">{{ $month }}</span></div>
</header>

@if ($w['openRuns'] > 0)
    <div class="warn">{{ $w['openRuns'] }} {{ $w['openRuns'] === 1 ? 'run' : 'runs' }} paying in {{ $month }} {{ $w['openRuns'] === 1 ? 'is' : 'are' }} not approved yet and {{ $w['openRuns'] === 1 ? 'is' : 'are' }} left out of these figures.</div>
@endif

<table>
    <tr><th class="no">Item</th><th>Computation of tax</th><th class="amt">Amount</th></tr>
    @foreach ($w['items'] as $i)
        <tr class="{{ in_array($i['item'], $strong, true) ? 'strong' : '' }}"><td class="no">{{ $i['item'] }}</td><td>{{ $i['label'] }}</td><td class="amt">{{ $peso($i['amount']) }}</td></tr>
    @endforeach
</table>

<h2>Runs included</h2>
<table>
    <tr><th>Run</th><th>Period</th><th>Paid</th><th class="amt">Employees</th><th class="amt">Tax withheld</th></tr>
    @forelse ($w['runs'] as $r)
        <tr><td>{{ $kinds[$r['kind']] ?? $r['kind'] }}</td><td>{{ $r['period_start'] }} to {{ $r['period_end'] }}</td><td>{{ $r['pay_date'] }}</td><td class="amt">{{ $r['employees'] }}</td><td class="amt">{{ $peso($r['taxWithheld']) }}</td></tr>
    @empty
        <tr><td colspan="5" class="muted">No approved or paid runs this month.</td></tr>
    @endforelse
</table>

<div class="notes">@foreach ($w['notes'] as $note)<p>{{ $note }}</p>@endforeach</div>
<footer>A worksheet for filling in BIR Form 1601-C (January 2018 ENCS) in eBIRForms or eFPS, not the return itself. Talia</footer>
</body>
</html>

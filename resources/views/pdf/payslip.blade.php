{{-- One payslip per page. $company, and $payslips: each with 'slip' (the stored payslip), 'run' and 'placement'. --}}
@php
    $peso = fn ($v) => number_format((float) $v, 2);
    $titles = ['regular' => 'Payslip', 'thirteenth' => '13th-month pay', 'final_pay' => 'Final pay', 'year_end_adjustment' => 'Year-end tax adjustment'];
    $period = function (array $p) {
        $s = strtotime($p['start']);
        $e = strtotime($p['end']);
        if ($p['start'] === $p['end']) {
            return date('F j, Y', $s);
        }
        return date('Y-m', $s) === date('Y-m', $e) ? date('F j', $s) . '–' . date('j, Y', $e) : date('F j, Y', $s) . ' – ' . date('F j, Y', $e);
    };
@endphp
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { size: A4; margin: 18mm 16mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font: 10.5pt/1.45 "Segoe UI", "Helvetica Neue", Arial, sans-serif; color: #1b2420; }
    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }
    header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #1b2420; padding-bottom: 8px; }
    .company { font-size: 15pt; font-weight: 700; }
    .doc { text-align: right; }
    .doc .title { font-size: 12pt; font-weight: 600; }
    .muted { color: #5d6a64; }
    .who { display: flex; justify-content: space-between; margin: 14px 0 18px; }
    .who .name { font-size: 12pt; font-weight: 600; }
    .cols { display: flex; gap: 24px; }
    .cols > section { flex: 1; }
    h2 { font-size: 9pt; text-transform: uppercase; letter-spacing: .06em; color: #5d6a64; margin: 0 0 6px; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 4px 0; border-bottom: 1px solid #e2e5e0; vertical-align: top; }
    td.amt { text-align: right; white-space: nowrap; padding-left: 12px; font-variant-numeric: tabular-nums; }
    tr.total td { border-bottom: none; border-top: 1.5px solid #1b2420; font-weight: 600; }
    .net { margin-top: 18px; display: flex; justify-content: space-between; align-items: baseline; background: #f0f2ef; padding: 12px 14px; border-radius: 6px; }
    .net .label { font-weight: 600; }
    .net .value { font-size: 16pt; font-weight: 700; font-variant-numeric: tabular-nums; }
    .employer { margin-top: 18px; }
    .notes { margin-top: 14px; font-size: 9pt; color: #5d6a64; }
    .notes p { margin: 2px 0; }
    footer { margin-top: 26px; font-size: 8pt; color: #8a958f; display: flex; justify-content: space-between; }
</style>
</head>
<body>
@foreach ($payslips as $p)
    @php($s = $p['slip'])
    <div class="page">
        <header>
            <div class="company">{{ $company }}</div>
            <div class="doc">
                <div class="title">{{ $titles[$p['run']['kind']] ?? 'Payslip' }}</div>
                <div class="muted">{{ $period($s['period']) }} · paid {{ date('F j, Y', strtotime($p['run']['pay_date'])) }}</div>
                @if (!in_array($p['run']['status'], ['APPROVED', 'PAID'], true))
                    <div style="color:#a32020;font-weight:600">Not final: this run is {{ strtolower(str_replace('_', ' ', $p['run']['status'])) }}</div>
                @endif
            </div>
        </header>

        <div class="who">
            <div>
                <div class="name">{{ $s['name'] }}</div>
                <div class="muted">{{ implode(' · ', array_filter([$p['placement']['jobTitle'] ?? null, $p['placement']['department'] ?? null])) }}</div>
            </div>
            <div class="muted" style="text-align:right">
                Employee ID {{ $s['employeeId'] }}
                @if (!empty($s['rates']['daily']))<br>Daily rate {{ $peso($s['rates']['daily']) }}@endif
            </div>
        </div>

        <div class="cols">
            <section>
                <h2>Earnings</h2>
                <table>
                    @foreach ($s['earnings'] as $line)
                        <tr><td>{{ $line['label'] }}@if (isset($line['taxable']) && !$line['taxable'] && $line['code'] !== 'TAX_REFUND') <span class="muted">(non-taxable)</span>@endif</td><td class="amt">{{ $peso($line['amount']) }}</td></tr>
                    @endforeach
                    <tr class="total"><td>Gross pay</td><td class="amt">{{ $peso($s['gross']) }}</td></tr>
                </table>
            </section>
            <section>
                <h2>Deductions</h2>
                <table>
                    @forelse ($s['deductions'] as $line)
                        <tr><td>{{ $line['label'] }}</td><td class="amt">{{ $peso($line['amount']) }}</td></tr>
                    @empty
                        <tr><td class="muted">None</td><td class="amt"></td></tr>
                    @endforelse
                    <tr class="total"><td>Total deductions</td><td class="amt">{{ $peso($s['totalDeductions']) }}</td></tr>
                </table>
            </section>
        </div>

        <div class="net"><span class="label">Net pay</span><span class="value">₱{{ $peso($s['net']) }}</span></div>

        @if (!empty($s['employer']['total']))
            <section class="employer">
                <h2>Paid by your employer on top of your pay</h2>
                <table>
                    <tr><td>SSS (including EC {{ $peso($s['employer']['ec'] ?? 0) }})</td><td class="amt">{{ $peso(($s['employer']['sss'] ?? 0) + ($s['employer']['ec'] ?? 0)) }}</td></tr>
                    <tr><td>PhilHealth</td><td class="amt">{{ $peso($s['employer']['philhealth'] ?? 0) }}</td></tr>
                    <tr><td>Pag-IBIG</td><td class="amt">{{ $peso($s['employer']['pagibig'] ?? 0) }}</td></tr>
                </table>
            </section>
        @endif

        @if (!empty($s['notes']))
            <div class="notes">@foreach ($s['notes'] as $note)<p>{{ $note }}</p>@endforeach</div>
        @endif

        <footer><span>Computer-generated; no signature needed.</span><span>Talia</span></footer>
    </div>
@endforeach
</body>
</html>

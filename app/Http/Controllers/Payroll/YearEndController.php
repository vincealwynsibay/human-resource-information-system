<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Payroll\Alphalist\Checker;
use App\Payroll\PayrollService;
use App\Payroll\YearEnd;
use Illuminate\Http\Request;

class YearEndController extends Controller
{
    use Respond;

    private function year(int $year): int
    {
        abort_unless($year >= 2000 && $year <= 2100, 404);
        return $year;
    }

    private function csv(string $body, string $name)
    {
        return response($body, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename={$name}"]);
    }

    /** The annual compensation summary (the figures a BIR Form 2316 needs). ?format=csv downloads it. */
    public function annual(Request $request, int $year)
    {
        $rows = PayrollService::annualRows($this->year($year));
        if ($request->query('format') === 'csv') {
            return $this->csv(YearEnd::annualCsv($rows), "annual-{$year}.csv");
        }
        return response()->json(['year' => $year, 'unpaidRuns' => PayrollService::unpaidRunsOfYear($year), 'rows' => $rows]);
    }

    /** BIR Form 2316 (September 2021) items per employee. ?format=csv downloads it. */
    public function form2316(Request $request, int $year)
    {
        $rows = PayrollService::annualRows($this->year($year));
        if ($request->query('format') === 'csv') {
            return $this->csv(YearEnd::annual2316Csv($rows), "2316-{$year}.csv");
        }
        return response()->json(['year' => $year, 'items' => YearEnd::FORM_2316_ITEMS, 'rows' => array_map(YearEnd::annual2316Row(...), $rows)]);
    }

    public function thirteenth(Request $request, int $year)
    {
        $data = $request->validate(['earnedBasic' => 'nullable|array', 'otherBonuses' => 'nullable|array']);
        return $this->attempt(fn () => PayrollService::thirteenthRun($this->year($year), $data['earnedBasic'] ?? [], $data['otherBonuses'] ?? [], $request->user()?->id), 201);
    }

    public function adjustment(Request $request, int $year)
    {
        return $this->attempt(fn () => PayrollService::yearEndAdjustment($this->year($year), $request->user()?->id), 201);
    }

    public function finalPay(Request $request, string $employeeId)
    {
        $data = $request->validate([
            'separationDate' => 'required|date_format:Y-m-d',
            'cause' => 'required|string',
            'hireDate' => 'nullable|date_format:Y-m-d',
            'birthDate' => 'nullable|date_format:Y-m-d',
            'unpaidDays' => 'nullable|numeric|min:0',
            'unusedSilDays' => 'nullable|numeric|min:0',
            'earnedBasicYear' => 'nullable|numeric|min:0',
            'otherBonuses' => 'nullable|numeric|min:0',
            'firstTimeRetirement' => 'nullable|boolean',
            'clearance' => 'nullable|array',
        ]);
        return $this->attempt(fn () => PayrollService::finalPay($employeeId, $data, $request->user()?->id), 201);
    }

    private function alphalistFor(Request $request, int $year): array
    {
        $q = $request->validate(['employerTin' => 'required|string', 'branch' => 'nullable|string', 'regionNumber' => 'nullable|string']);
        return PayrollService::alphalist($this->year($year), ['tin' => $q['employerTin'], 'branch' => $q['branch'] ?? null], $q['regionNumber'] ?? null);
    }

    /** The BIR 1604-C alphalist for a year: its problems, counts and self-check, without the file. */
    public function alphalist(Request $request, int $year)
    {
        $built = $this->alphalistFor($request, $year);
        unset($built['content']);
        return response()->json([...$built, 'unpaidRuns' => PayrollService::unpaidRunsOfYear($year)]);
    }

    /** The .DAT file itself, under its official name. Refused while the file has errors. */
    public function alphalistDownload(Request $request, int $year)
    {
        $built = $this->alphalistFor($request, $year);
        if ($built['errors']) {
            return response()->json(['message' => 'The alphalist has errors; fix them before downloading.', 'errors' => $built['errors']], 422);
        }
        return response($built['content'], 200, ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => "attachment; filename={$built['fileName']}"]);
    }

    /** Check any 1604-C .DAT file (a `file` upload or `content` text) against the validator's rules. */
    public function check(Request $request)
    {
        $content = $request->hasFile('file') ? $request->file('file')->get() : (string) $request->input('content', '');
        return response()->json(Checker::check($content));
    }
}

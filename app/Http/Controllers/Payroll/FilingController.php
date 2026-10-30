<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Payroll\Filings\Form1601C;
use App\Payroll\Filings\Remittances;
use Spatie\LaravelPdf\Facades\Pdf;

/** Monthly government filings, as worksheets to copy into the agencies' own systems. */
class FilingController extends Controller
{
    public function form1601c(int $year, int $month)
    {
        return $this->invalidMonth($month) ?? response()->json(Form1601C::forMonth($year, $month));
    }

    public function form1601cPdf(int $year, int $month)
    {
        return $this->invalidMonth($month) ?? Pdf::view('pdf.form-1601c', ['company' => config('payroll.company_name'), 'w' => Form1601C::forMonth($year, $month)])
            ->format('a4')->download(sprintf('1601-C-%d-%02d.pdf', $year, $month));
    }

    /** SSS, PhilHealth and Pag-IBIG contributions for the month the pay is for. */
    public function contributions(int $year, int $month)
    {
        return $this->invalidMonth($month) ?? response()->json(Remittances::forMonth($year, $month));
    }

    public function contributionsCsv(int $year, int $month, string $agency)
    {
        if (!in_array($agency, Remittances::AGENCIES, true)) {
            return response()->json(['message' => 'The agency must be one of ' . implode(', ', Remittances::AGENCIES) . '.'], 404);
        }
        return $this->invalidMonth($month) ?? response(Remittances::csv(Remittances::forMonth($year, $month), $agency), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s-%d-%02d.csv"', $agency, $year, $month),
        ]);
    }

    public function contributionsPdf(int $year, int $month)
    {
        return $this->invalidMonth($month) ?? Pdf::view('pdf.contributions', ['company' => config('payroll.company_name'), 'r' => Remittances::forMonth($year, $month)])
            ->format('a4')->landscape()->download(sprintf('contributions-%d-%02d.pdf', $year, $month));
    }

    private function invalidMonth(int $month)
    {
        return $month < 1 || $month > 12 ? response()->json(['message' => 'The month must be 1 to 12.'], 422) : null;
    }
}

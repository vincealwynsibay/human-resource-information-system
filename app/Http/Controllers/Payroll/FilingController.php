<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Payroll\Filings\Form1601C;
use Spatie\LaravelPdf\Facades\Pdf;

/** Monthly government filings, as worksheets to copy into the agencies' own systems. */
class FilingController extends Controller
{
    public function form1601c(int $year, int $month)
    {
        if ($month < 1 || $month > 12) {
            return response()->json(['message' => 'The month must be 1 to 12.'], 422);
        }
        return response()->json(Form1601C::forMonth($year, $month));
    }

    public function form1601cPdf(int $year, int $month)
    {
        if ($month < 1 || $month > 12) {
            return response()->json(['message' => 'The month must be 1 to 12.'], 422);
        }
        return Pdf::view('pdf.form-1601c', ['company' => config('payroll.company_name'), 'w' => Form1601C::forMonth($year, $month)])
            ->format('a4')->download(sprintf('1601-C-%d-%02d.pdf', $year, $month));
    }
}

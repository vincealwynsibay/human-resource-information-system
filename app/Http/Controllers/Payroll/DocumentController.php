<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollPayslip;
use App\Models\Payroll\PayrollRun;
use App\Payroll\Bank\BankFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelPdf\Facades\Pdf;

/** Payslip PDFs and bank credit files: what leaves the system once a run is approved. */
class DocumentController extends Controller
{
    use Respond;

    /** Every payslip of a run, one per page, for printing or handing out. */
    public function runPayslips(PayrollRun $run)
    {
        return $this->payslipPdf($run->payslips()->orderBy('name')->get(), "payslips-{$run->kind}-{$run->period_start}.pdf");
    }

    public function runPayslip(PayrollRun $run, string $employeeId)
    {
        $slips = $run->payslips()->where('employee_id', $employeeId)->get();
        if ($slips->isEmpty()) {
            return response()->json(['message' => 'This employee has no payslip in this run.'], 404);
        }
        return $this->payslipPdf($slips, "payslip-{$employeeId}-{$run->period_start}.pdf");
    }

    /** An employee's own payslip, once its run is approved. */
    public function myPayslip(Request $request, PayrollPayslip $payslip)
    {
        $own = $payslip->employee_id === (string) ($request->user()->EmployeeID ?? $request->user()->id);
        if (!$own || !in_array($payslip->run->status, [PayrollRun::APPROVED, PayrollRun::PAID], true)) {
            return response()->json(['message' => 'Payslip not found.'], 404);
        }
        return $this->payslipPdf($payslip->newCollection([$payslip]), "payslip-{$payslip->run->period_start}.pdf");
    }

    /** Who will be credited, the total, and who has no bank account on file. */
    public function bank(PayrollRun $run)
    {
        return $this->attempt(fn () => [
            ...BankFile::forRun($run),
            'formats' => array_map(fn ($f) => ['key' => $f->key(), 'label' => $f->label(), 'extension' => $f->extension()], BankFile::formats()),
        ]);
    }

    public function bankDownload(PayrollRun $run, string $format)
    {
        return $this->attempt(function () use ($run, $format) {
            $layout = BankFile::format($format);
            $file = BankFile::forRun($run);
            $name = "bank-{$file['meta']['payDate']}-{$layout->key()}.{$layout->extension()}";
            return response($layout->render($file['lines'], $file['meta']), 200, [
                'Content-Type' => $layout->extension() === 'csv' ? 'text/csv; charset=UTF-8' : 'text/plain; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$name}\"",
            ]);
        });
    }

    private function payslipPdf(Collection $slips, string $filename)
    {
        $slips->loadMissing('run');
        $placement = $this->placements($slips->pluck('employee_id')->all());
        return Pdf::view('pdf.payslip', [
            'company' => config('payroll.company_name'),
            'payslips' => $slips->map(fn (PayrollPayslip $s) => [
                'slip' => $s->data,
                'run' => $s->run->only(['kind', 'status', 'pay_date']),
                'placement' => $placement[$s->employee_id] ?? [],
            ])->all(),
        ])->format('a4')->download($filename);
    }

    /** Job title and department from the HR records, when this database has them. */
    private function placements(array $employeeIds): array
    {
        if (!Schema::hasTable('tblemployee')) {
            return [];
        }
        return DB::table('tblemployee as e')
            ->leftJoin('tbldepartment as d', 'd.id', '=', 'e.DepartmentID')
            ->leftJoin('tbljobtitle as j', 'j.id', '=', 'e.JobTitleID')
            ->whereIn('e.id', array_map('intval', $employeeIds))
            ->get(['e.id', 'd.name as department', 'j.name as jobTitle'])
            ->mapWithKeys(fn ($r) => [(string) $r->id => ['department' => $r->department, 'jobTitle' => $r->jobTitle]])
            ->all();
    }
}

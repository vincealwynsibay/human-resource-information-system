<?php
namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;

class PayrollExport implements FromCollection, WithHeadings
{
    private Carbon $startDate;
    private Carbon $endDate;

    public function __construct(Carbon $startDate, Carbon $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        // month-day-year to date object
        return DB::select('CALL procGetPayrollForExcel(?, ?)', [$this->startDate, $this->endDate]);

    }

    public function headings(): array
    {
        return [
            'employee_id',
            'pay_period_start',
            'pay_period_end',
            'gross_salary',
            'deductions',
            'net_salary'
        ];
    }

}


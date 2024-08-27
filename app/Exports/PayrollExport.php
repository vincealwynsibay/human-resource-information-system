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
        return DB::table('tblpayroll')
            ->whereDate('created_date', ">=", $this->startDate)
            ->whereDate('created_date', "<=", $this->endDate)
            ->select(['EmployeeID as employee_id', 'pay_period_start', 'pay_period_end', 'gross_salary', 'deductions', 'net_salary'])
            ->get();
    }

    // public function collection()
    // {
    //     return DB::table('tblpayroll')
    //         ->get(['EmployeeID as employee_id', 'pay_period_start', 'pay_period_end', 'gross_salary', 'deductions', 'net_salary']);
    // }

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


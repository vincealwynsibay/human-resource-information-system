<?php

namespace App\Http\Controllers;

use App\Enum\DayLengthEnum;
use App\Enum\PayFrequencyEnum;
use App\Enum\LeaveRequestEnum;
use App\Exports\PayrollExport;
use App\Services\AttendanceReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use App\Enum\PayrollItemTypeEnum;
use Maatwebsite\Excel\Facades\Excel;

class PayrollController extends Controller
{



    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $payroll = DB::table('tblpayroll')->get();
        return response()->json($payroll, 200);
    }

    public function getEmployeePayroll($employeeId)
    {
        $payroll = DB::table('tblpayroll')->where('EmployeeID', $employeeId)->get();
        return response()->json($payroll, 200);
    }

    public function export(Request $request)
    {
        // $validated = $request->validate([
        //     'startDate' => 'required|date',
        //     'endDate' => 'required|date'
        // ]);
        $validated = $request->validate([
            'startDate' => 'nullable',
            'endDate' => 'nullable'
        ]);


        $employeeId = DB::table('tblemployee')->insertGetId([
            "firstname" => fake()->firstName(),
            "lastname" => fake()->lastName(),
            "joined_date" => Date::now()
        ]);

        DB::table('tblpayroll')->insert([
            'EmployeeID' => $employeeId,
            'pay_period_start' => Date::today()->subDay(),
            'pay_period_end' => Date::today(),
            'gross_salary' => 150,
            'deductions' => 50,
            'net_salary' => 100,
            'created_date' => Date::now()
        ]);

        return Excel::download(new PayrollExport(Date::createFromFormat('Y-m-d', $validated['startDate']), Date::createFromFormat('Y-m-d', $validated['endDate'])), 'payroll-' . Date::today()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, AttendanceReportService $attendanceReportService, $employeeId)
    {
        // this store is called by scheduling and the pay frequency depends on the basic salary frequency
        $validated = $request->validate([
            "employeeId" => "required|exists:tblemployee,id",
        ]);


        $basicSalary = DB::table('tblbasicsalary')->where("EmployeeID", $validated['employeeId'])->first();
        $employee = DB::table('tblemployee')->where('id', $request->employeeId)->first();
        $employeeWorkShift = DB::table('tblemployeeworkshift')->where('EmployeeID', $employee->id)->first();
        $workShift = DB::table('tblworkshift')->where('id', $employeeWorkShift->WorkShiftID)->first();
        $workDaysAmount = DB::table('tblworkweek')->select(DB::raw('(mon + tue + wed + thu + fri + sat + sun) AS total_sum'))->first()->total_sum;
        $annualWorkHours = $workDaysAmount * $workShift->hours_per_day * 52;
        $monthlyWorkHours = $annualWorkHours / 12;
        $hourlyRate = $basicSalary->amount / ($monthlyWorkHours / 2);

        $grossSalary = 0;
        $deductions = 0;
        $payrollItems = [];
        $dateFrom = null;
        $dateTo = null;


        $day = Date::now()->format('d');
        if ($day == 15) {
            $dateFrom = Date::now()->startOfMonth()->setTime(0, 0, 0);
            $dateTo = Date::today()->setTime(23, 59, 59);
        } else {
            // if end of month, schedule SSS, PhilHealth, Pag-Ibig
            $dateFrom = Date::now()->dayOfMonth(16)->setTime(0, 0, 0);
            // $dateTo = Date::today();
            $dateTo = Date::today()->setTime(23, 59, 59);
        }

        // determine if date is 15th or end of month for semi-monthly
        // if 15th, schedule income tax
        // attendance payroll items (leave, overtime, late, undertime, absences, holiday)
        $attendances = DB::table('tblattendancerecord')->where('EmployeeID', $request->employeeId)->whereBetween('date', [$dateFrom, $dateTo])->orderBy('date', 'ASC')->get();
        $payrollItems = array_merge($payrollItems, $this->getAttendancePayrollItems($attendanceReportService, $employee->id, $attendances, $workShift, $hourlyRate, $dateFrom, $dateTo));
        if ($day == 15) {
            $payrollItems[] = [
                'EmployeeID' => $employee->id,
                'item_type' => PayrollItemTypeEnum::DEDUCTION,
                'amount' => $this->calculateTaxes($grossSalary),
                'description' => 'Income Tax',
                'created_date' => Date::now()
            ];

        } else if ($day == Date::now()->endOfMonth()->format('d')) {
            // if end of month, schedule SSS, PhilHealth, Pag-Ibig
            $sssContribution = $employee->sss_contribution;
            $philhealthContribution = $employee->philhealth_contribution;
            $pagIbigContribution = $employee->pagibig_contribution;

            $payrollItems[] = [
                'EmployeeID' => $employee->id,
                'item_type' => PayrollItemTypeEnum::DEDUCTION,
                'amount' => $sssContribution,
                'description' => 'SSS Contributions',
                'created_date' => Date::now()
            ];

            $payrollItems[] = [
                'EmployeeID' => $employee->id,
                'item_type' => PayrollItemTypeEnum::DEDUCTION,
                'amount' => $philhealthContribution,
                'description' => 'PhilHealth Contributions',
                'created_date' => Date::now()
            ];

            $payrollItems[] = [
                'EmployeeID' => $employee->id,
                'item_type' => PayrollItemTypeEnum::DEDUCTION,
                'amount' => $pagIbigContribution,
                'description' => 'Pag-Ibig Contributions',
                'created_date' => Date::now()
            ];
        }

        // basic salary
        $payrollItems[] = [
            'EmployeeID' => $employee->id,
            'item_type' => PayrollItemTypeEnum::BASIC_SALARY,
            'amount' => $basicSalary->amount / 2,
            'description' => 'Basic Salary',
            'created_date' => Date::now()
        ];


        // insert all payroll items (basic salary, deductions, attendance)
        DB::table('tblpayrollitem')->insert($payrollItems);

        // calculate gross salary
        $grossSalary = $this->calculateGrossSalary($employee->id, $dateFrom, $dateTo);
        $deductions = $this->calculateDeductions($employee->id, $dateFrom, $dateTo);
        $netSalary = $grossSalary - $deductions;

        $payrollId = DB::table('tblpayroll')->insert([
            'EmployeeID' => $employee->id,
            'pay_period_start' => $dateFrom,
            'pay_period_end' => $dateTo,
            'gross_salary' => $grossSalary,
            'deductions' => $deductions,
            'net_salary' => $netSalary,
            'created_date' => Date::now()
        ]);

        return response()->json([
            'message' => 'Payroll created',
            'payrollId' => $payrollId,
            'grossSalary' => $grossSalary,
            'deductions' => $deductions,
            'netSalary' => $netSalary,
            'payrollItems' => $payrollItems
        ], 201);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $payroll = DB::table('tblpayroll')->where('id', $id)->first();
        return response()->json($payroll, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::table('tblpayroll')->where('id', $id)->delete();
        return response()->json(['message' => 'Payroll deleted'], 200);
    }


    protected function calculateGrossSalary($employeeId, $dateFrom, $dateTo)
    {
        // get all valid payroll items within the date range (salary, overtime, bonuses, allowance, holiday pay, commissions, leave pay)
        // $payrollItems = DB::table('tblpayrollitem')->where('EmployeeID', $employeeId)->whereDate('created_date', ">=", $dateFrom)->whereDate('created_date', "<=", $dateTo)->get();
        $payrollItems = DB::table('tblpayrollitem')->where('EmployeeID', $employeeId)->get();
        $grossSalary = 0;
        foreach ($payrollItems as $item) {
            if ($item->item_type != PayrollItemTypeEnum::DEDUCTION) {
                $grossSalary += $item->amount;
            }
        }
        return $grossSalary;
    }

    protected function calculateDeductions($employeeId, $dateFrom, $dateTo)
    {
        $payrollItems = DB::table('tblpayrollitem')->where('EmployeeID', $employeeId)->whereDate('created_date', ">=", $dateFrom)->whereDate('created_date', "<=", $dateTo)->get();
        $grossSalary = 0;
        foreach ($payrollItems as $item) {
            if ($item->item_type == PayrollItemTypeEnum::DEDUCTION) {
                $grossSalary += $item->amount;
            }
        }
        return $grossSalary;
    }

    protected function calculateTaxes($grossSalary)
    {
        $payFrequencyWithinAMonth = 2;

        $annualGrossSalary = $grossSalary * 12 * $payFrequencyWithinAMonth;
        $taxes = 0;
        if ($annualGrossSalary > 250000 && $annualGrossSalary <= 400000) {
            $taxes = ($annualGrossSalary - 250000) * 0.15;
        } else if ($annualGrossSalary > 400000 && $annualGrossSalary <= 800000) {
            $taxes = 22500 + ($annualGrossSalary - 400000) * 0.20;
        } else if ($annualGrossSalary > 800000 && $annualGrossSalary <= 2000000) {
            $taxes = 102500 + ($annualGrossSalary - 800000) * 0.25;
        } else if ($annualGrossSalary > 2000000 && $annualGrossSalary <= 8000000) {
            $taxes = 402500 + ($annualGrossSalary - 2000000) * 0.30;
        } else if ($annualGrossSalary >= 8000000) {
            $taxes = 2202500 + ($annualGrossSalary - 8000000) * 0.35;
        }

        return $taxes;
    }

    protected function getAttendancePayrollItems(AttendanceReportService $attendanceReportService, $employeeId, $attendances, $workShift, $hourlyRate, $startDate, $endDate)
    {

        $attendanceSummary = $attendanceReportService->getAttendanceSummary($employeeId, $attendances, $workShift, $startDate, $endDate);
        $totalOvertimeRestDay = $attendanceSummary["totalOvertimeRestDay"];
        $totalOvertimeHoliday = $attendanceSummary["totalOvertimeHoliday"];
        $totalUndertimeDuration = $attendanceSummary["totalUndertimeDuration"];
        $totalLateDuration = $attendanceSummary["totalLateDuration"];
        $absences = $attendanceSummary["absences"];
        $leave = $attendanceSummary["leave"];


        $payrollItems = [];

        if ($leave > 0) {
            $payrollItems[] = [
                'EmployeeID' => $employeeId,
                'item_type' => PayrollItemTypeEnum::LEAVE,
                'amount' => $leave * $workShift->hours_per_day * $hourlyRate,
                'description' => 'Leave',
                'created_date' => Date::now()
            ];
        }

        if ($totalOvertimeRestDay > 0) {
            $payrollItems[] = [
                'EmployeeID' => $employeeId,
                'item_type' => PayrollItemTypeEnum::OVERTIME,
                'amount' => $totalOvertimeRestDay * ($hourlyRate * 1.5),
                'description' => 'Overtime Rest Day',
                'created_date' => Date::now()
            ];
        }

        if ($totalOvertimeHoliday > 0) {
            $payrollItems[] = [
                'EmployeeID' => $employeeId,
                'item_type' => PayrollItemTypeEnum::HOLIDAY,
                'amount' => $totalOvertimeHoliday * ($hourlyRate * 2),
                'description' => 'Overtime Holiday',
                'created_date' => Date::now()
            ];
        }

        // if ($totalLateDuration > 0) {
        //     $payrollItems[] = [
        //         'EmployeeID' => $employeeId,
        //         'item_type' => PayrollItemTypeEnum::DEDUCTION,
        //         'amount' => $totalLateHours * $hourlyRate,
        //         'description' => 'Late Hours'
        //     ];
        // }

        if ($totalUndertimeDuration > 0) {
            $payrollItems[] = [
                'EmployeeID' => $employeeId,
                'item_type' => PayrollItemTypeEnum::DEDUCTION,
                'amount' => $totalUndertimeDuration * $hourlyRate,
                'description' => 'Undertime Hours',
                'created_date' => Date::now()
            ];
        }

        if ($absences > 0) {
            $payrollItems[] = [
                'EmployeeID' => $employeeId,
                'item_type' => PayrollItemTypeEnum::DEDUCTION,
                'amount' => $absences * $workShift->hours_per_day * $hourlyRate,
                'description' => 'Absences',
                'created_date' => Date::now()
            ];
        }

        return $payrollItems;
    }
}

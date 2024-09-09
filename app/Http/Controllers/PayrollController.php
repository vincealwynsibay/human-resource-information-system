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
        $payroll = DB::select('CALL procGetPayroll(?)', [null]);
        return response()->json($payroll, 200);
    }

    public function getEmployeePayroll($employeeId)
    {
        $payroll = DB::select('CALL procGetPayroll(?)', [$employeeId]);

        return response()->json($payroll, 200);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'startDate' => 'nullable',
            'endDate' => 'nullable'
        ]);

        return Excel::download(new PayrollExport(Date::createFromFormat('Y-m-d', $request->startDate), Date::createFromFormat('Y-m-d', $request->endDate)), 'payroll-' . Date::today()->format('Y-m-d') . '.xlsx');
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


        $basicSalary = DB::select('CALL procGetEmployeeSalary(?)', [$request->employeeId])[0] ?? null;
        if ($basicSalary == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not have a salary']
                ]
            ], 404);
        }

        $employee = DB::select('CALL procGetEmployee(?)', [$request->employeeId])[0] ?? null;
        $employeeWorkShift = DB::select('CALL procGetEmployeeWorkShifts(?)', [$employee->id])[0] ?? null;
        $workShift = DB::select('CALL procGetWorkShift(?)', [$employeeWorkShift->WorkShiftID])[0] ?? null;

        if ($workShift == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not have a work shift']
                ]
            ], 404);
        }

        $workDaysAmount = DB::select('CALL procGetNumberOfWorkDays()')[0]->total ?? 0;

        if ($workDaysAmount == 0) {
            return response()->json([
                'errors' => [
                    'WorkDays' => ['No work days found']
                ]
            ], 404);
        }

        $currentDate = Date::now();

        $annualWorkHours = $workDaysAmount * $workShift->hours_per_day * 52;
        $monthlyWorkHours = $annualWorkHours / 12;
        $hourlyRate = $basicSalary->amount / ($monthlyWorkHours / 2);

        $grossSalary = 0;
        $deductions = 0;
        $payrollItems = [];
        $dateFrom = null;
        $dateTo = null;


        $day = $currentDate->format('d');
        if ($day == 15) {
            $dateFrom = $currentDate->startOfMonth()->setTime(0, 0, 0);
            $dateTo = Date::today()->setTime(23, 59, 59);
        } else if ($day == $currentDate->endOfMonth()->format('d')) {
            // if end of month, schedule SSS, PhilHealth, Pag-Ibig
            $dateFrom = $currentDate->dayOfMonth(16)->setTime(0, 0, 0);
            // $dateTo = Date::today();
            $dateTo = Date::today()->setTime(23, 59, 59);
        } else {
            // TODO: invalid date for payroll or continue
            return response()->json(['message' => 'Invalid date for payroll'], 400);
        }

        // check if payroll already exists between $dateFrom and $dateTo
        $existingPayroll = DB::select('CALL procGetPayrollByPayPeriod(?, ?, ?)', [$employeeId, $dateFrom, $dateTo]);


        if ($existingPayroll) {
            return response()->json(['message' => 'Payroll already exists for this time'], 400);
        }

        // determine if date is 15th or end of month for semi-monthly
        // if 15th, schedule income tax
        // attendance payroll items (leave, overtime, late, undertime, absences, holiday)
        $attendances = DB::select('CALL procGetEmployeeAttendanceByDateRange(?, ?, ?)', [
            $employee->id,
            $dateFrom,
            $dateTo
        ]);
        $payrollItems = array_merge($payrollItems, $this->getAttendancePayrollItems($attendanceReportService, $employee->id, $attendances, $workShift, $hourlyRate, $dateFrom, $dateTo));
        if ($day == 15) {
            $payrollItems[] = [
                'EmployeeID' => $employee->id,
                'item_type' => PayrollItemTypeEnum::DEDUCTION->value,
                'amount' => $this->calculateTaxes($grossSalary),
                'description' => 'Income Tax',
                'created_date' => $currentDate
            ];

        } else if ($day == $currentDate->endOfMonth()->format('d')) {
            // if end of month, schedule SSS, PhilHealth, Pag-Ibig
            $sssContribution = $employee->sss_contribution;
            $philhealthContribution = $employee->philhealth_contribution;
            $pagIbigContribution = $employee->pagibig_contribution;

            $payrollItems[] = [
                'EmployeeID' => $employee->id,
                'item_type' => PayrollItemTypeEnum::DEDUCTION->value,
                'amount' => $sssContribution,
                'description' => 'SSS Contributions',
                'created_date' => $currentDate
            ];

            $payrollItems[] = [
                'EmployeeID' => $employee->id,
                'item_type' => PayrollItemTypeEnum::DEDUCTION->value,
                'amount' => $philhealthContribution,
                'description' => 'PhilHealth Contributions',
                'created_date' => $currentDate
            ];

            $payrollItems[] = [
                'EmployeeID' => $employee->id,
                'item_type' => PayrollItemTypeEnum::DEDUCTION->value,
                'amount' => $pagIbigContribution,
                'description' => 'Pag-Ibig Contributions',
                'created_date' => $currentDate
            ];
        }

        // basic salary
        $payrollItems[] = [
            'EmployeeID' => $employee->id,
            'item_type' => PayrollItemTypeEnum::BASIC_SALARY->value,
            'amount' => $basicSalary->amount / 2,
            'description' => 'Basic Salary',
            'created_date' => $currentDate
        ];


        // insert all payroll items (basic salary, deductions, attendance)
        foreach ($payrollItems as $item) {
            DB::select('CALL procInsertPayrollItem(?, ?, ?, ?, ?)', [
                $item['EmployeeID'],
                $item['item_type'],
                $item['amount'],
                $item['description'],
                $currentDate
            ]);
        }

        // calculate gross salary
        $grossSalary = $this->calculateGrossSalary($employee->id, $dateFrom, $dateTo);
        $deductions = $this->calculateDeductions($employee->id, $dateFrom, $dateTo);
        $netSalary = $grossSalary - $deductions;

        $payrollId = DB::select('CALL procInsertPayroll(?, ?, ?, ?, ?, ?, ?)', [
            $employee->id,
            $dateFrom,
            $dateTo,
            $grossSalary,
            $deductions,
            $netSalary,
            Date::now()
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
        $payroll = DB::select('CALL procGetPayrollByID(?)', [$id]);
        return response()->json($payroll, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::select('CALL procDeletePayrollByID(?)', [
            $id
        ]);
        return response()->json(['message' => 'Payroll deleted'], 200);
    }


    protected function calculateGrossSalary($employeeId, $dateFrom, $dateTo)
    {
        // get all valid payroll items within the date range (salary, overtime, bonuses, allowance, holiday pay, commissions, leave pay)
        $payrollItems = DB::select('CALL procGetPayrollItems(?)', [
            $employeeId
        ]);
        $grossSalary = 0;
        foreach ($payrollItems as $item) {
            if ($item->item_type != PayrollItemTypeEnum::DEDUCTION->value) {
                $grossSalary += $item->amount;
            }
        }
        return $grossSalary;
    }

    protected function calculateDeductions($employeeId, $dateFrom, $dateTo)
    {
        $payrollItems = DB::select('CALL procGetPayrollItems(?)', [
            $employeeId
        ]);
        $grossSalary = 0;
        foreach ($payrollItems as $item) {
            if ($item->item_type == PayrollItemTypeEnum::DEDUCTION->value) {
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
                'item_type' => PayrollItemTypeEnum::LEAVE->value,
                'amount' => $leave * $workShift->hours_per_day * $hourlyRate,
                'description' => 'Leave',
                'created_date' => Date::now()
            ];
        }

        if ($totalOvertimeRestDay > 0) {
            $payrollItems[] = [
                'EmployeeID' => $employeeId,
                'item_type' => PayrollItemTypeEnum::OVERTIME->value,
                'amount' => $totalOvertimeRestDay * ($hourlyRate * 1.5),
                'description' => 'Overtime Rest Day',
                'created_date' => Date::now()
            ];
        }

        if ($totalOvertimeHoliday > 0) {
            $payrollItems[] = [
                'EmployeeID' => $employeeId,
                'item_type' => PayrollItemTypeEnum::HOLIDAY->value,
                'amount' => $totalOvertimeHoliday * ($hourlyRate * 2),
                'description' => 'Overtime Holiday',
                'created_date' => Date::now()
            ];
        }

        // if ($totalLateDuration > 0) {
        //     $payrollItems[] = [
        //         'EmployeeID' => $employeeId,
        //         'item_type' => PayrollItemTypeEnum::DEDUCTION->value,
        //         'amount' => $totalLateHours * $hourlyRate,
        //         'description' => 'Late Hours'
        //     ];
        // }

        if ($totalUndertimeDuration > 0) {
            $payrollItems[] = [
                'EmployeeID' => $employeeId,
                'item_type' => PayrollItemTypeEnum::DEDUCTION->value,
                'amount' => $totalUndertimeDuration * $hourlyRate,
                'description' => 'Undertime Hours',
                'created_date' => Date::now()
            ];
        }

        if ($absences > 0) {
            $payrollItems[] = [
                'EmployeeID' => $employeeId,
                'item_type' => PayrollItemTypeEnum::DEDUCTION->value,
                'amount' => $absences * $workShift->hours_per_day * $hourlyRate,
                'description' => 'Absences',
                'created_date' => Date::now()
            ];
        }

        return $payrollItems;
    }
}

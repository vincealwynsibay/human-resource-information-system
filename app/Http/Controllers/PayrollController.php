<?php

namespace App\Http\Controllers;

use App\Enum\DayLengthEnum;
use App\Enum\PayFrequencyEnum;
use App\Enum\LeaveRequestEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use PayrollItemTypeEnum;

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

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // this store is called by scheduling and the pay frequency depends on the basic salary frequency
        $validated = $request->validate([
            "employeeId" => "required|exists:tblemployee,id",
        ]);

        $basicSalary = DB::table('tblbasicsalary')->where("EmployeeID", $validated['employeeId'])->first();
        $employee = DB::table('tblemployee')->where('id', $request->employeeId)->first();
        $workShift = DB::table('tblworkshift')->where('id', $employee->WorkShiftID)->first();
        $workDaysAmount = DB::table('tblworkweek')->select(DB::raw('(mon + tue + wed + thu + fri + sat + sun) AS total_sum'))->first()['total_sum'];
        // work days amount * hours per day * 52 weeks
        $annualWorkHours = $workDaysAmount * $workShift["hours_per_day"] * 52;
        $monthlyWorkHours = $annualWorkHours / 12;
        // $weeklyWorkHours = $annualWorkHours / 52;
        $grossSalary = 0;

        $payroll = [];
        $payrollItems = [];


        // determine if date is 15th or end of month for semi-monthly
        $day = Date::now()->format('d');
        if ($day == 15) {
            $attendances = DB::table('tblattendancerecord')->where('EmployeeID', $request->employeeId)->where('date', [Date::now()->startOfMonth(), Date::today()])->orderBy('date', 'DESC')->get();
            // TODO: modify basic salary to get from payroll item
            $hourlyRate = $basicSalary["amount"] / ($monthlyWorkHours / 2);
            $grossSalary = $this->calculateGrossSalary($basicSalary, $attendances, $workShift, $hourlyRate);

            $payroll = [
                'EmployeeID' => $request->employeeId,
                'pay_period_start' => Date::now()->startOfMonth(),
                'pay_period_end' => Date::now(),
                'created_date' => Date::now()
            ];

            // TODO: setup deductions to get from payroll item, schedule deductions for taxes, sss, philhealth, pagibig to be spread out in 2 payrolls
        } else if ($day == Date::now()->endOfMonth()->format('d')) {
            $attendances = DB::table('tblattendancerecord')->where('EmployeeID', $request->employeeId)->where('date', [Date::now()->dayOfMonth(16), Date::today()])->orderBy('date', 'DESC')->get();

            $payroll = [
                'EmployeeID' => $request->employeeId,
                'pay_period_start' => Date::now()->startOfMonth(),
                'pay_period_end' => Date::now(),
                'created_date' => Date::now()
            ];
        }

        // basic salary
        $payrollItems[] = [
            'payroll_id' => $payroll['id'],
            'item_type' => PayrollItemTypeEnum::BASIC_SALARY,
            'amount' => $basicSalary["amount"],
            'description' => 'Basic Salary'
        ];

        // attendance payroll items (leave, overtime, late, undertime, absences)
        $payrollItems = array_merge($payrollItems, $this->calculateAttendacePayrollItems($attendances, $workShift, $hourlyRate));

        // calculate gross salary with basic salary, overtime pay, bonuses, allowance holiday pay, commissions, leave pay, 
        // TODO: setup a workflow for other payroll item (overtime, bonus, allowance, commission, leave, holiday)
        $grossSalary = 0;

        foreach ($payrollItems as $item) {
            if ($item["item_type"] != PayrollItemTypeEnum::DEDUCTION) {
                $grossSalary += $item["amount"];
            }
        }

        $deductions = 0;
        $payroll["deductions"] += $this->calculateTaxes($grossSalary, $basicSalary["pay_frequency"]);
        $payrollItem[] = [
            'item_type' => PayrollItemTypeEnum::DEDUCTION,
            'amount' => $this->calculateTaxes($grossSalary, $basicSalary["pay_frequency"]),
            'description' => 'Income Tax'
        ];

        // provide a fixed amount for each employee instead of automating
        $employee = DB::table('tblemployee')->where($request->user()->EmployeeID)->first();
        $sssContribution = $employee->sss_contribution;
        $philhealthContribution = $employee->philhealth_contribution;
        $pagIbigContribution = $employee->pagibig_contribution;

        $payroll["deductions"] += $sssContribution;
        $payrollItem[] = [
            'item_type' => PayrollItemTypeEnum::DEDUCTION,
            'amount' => $sssContribution,
            'description' => 'SSS Contributions'
        ];

        $payroll["deductions"] += $philhealthContribution;
        $payrollItem[] = [
            'item_type' => PayrollItemTypeEnum::DEDUCTION,
            'amount' => $philhealthContribution,
            'description' => 'PhilHealth Contributions'
        ];

        $payroll["deductions"] += $pagIbigContribution;
        $payrollItem[] = [
            'item_type' => PayrollItemTypeEnum::DEDUCTION,
            'amount' => $pagIbigContribution,
            'description' => 'Pag-Ibig Contributions'
        ];

        $payroll["net_salary"] = $grossSalary - $deductions;

        $payrollId = DB::table('tblpayroll')->insert($payroll);
        DB::table('tblpayrollitem')->insert($payrollItems);

        return response()->json(['message' => 'Payroll created'], 201);
    }

    protected function calculateTaxes($grossSalary, $payFrequency)
    {
        $payFrequencyWithinAMonth = 1;

        if ($payFrequency == PayFrequencyEnum::WEEKLY) {
            $payFrequencyWithinAMonth = 4;
        } else if ($payFrequency == PayFrequencyEnum::BIWEEKLY) {
            $payFrequencyWithinAMonth = 2;
        } else if ($payFrequency == PayFrequencyEnum::MONTHLY) {
            $payFrequencyWithinAMonth = 1;
        } else if ($payFrequency == PayFrequencyEnum::ANNUALLY) {
            $payFrequencyWithinAMonth = 1 / 12;
        }

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

    // protected function calculateSSSContributions($grossSalary)
    // {
    //     $contribution = DB::table('tblsss')->where('compensation_from', ">= $grossSalary")->where('compensation_to', "<= $grossSalary")->first();
    //     return $contribution->total_contribution_ee;
    // }

    // protected function calculatePhilHealthContribution($basicSalary)
    // {
    //     return $basicSalary * 0.05;
    // }

    // protected function calculatePagIbigContribution($basicSalary)
    // {
    //     if ($basicSalary <= 1500) {
    //         return $basicSalary * 0.01;
    //     }

    //     return $basicSalary * 0.02;
    // }


    protected function calculateAttendacePayrollItems($attendances, $workShift, $hourlyRate)
    {

        $totalHoursWorked = 0;
        $overtimeRegularHours = 0;
        $overtimeRestDay = 0;
        $overtimeHoliday = 0;
        $undertimeHours = 0;
        $lateHours = 0;
        $absences = 0;
        $leave = 0;

        $left = 0;
        $right = 0;
        while ($right < count($attendances)) {
            $attendance = $attendances[$left];
            $leftDate = $attendances[$left];
            $rightDate = $attendances[$right];

            // get gap between two attendances
            if (Date::parse($leftDate->date)->diffInDays(Date::parse($rightDate->date)) > 1) {
                $date = Date::parse($leftDate->date)->addDay();

                // left date + 1 to right before the right date, check if there are absences
                while ($date->lt(Date::parse($rightDate->date))) {
                    $currentDate = $date->format('Y-m-d');

                    $day = strtolower(Date::parse('Y-m-d', $currentDate)->format('D'));
                    $workWeek = DB::table('tblworkweek')->first();
                    $dayLength = $workWeek->$day;

                    if (DayLengthEnum::from($dayLength)->isNoWork()) {
                        $date = $date->addDay();
                        continue;
                    }

                    $holiday = DB::table('tblholiday')->where('date', $currentDate)->first();
                    if ($holiday != null) {
                        $date = $date->addDay();
                        continue;
                    }

                    $currentLeave = DB::table('tblleave')
                        ->where('status', LeaveRequestEnum::APPROVED)
                        // if unpaid leave add to absences
                        ->whereNot('leaveTypeID', null)
                        ->where('EmployeeID', $attendance->EmployeeID)
                        ->where('date', $currentDate)
                        ->first();

                    if ($currentLeave != null) {
                        // add to leave counter
                        $leave++;
                        $date = $date->addDay();
                        continue;
                    }

                    $absences++;
                    $date = $date->addDay();
                }
            }


            $timeIn1 = $attendance->time_in_session1;
            $timeOut1 = $attendance->time_out_session1;
            $timeIn2 = $attendance->time_in_session2;
            $timeOut2 = $attendance->time_out_session2;


            if ($timeIn1 != null && $timeOut1 != null) {
                $totalHoursWorked += Date::parse($timeOut1)->diffInMinutes(Date::parse($timeIn1)) / 60;
            }

            if ($timeIn2 != null && $timeOut2 != null) {
                $totalHoursWorked += Date::parse($timeOut2)->diffInMinutes(Date::parse($timeIn2)) / 60;
            }

            // check if date is rest day (check work week)
            $day = strtolower(Date::parse('Y-m-d', $attendance->date)->format('D'));
            $workWeek = DB::table('tblworkweek')->first();
            $dayLength = $workWeek->$day;
            $holiday = DB::table('tblholiday')->where('date', $attendance->date)->first();

            // check if either holiday or rest day, because there is a different rate for that, 
            // if also holiday or rest day, it is impossible to overtime because all work hours is an overtime
            // TODO: this overtimes should be authorized by the manager/admin
            if (DayLengthEnum::from($dayLength)->isNoWork()) {
                $overtimeRestDay += $totalHoursWorked;
            } else if ($holiday != null) {
                $overtimeHoliday += $totalHoursWorked;
            } else if ($totalHoursWorked > $workShift->hours_per_day) {
                $overtimeRegularHours += $totalHoursWorked - $workShift->hours_per_day;
            } else if ($totalHoursWorked < $workShift->hours_per_day) {
                $undertimeHours = $workShift->hours_per_day - $totalHoursWorked;
            }

            if ($timeIn1 != null && $timeIn1 > $workShift->start_time) {
                $lateHours += Date::parse($timeIn1)->diffInMinutes(Date::parse($workShift->start_time)) / 60;
            }

            if ($timeIn2 != null && $timeIn2 > $workShift->start_time) {
                $lateHours += Date::parse($timeIn2)->diffInMinutes(Date::parse($workShift->start_time)) / 60;
            }

            $left = $right;
            $right++;
        }

        $payrollItems = [];

        if ($leave > 0) {
            $payrollItems[] = [
                'item_type' => PayrollItemTypeEnum::LEAVE,
                'amount' => $leave * $workShift->hours_per_day * $hourlyRate,
                'description' => 'Leave'
            ];
        }

        if ($overtimeRegularHours > 0) {
            $payrollItems[] = [
                'item_type' => PayrollItemTypeEnum::OVERTIME,
                'amount' => $overtimeRegularHours * ($hourlyRate * 1.25),
                'description' => 'Overtime Regular Hours'
            ];
        }

        if ($overtimeRestDay > 0) {
            $payrollItems[] = [
                'item_type' => PayrollItemTypeEnum::OVERTIME,
                'amount' => $overtimeRestDay * ($hourlyRate * 1.5),
                'description' => 'Overtime Rest Day'
            ];
        }

        if ($overtimeHoliday > 0) {
            $payrollItems[] = [
                'item_type' => PayrollItemTypeEnum::OVERTIME,
                'amount' => $overtimeHoliday * ($hourlyRate * 2),
                'description' => 'Overtime Holiday'
            ];
        }


        // TODO: DECIDE IF DEDUCTION BY LATE OR UNDERTIME
        // if ($lateHours > 0) {
        //     $payrollItems[] = [
        //         'item_type' => PayrollItemTypeEnum::DEDUCTION,
        //         'amount' => $lateHours * $hourlyRate,
        //         'description' => 'Late Hours'
        //     ];
        // }

        if ($undertimeHours > 0) {
            $payrollItems[] = [
                'item_type' => PayrollItemTypeEnum::DEDUCTION,
                'amount' => $undertimeHours * $hourlyRate,
                'description' => 'Undertime Hours'
            ];
        }

        if ($absences > 0) {
            $payrollItems[] = [
                'item_type' => PayrollItemTypeEnum::DEDUCTION,
                'amount' => $absences * $workShift->hours_per_day * $hourlyRate,
                'description' => 'Absences'
            ];
        }

        // $grossSalary = $basicSalary["amount"];
        // $grossSalary -= ($lateHours * $hourlyRate);
        // $grossSalary -= ($undertimeHours * $hourlyRate);
        // $grossSalary -= ($absences * $workShift->hours_per_day * $hourlyRate);
        return $payrollItems;
    }

    protected function calculateGrossSalary($basicSalary, $attendances, $workShift, $hourlyRate)
    {

        $totalHoursWorked = 0;
        $overtimeRegularHours = 0;
        $overtimeRestDay = 0;
        $undertimeHours = 0;
        $lateHours = 0;
        $absences = 0;

        $left = 0;
        $right = 0;
        while ($right < count($attendances)) {
            $attendance = $attendances[$left];
            $leftDate = $attendances[$left];
            $rightDate = $attendances[$right];

            // get gap between two attendances
            if (Date::parse($leftDate->date)->diffInDays(Date::parse($rightDate->date)) > 1) {
                $date = Date::parse($leftDate->date)->addDay();

                // left date + 1 to right before the right date, check if there are absences
                while ($date->lt(Date::parse($rightDate->date))) {
                    $currentDate = $date->format('Y-m-d');

                    $day = strtolower(Date::parse('Y-m-d', $currentDate)->format('D'));
                    $workWeek = DB::table('tblworkweek')->first();
                    $dayLength = $workWeek->$day;

                    if (DayLengthEnum::from($dayLength)->isNoWork()) {
                        $date = $date->addDay();
                        continue;
                    }

                    $holiday = DB::table('tblholiday')->where('date', $currentDate)->first();
                    if ($holiday != null) {
                        $date = $date->addDay();
                        continue;
                    }

                    $leave = DB::table('tblleave')
                        ->where('status', LeaveRequestEnum::APPROVED)
                        // if unpaid leave add to absences
                        ->whereNot('leaveTypeID', null)
                        ->where('EmployeeID', $attendance->EmployeeID)
                        ->where('date', $currentDate)
                        ->first();

                    if ($leave != null) {
                        $date = $date->addDay();
                        continue;
                    }

                    $absences++;
                    $date = $date->addDay();
                }
            }


            $timeIn1 = $attendance->time_in_session1;
            $timeOut1 = $attendance->time_out_session1;
            $timeIn2 = $attendance->time_in_session2;
            $timeOut2 = $attendance->time_out_session2;


            if ($timeIn1 != null && $timeOut1 != null) {
                $totalHoursWorked += Date::parse($timeOut1)->diffInMinutes(Date::parse($timeIn1)) / 60;
            }

            if ($timeIn2 != null && $timeOut2 != null) {
                $totalHoursWorked += Date::parse($timeOut2)->diffInMinutes(Date::parse($timeIn2)) / 60;
            }

            // check if date is rest day (check work week)
            $day = strtolower(Date::parse('Y-m-d', $attendance->date)->format('D'));
            $workWeek = DB::table('tblworkweek')->first();
            $dayLength = $workWeek->$day;
            $holiday = DB::table('tblholiday')->where('date', $attendance->date)->first();

            // check if either holiday or rest day, because there is a different rate for that, 
            // if also holiday or rest day, it is impossible to overtime because all work hours is an overtime
            if (DayLengthEnum::from($dayLength)->isNoWork()) {
                $overtimeRestDay += $totalHoursWorked;
            } else if ($holiday != null) {
                $overtimeRestDay += $totalHoursWorked;
            } else if ($totalHoursWorked > $workShift->hours_per_day) {
                $overtimeRegularHours += $totalHoursWorked - $workShift->hours_per_day;
            } else if ($totalHoursWorked < $workShift->hours_per_day) {
                $undertimeHours = $workShift->hours_per_day - $totalHoursWorked;
            }

            if ($timeIn1 != null && $timeIn1 > $workShift->start_time) {
                $lateHours += Date::parse($timeIn1)->diffInMinutes(Date::parse($workShift->start_time)) / 60;
            }

            if ($timeIn2 != null && $timeIn2 > $workShift->start_time) {
                $lateHours += Date::parse($timeIn2)->diffInMinutes(Date::parse($workShift->start_time)) / 60;
            }

            $left = $right;
            $right++;
        }

        $grossSalary = $basicSalary["amount"];
        // $grossSalary += ($overtimeRegularHours * ($hourlyRate * 1.25));
        // $grossSalary += ($overtimeRestDay * ($hourlyRate * 1.5));
        $grossSalary -= ($lateHours * $hourlyRate);
        $grossSalary -= ($undertimeHours * $hourlyRate);
        $grossSalary -= ($absences * $workShift->hours_per_day * $hourlyRate);
        return $grossSalary;
    }

    // protected function calculateGrossSalary($basicSalary, $hourlyRate, $totalHoursWorked, $overtimeRegularHours, $overtimeRestDay, $lateHours, $undertimeHours)
    // {
    //     $grossSalary = $basicSalary["amount"];
    //     $grossSalary += ($overtimeRegularHours * ($hourlyRate * 1.25));
    //     $grossSalary += ($overtimeRestDay * ($hourlyRate * 1.5));
    //     $grossSalary -= ($lateHours * $hourlyRate);
    //     $grossSalary -= ($undertimeHours * $hourlyRate);
    //     return $grossSalary;
    // }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        DB::table('tblpayroll')->where('id', $id)->delete();
        return response()->json(['message' => 'Payroll deleted'], 200);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enum\RoleEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\File;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            "name" => "nullable|string|max:200",
            "id" => "nullable|string",
            "employmentStatus" => "nullable|exists:tblemploymentstatus,id",
            "jobTitle" => "nullable|exists:tbljobtitle,id",
            "department" => "nullable|exists:tbldepartment,id",
            "sortField" => "nullable|string",
            "sortOrder" => "nullable|string|in:asc,desc",
        ]);


        $sortOrder = $request->sortOrder ?? "desc";
        $sortField = $request->sortField ?? "id";


        $employees = [];
        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            // call procGetEmployeesAsSuperVisor
            $employees = DB::select("CALL procGetEmployeesAsSuperVisor(?, ?, ?, ?, ?, ?, ?, ?)", [
                $request->user()->EmployeeID,
                $request->id,
                $request->name,
                $request->employmentStatus,
                $request->jobTitle,
                $request->department,
                $sortField,
                $sortOrder,
                NULL
            ]);

        } else {
            // call procGetEmployees
            $employees = DB::select("CALL procGetEmployees(?, ?, ?, ?, ?, ?, ?, ?)", [
                $request->id,
                $request->name,
                $request->employmentStatus,
                $request->jobTitle,
                $request->department,
                $sortField,
                $sortOrder,
                NULL
            ]);
        }


        return response()->json([
            "employees" => $employees
        ], 200);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'firstname' => "required|string|max:100",
            'lastname' => "required|string|max:100",
            'middlename' => "nullable|string|max:100",
        ]);

        DB::select('CALL procInsertEmployee(?,?,?, ?)', [
            $request->firstname,
            $request->lastname,
            $request->middlename ?? "",
            Date::now()
        ]);

        return response()->json([
            "message" => "Employee created successfully"
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {


        $employee = DB::select('CALL procGetEmployee(?)', [$id])[0];

        // check if employee is a subordinate to current user
        $subordinate = DB::select('CALL procGetSubordinate(?,?)', [$request->user()->EmployeeID, $employee->id]);

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($subordinate == null && $employee->id != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['You cannot view other employees\'s details']
                    ]
                ], 404);
            }
        }

        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        return response()->json(["employee" => $employee], 200);
    }


    public function assignSupervisor(Request $request, string $id)
    {
        $validated = $request->validate([
            'supervisor' => "required|exists:tblemployee,id"
        ]);

        if ($request->supervisor == $id) {
            return response()->json([
                'errors' => [
                    'Supervisor' => ['Employee cannot be their own supervisor']
                ]
            ], 400);
        }

        $employee = DB::select('CALL procGetEmployee(?)', [$id])[0];
        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        DB::select('CALL procAssignSupervisor(?,?)', [$request->supervisor, $employee->id]);

        return response()->json([
            'message' => "Supervisor assigned successfully"
        ], 200);
    }

    public function removeSupervisor(Request $request, string $id)
    {
        $validated = $request->validate([
            'supervisor' => "required|exists:tblemployee,id"
        ]);

        $employee = DB::select('CALL procGetEmployee(?)', [$id])[0];

        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        DB::select('CALL procRemoveSupervisor(?,?)', [$request->supervisor, $employee->id]);

        return response()->json([
            'message' => "Supervisor removed successfully"
        ], 200);
    }

    public function assignWorkShift(Request $request, string $id)
    {
        $validated = $request->validate([
            'workShift' => "required|exists:tblworkshift,id"
        ]);

        $employee = DB::select('CALL procGetEmployee(?)', [$id])[0];

        // check if employee is a subordinate to current user
        $subordinate = DB::select('CALL procGetSubordinate(?,?)', [$request->user()->EmployeeID, $employee->id]);


        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($subordinate == null && $employee->id != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['You cannot update other\'s details']
                    ]
                ], 404);
            }
        }

        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        DB::select('CALL procAssignWorkShift(?,?)', [$employee->id, $request->workShift]);

        return response()->json([
            'message' => "Work Shift assigned successfully"
        ], 200);
    }

    public function removeWorkShift(Request $request, string $id)
    {
        $validated = $request->validate([
            'workShift' => "required|exists:tblworkshift,id"
        ]);

        $employee = DB::select('CALL procGetEmployee(?)', [$id])[0];


        // check if employee is a subordinate to current user
        $subordinate = DB::select('CALL procGetSubordinate(?,?)', [$request->user()->EmployeeID, $employee->id]);

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($subordinate == null && $employee->id != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['You cannot update other\'s details']
                    ]
                ], 404);
            }
        }


        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        DB::select('CALL procRemoveWorkShift(?,?)', [$employee->id, $request->workShift]);

        return response()->json([
            'message' => "Work Shift removed successfully"
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'firstname' => "required|string|max:100",
            'lastname' => "required|string|max:100",
            'middlename' => "nullable|string|max:100",
            'birth_date' => "nullable|date",
            'gender' => "nullable|string|max:6",
            'marital_status' => "nullable|string|max:20",
            'mobile_no' => "nullable|string|max:50",
            'email' => "nullable|string|max:50",
            'address' => "nullable|string|max:100",
            'city' => "nullable|string|max:100",
            'province' => "nullable|string|max:100",
            'zip_code' => "nullable|string|max:20",
        ]);


        $employee = DB::select('CALL procGetEmployee(?)', [$id])[0];


        // check if employee is a subordinate to current user
        $subordinate = DB::select('CALL procGetSubordinate(?,?)', [$request->user()->EmployeeID, $employee->id]);


        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($subordinate == null && $employee->id != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['You cannot update other\'s details']
                    ]
                ], 404);
            }
        }


        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        DB::select('CALL procUpdateEmployeeByID(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $id,
            $request->firstname ?? "",
            $request->lastname ?? "",
            $request->middlename ?? "",
            $request->birth_date ?? null,
            $request->gender ?? "",
            $request->marital_status ?? "",
            $request->mobile_no ?? "",
            $request->email ?? "",
            $request->address ?? "",
            $request->city ?? "",
            $request->province ?? "",
            $request->zip_code ?? "",
        ]);

        return response()->json([
            'message' => "Employee Updated Successfully"
        ], 200);
    }

    public function updateContribution(Request $request, string $id)
    {
        $validated = $request->validate([
            'sss' => "nullable|string|max:50",
            'pagibig' => "nullable|string|max:50",
            'philhealth' => "nullable|string|max:50",
        ]);


        $employee = DB::select('CALL procGetEmployee(?)', [$id])[0];


        // check if employee is a subordinate to current user
        $subordinate = DB::select('CALL procGetSubordinate(?,?)', [$request->user()->EmployeeID, $employee->id]);


        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($subordinate == null && $employee->id != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['You cannot update other\'s details']
                    ]
                ], 404);
            }
        }



        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }


        DB::select('CALL procUpdateEmployeeContribution(?, ?, ?, ?)', [
            $id,
            $request->sss ?? "",
            $request->pagibig ?? "",
            $request->philhealth ?? "",
        ]);

        return response()->json([
            'message' => "Employee Contribution Updated Successfully"
        ], 200);

    }

    public function updatePersonalDetails(Request $request, string $id)
    {
        $validated = $request->validate([
            'firstname' => "required|string|max:100",
            'lastname' => "required|string|max:100",
            'middlename' => "nullable|string|max:100",
            'birth_date' => "nullable|date",
            'gender' => "nullable|string|max:6",
            'marital_status' => "nullable|string|max:20",
            'mobile_no' => "nullable|string|max:50",
            'email' => "nullable|string|max:50",
            'address' => "nullable|string|max:100",
            'city' => "nullable|string|max:100",
            'province' => "nullable|string|max:100",
            'zip_code' => "nullable|string|max:20",
        ]);


        $employee = DB::select('CALL procGetEmployee(?)', [$id])[0];


        // check if employee is a subordinate to current user
        $subordinate = DB::select('CALL procGetSubordinate(?,?)', [$request->user()->EmployeeID, $employee->id]);


        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($subordinate == null && $employee->id != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['You cannot update other\'s details']
                    ]
                ], 404);
            }
        }


        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        DB::select('CALL procUpdateEmployeePersonalDetailsByID(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $id,
            $request->firstname ?? "",
            $request->lastname ?? "",
            $request->middlename ?? "",
            $request->birth_date ?? null,
            $request->gender ?? "",
            $request->marital_status ?? "",
            $request->mobile_no ?? "",
            $request->email ?? "",
            $request->address ?? "",
            $request->city ?? "",
            $request->province ?? "",
            $request->zip_code ?? "",
        ]);

        return response()->json([
            'message' => "Employee Updated Successfully"
        ], 200);
    }


    public function updateContactDetails(Request $request, string $id)
    {
        $validated = $request->validate([
            'mobile_no' => "nullable|string|max:50",
            'email' => "nullable|string|max:50",
            'address' => "nullable|string|max:100",
            'city' => "nullable|string|max:100",
            'province' => "nullable|string|max:100",
            'zip_code' => "nullable|string|max:20",
        ]);

        $employee = DB::select('CALL procGetEmployee(?)', [$id])[0];


        // check if employee is a subordinate to current user
        $subordinate = DB::select('CALL procGetSubordinate(?,?)', [$request->user()->EmployeeID, $employee->id]);


        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($subordinate == null && $employee->id != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['You cannot update other\'s details']
                    ]
                ], 404);
            }
        }



        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        // DB::table("tblemployee")->where('id', $id)->update([
        //     'mobile_no' => $request->mobile_no ?? "",
        //     'email' => $request->email ?? "",
        //     'address' => $request->address ?? "",
        //     'city' => $request->city ?? "",
        //     'province' => $request->province ?? "",
        //     'zip_code' => $request->zip_code ?? "",
        // ]);

        DB::select('CALL procUpdateEmployeeContactDetailsByID(?, ?, ?, ?, ?, ?, ?)', [
            $id,
            $request->mobile_no ?? "",
            $request->email ?? "",
            $request->address ?? "",
            $request->city ?? "",
            $request->province ?? "",
            $request->zip_code ?? "",
        ]);

        return response()->json([
            'message' => "Employee Contact Details Updated Successfully"
        ], 200);
    }
    public function updateJobDetails(Request $request, string $id)
    {
        $validated = $request->validate([
            'jobTitle' => "nullable|exists:tbljobtitle,id",
            'employmentStatus' => "nullable|exists:tblemploymentstatus,id",
            'department' => "nullable|exists:tbldepartment,id",
        ]);


        $employee = DB::select('CALL procGetEmployee(?)', [$id])[0];


        // check if employee is a subordinate to current user
        $subordinate = DB::select('CALL procGetSubordinate(?,?)', [$request->user()->EmployeeID, $employee->id]);


        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($subordinate == null && $employee->id != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['You cannot update other\'s details']
                    ]
                ], 404);
            }
        }



        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        // DB::table("tblemployee")->where('id', $id)->update([
        //     'JobTitleID' => $request->jobTitle,
        //     'EmploymentStatusID' => $request->employmentStatus,
        //     'DepartmentID' => $request->department,
        // ]);

        DB::select('CALL procUpdateEmployeeJobDetailsByID(?, ?, ?, ?)', [
            $id,
            $request->jobTitle ?? "",
            $request->employmentStatus ?? "",
            $request->department ?? "",
        ]);

        return response()->json([
            'message' => "Employee Job Details Updated Successfully"
        ], 200);
    }


    public function updateImage(Request $request, string $id)
    {
        $validated = $request->validate([
            "image" => ["required", File::image()->max("1mb")]
        ]);


        $employee = DB::select('CALL procGetEmployee(?)', [$id])[0];


        // check if employee is a subordinate to current user
        $subordinate = DB::select('CALL procGetSubordinate(?,?)', [$request->user()->EmployeeID, $employee->id]);

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($subordinate == null && $employee->id != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['You cannot update other\'s details']
                    ]
                ], 404);
            }
        }

        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        $file = $request->file('image')->path();
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $data = base64_encode(file_get_contents($file));
        $data_uri = 'data:' . finfo_file($finfo, $file) . ';base64,' . $data;


        // DB::table("tblemployee")->where('id', $id)->update([
        //     'image' => $data_uri
        // ]);
        DB::select('CALL procUpdateEmployeeImageByID(?, ?)', [
            $id,
            $data_uri
        ]);


        return response()->json([
            'message' => "Employee Profile Picture Updated Successfully"
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {

        $employee = DB::select('CALL procGetEmployee(?)', [$id])[0];


        // check if employee is a subordinate to current user
        $subordinate = DB::select('CALL procGetSubordinate(?,?)', [$request->user()->EmployeeID, $employee->id]);


        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($subordinate == null && $employee->id != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['You cannot dekete other\'s details']
                    ]
                ], 404);
            }
        }



        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        // DB::table("tblemployee")->where('id', $id)->delete();
        DB::select('CALL procDeleteEmployeeByID(?, ?)', [$id, Date::now()]);
        return response()->json([
            'message' => "Employee Deleted Successfully"
        ], 200);
    }
}

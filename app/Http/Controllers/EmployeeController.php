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


        $sortOrder = $validated['sortOrder'] ?? "desc";
        $sortField = $validated['sortField'] ?? "id";


        $employees = [];
        if ($request->user()->roleID == RoleEnum::SUPERVISOR->value) {
            $employees = DB::table('tblemployee')
                ->where('SupervisorID', $request->user()->id)
                ->when($request->id, function ($query, $id) {
                    return $query->where('id', $id);
                })
                ->when($request->name, function ($query, $name) {
                    return $query->whereLike(DB::raw("CONCAT(`firstname`, ' ', `lastname`)"), $name);
                })
                ->when($request->employmentStatus, function ($query, $employmentStatus) {
                    return $query->where('EmploymentStatusID', $employmentStatus);
                })
                ->when($request->jobTitle, function ($query, $jobTitle) {
                    return $query->where('JobTitleID', $jobTitle);
                })
                ->when($request->department, function ($query, $department) {
                    return $query->where('DepartmentID', $department);
                })
                ->orderBy($sortField, $sortOrder)
                ->get();
        } else {
            $employees = DB::table('tblemployee')
                ->where('SupervisorID', $request->user()->id)
                ->when($request->id, function ($query, $id) {
                    return $query->where('id', $id);
                })
                ->when($request->name, function ($query, $name) {
                    return $query->whereLike(DB::raw("CONCAT(`firstname`, ' ', `lastname`)"), $name);
                })
                ->when($request->employmentStatus, function ($query, $employmentStatus) {
                    return $query->where('EmploymentStatusID', $employmentStatus);
                })
                ->when($request->jobTitle, function ($query, $jobTitle) {
                    return $query->where('JobTitleID', $jobTitle);
                })
                ->when($request->department, function ($query, $department) {
                    return $query->where('DepartmentID', $department);
                })
                ->orderBy($sortField, $sortOrder)
                ->get();
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

        DB::table('tblemployee')->insert([
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'middlename' => $validated['middlename'],
            'joined_date' => Date::now()

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


        $employee = DB::table("tblemployee")->find($id);

        if ($request->user()->roleID == RoleEnum::SUPERVISOR->value) {
            if ($employee->SupervisorID != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['Access not allowed']
                    ]
                ], 404);
            }
        }

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($employee->id != $request->user()->id) {
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

        if ($validated["supervisor"] == $id) {
            return response()->json([
                'errors' => [
                    'Supervisor' => ['Employee cannot be their own supervisor']
                ]
            ], 400);
        }

        // check if supervisor is a supervisor
        $supervisor = DB::table("tblemployee")->find($validated["supervisor"]);

        if ($supervisor->JobTitleID > 2) {
            return response()->json([
                'errors' => [
                    'Supervisor' => ['Supervisor must be a supervisor']
                ]
            ], 400);
        }

        $employee = DB::table("tblemployee")->find($id);
        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        DB::table("tblemployee")->where('id', $id)->update([
            'SupervisorID' => $validated["supervisor"]
        ]);


        return response()->json([
            'message' => "Supervisor assigned successfully"
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

        $employee = DB::table("tblemployee")->find($id);

        if ($request->user()->roleID == RoleEnum::SUPERVISOR->value) {
            if ($employee->SupervisorID != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['Access not allowed']
                    ]
                ], 404);
            }
        }

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($employee->id != $request->user()->id) {
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

        DB::table("tblemployee")->where('id', $id)->update([
            'firstname' => $validated["firstname"] ?? "",
            'lastname' => $validated["lastname"] ?? "",
            'middlename' => $validated["middlename"] ?? "",
            'birth_date' => $validated["birth_date"] ?? null,
            'gender' => $validated["gender"] ?? "",
            'marital_status' => $validated["marital_status"] ?? "",
            'mobile_no' => $validated["mobile_no"] ?? "",
            'email' => $validated["email"] ?? "",
            'address' => $validated["address"] ?? "",
            'city' => $validated["city"] ?? "",
            'province' => $validated["province"] ?? "",
            'zip_code' => $validated["zip_code"] ?? "",
        ]);


        return response()->json([
            'message' => "Employee Updated Successfully"
        ], 200);
    }


    public function updatePersonalDetails(Request $request, string $id)
    {
        $validated = $request->validate([
            'firstname' => "required|string|max:100",
            'lastname' => "required|string|max:100",
            'middlename' => "nullable|string|max:100",
            'birth_date' => "nullable|date|",
            'gender' => "nullable|string|max:6",
            'marital_status' => "nullable|string|max:20",
        ]);


        $employee = DB::table("tblemployee")->find($id);

        if ($request->user()->roleID == RoleEnum::SUPERVISOR->value) {
            if ($employee->SupervisorID != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['Access not allowed']
                    ]
                ], 404);
            }
        }


        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($employee->id != $request->user()->id) {
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

        DB::table("tblemployee")->where('id', $id)->update([
            'firstname' => $validated["firstname"] ?? "",
            'lastname' => $validated["lastname"] ?? "",
            'middlename' => $validated["middlename"] ?? "",
            'birth_date' => $validated["birth_date"] ?? null,
            'gender' => $validated["gender"] ?? "",
            'marital_status' => $validated["marital_status"] ?? "",
        ]);

        return response()->json([
            'message' => "Employee Personal Details Updated Successfully"
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
        $employee = DB::table("tblemployee")->find($id);

        if ($request->user()->roleID == RoleEnum::SUPERVISOR->value) {
            if ($employee->SupervisorID != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['Access not allowed']
                    ]
                ], 404);
            }
        }


        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($employee->id != $request->user()->id) {
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

        DB::table("tblemployee")->where('id', $id)->update([
            'mobile_no' => $validated["mobile_no"] ?? "",
            'email' => $validated["email"] ?? "",
            'address' => $validated["address"] ?? "",
            'city' => $validated["city"] ?? "",
            'province' => $validated["province"] ?? "",
            'zip_code' => $validated["zip_code"] ?? "",
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

        $employee = DB::table("tblemployee")->find($id);

        if ($request->user()->roleID == RoleEnum::SUPERVISOR->value) {
            if ($employee->SupervisorID != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['Access not allowed']
                    ]
                ], 404);
            }
        }


        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($employee->id != $request->user()->id) {
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

        DB::table("tblemployee")->where('id', $id)->update([
            'JobTitleID' => $validated["jobTitle"],
            'EmploymentStatusID' => $validated["employmentStatus"],
            'DepartmentID' => $validated["department"],
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

        if ($request->user()->roleID == RoleEnum::SUPERVISOR->value) {
            return response()->json([
                'errors' => [
                    'Authorization' => ['Access not allowed']
                ]
            ], 404);
        }

        $employee = DB::table("tblemployee")->find($id);
        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($employee->id != $request->user()->id) {
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


        DB::table("tblemployee")->where('id', $id)->update([
            'image' => $data_uri
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
        $employee = DB::table("tblemployee")->find($id);

        if ($request->user()->roleID == RoleEnum::SUPERVISOR->value) {
            if ($employee->SupervisorID != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['Access not allowed']
                    ]
                ], 404);
            }
        }

        if ($request->user()->roleID == RoleEnum::EMPLOYEE->value) {
            if ($employee->id != $request->user()->id) {
                return response()->json([
                    'errors' => [
                        'Authorization' => ['You cannot delete other\'s details']
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

        DB::table("tblemployee")->where('id', $id)->delete();
        return response()->json([
            'message' => "Employee Deleted Successfully"
        ], 200);
    }
}

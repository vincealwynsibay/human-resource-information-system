<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            "name" => "nullable|string|max:200",
            "id" => "nullable|number",
            "employmentStatus" => "nullable|exists:tblemploymentstatus,id",
            "jobTitle" => "nullable|exists:tbljobtitle,id",
            "department" => "nullable|exists:tbldepartment,id",
            "sortBy" => "nullable|string",
            "direction" => "nullable|string|in:asc,desc",
        ]);

        $direction = $validated['direction'] ?? "desc";
        $sortBy = $validated['sortBy'] ?? "id";

        $employees = DB::table('tblemployee')
            ->when($validated["id"], function ($query, $id) {
                return $query->where('id', $id);
            })
            ->when($validated["name"], function ($query, $name) {
                return $query->whereRaw(DB::raw("CONCAT(`firstname`, ' ', `lastname`"), 'LIKE', $name);
            })
            ->when($validated["employmentStatus"], function ($query, $employmentStatus) {
                return $query->where('EmploymentStatusID', $employmentStatus);
            })
            ->when($validated["jobTitle"], function ($query, $jobTitle) {
                return $query->where('JobTitleID', $jobTitle);
            })
            ->when($validated["department"], function ($query, $department) {
                return $query->where('DepartmentID', $department);
            })
            ->orderBy($sortBy, $direction)
            ->get();

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
    public function show(string $id)
    {
        $employee = DB::table("tblemployee")->find($id);

        if ($employee == null) {
            return response()->json([
                'errors' => [
                    'Employee' => ['Employee does not exist']
                ]
            ], 404);
        }

        return response()->json(["employee" => $employee], 200);
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
            'birth_date' => $validated["birth_date"],
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
        ], 201);
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
            'birth_date' => $validated["birth_date"],
            'gender' => $validated["gender"] ?? "",
            'marital_status' => $validated["marital_status"] ?? "",
        ]);

        return response()->json([
            'message' => "Employee Personal Details Updated Successfully"
        ], 201);
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
        ], 201);
    }
    public function updateJobDetails(Request $request, string $id)
    {
        $validated = $request->validate([
            'jobTitle' => "nullable|exists:tbljobtitle,id",
            'employmentStatus' => "nullable|exists:tblemploymentstatus,id",
            'department' => "nullable|exists:tbldepartment,id",
        ]);

        $employee = DB::table("tblemployee")->find($id);

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
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $employee = DB::table("tblemployee")->find($id);

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
        ], 201);
    }
}

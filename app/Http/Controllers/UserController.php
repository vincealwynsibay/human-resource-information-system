<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function index(Request $request)
    {

        // TODO: paginate all
        $users = DB::table('tbluser')->where('status', "ACTIVE")->get(["username", "RoleID"]);
        // TODO: populate role names
        return response()->json($users, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => "required|string",
            'password' => "required|string",
            'role' => "required|exists:tbluserrole,id",
            'employeeId' => "required|exists:tblemployee,id"
        ]);

        DB::table('tbluser')->insert([
            'username' => $validated["username"],
            'password' => Hash::make($validated["password"]),
            'EmployeeID' => $validated["employeeId"],
            'RoleID' => $validated["role"],
        ]);

        return response()->json([
            'message' => 'User created successfully'
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'oldPassword' => 'required|string',
            'newPassword' => ['required', 'confirmed', Rules\Password::defaults()]
        ]);

        if (!Hash::check($validated['oldPassword'], $request->user()->password)) {
            return response()->json([
                'errors' => [
                    'Password' => ["Old password is incorrect"]
                ]
            ], 401);
        }

        DB::table('tbluser')->where('id', $request->user()->id)->update([
            'password' => Hash::make($validated['newPassword'])
        ]);


        return response()->json([
            'message' => 'Password changed successfully',
        ], 200);
    }

    public function destroy(Request $request)
    {
        $request->merge(['user_id' => $request->route('user_id')]);
        $validated = $request->validate([
            'user_id' => 'required|exists:tbluser,id'
        ]);

        DB::table('tbluser')->where('id', $validated['user_id'])->update([
            'status' => "INACTIVE"
        ]);

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

}

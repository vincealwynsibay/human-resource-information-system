<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmergencyContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($employeeId)
    {
        $employee = DB::table('tblemployee')->where('id', $employeeId)->first();

        if ($employee == null) {
            return response()->json([
                'message' => 'Employee not found'
            ], 404);
        }

        $emergencyContacts = DB::table('tblemergencycontact')->where('employeeId', $employeeId)->get();
        return response()->json($emergencyContacts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'relationship' => 'required|string',
            'mobile_no' => 'required|string',
            'email' => 'required|email'
        ]);

        $emergencyContactId = DB::table('tblemergencycontact')->insertGetId([
            'employeeId' => $employeeId,
            'name' => $validated['name'],
            'relationship' => $validated['relationship'],
            'mobile_no' => $validated['mobile_no'],
            'email' => $validated['email']
        ]);

        $emergencyContact = DB::table('tblemergencycontact')->where('id', $emergencyContactId)->first();
        return response()->json($emergencyContact, 201);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id, string $emergencyContactId)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'relationship' => 'required|string',
            'mobile_no' => 'required|string',
            'email' => 'required|email'
        ]);

        $emergencyContact = DB::table('tblemergencycontact')->where('id', $emergencyContactId)->first();

        if ($emergencyContact == null) {
            return response()->json([
                'message' => 'Emergency contact not found'
            ], 404);
        }

        DB::table('tblemergencycontact')->where('id', $emergencyContactId)->update([
            'name' => $validated['name'],
            'relationship' => $validated['relationship'],
            'mobile_no' => $validated['mobile_no'],
            'email' => $validated['email']
        ]);

        return response()->json([
            'message' => 'Emergency contact updated'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, string $emergencyContactId)
    {
        $emergencyContact = DB::table('tblemergencycontact')->where('id', $emergencyContactId)->first();

        if ($emergencyContact == null) {
            return response()->json([
                'message' => 'Emergency contact not found'
            ], 404);
        }

        DB::table('tblemergencycontact')->where('id', $emergencyContactId)->delete();
        return response()->json([
            'message' => 'Emergency contact deleted'
        ]);
    }
}

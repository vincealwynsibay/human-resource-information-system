<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CertificateController extends Controller
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

        $certificates = DB::table('tblemployeecertificate')->where('employeeId', $employeeId)->get();
        return response()->json($certificates);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $employeeId)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'issuedBy' => 'required|string',
            'issuedDate' => 'required|date',
        ]);

        $certificateId = DB::table('tblemployeecertificate')->insertGetId([
            'employeeId' => $employeeId,
            'name' => $validated['name'],
            'issued_by' => $validated['issuedBy'],
            'issued_date' => $validated['issuedDate'],
        ]);

        $certificate = DB::table('tblemployeecertificate')->where('id', $certificateId)->first();
        return response()->json($certificate, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id, string $certificateId)
    {
        $validated = $request->validate([
            'name' => 'nullable|string',
            'issuedBy' => 'nullable|string',
            'issuedDate' => 'nullable|date',
        ]);

        $certificate = DB::table('tblemployeecertificate')->where('id', $certificateId)->first();

        if ($certificate == null) {
            return response()->json([
                'message' => 'Certificate not found'
            ], 404);
        }

        DB::table('tblemployeecertificate')->where('id', $certificateId)->update([
            'name' => $validated['name'],
            'issued_by' => $validated['issuedBy'],
            'issued_date' => $validated['issuedDate'],
        ]);

        return response()->json([
            'message' => 'Certificate updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id, string $certificateId)
    {
        $certificate = DB::table('tblemployeecertificate')->where('id', $certificateId)->first();

        if ($certificate == null) {
            return response()->json([
                'message' => 'Certificate not found'
            ], 404);
        }

        DB::table('tblemployeecertificate')->where('id', $certificateId)->delete();
        return response()->json([
            'message' => 'Certificate deleted successfully'
        ]);
    }
}

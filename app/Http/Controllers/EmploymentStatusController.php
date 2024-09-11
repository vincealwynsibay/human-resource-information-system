<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmploymentStatusRequest;
use App\Http\Requests\UpdateEmploymentStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmploymentStatusController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $employmentStatuses = DB::select('CALL procGetEmploymentStatuses()');
        return response()->json($employmentStatuses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmploymentStatusRequest $request)
    {
        $validated = $request->validate([
            "name" => "required|string|max:45"
        ]);

        DB::select('CALL procInsertEmploymentStatus(?)', [
            $validated['name']
        ]);

        return response()->json([
            'message' => 'New Employment Status created successfully'
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmploymentStatusRequest $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:45'
        ]);

        $employmentStatus = DB::select('CALL procGetEmploymentStatus(?)', [$id])[0] ?? null;

        if ($employmentStatus == null) {
            return response()->json([
                'message' => ['EmploymentStatus does not exist']
            ], 404);
        }

        DB::select('CALL procUpdateEmploymentStatusByID(?, ?)', [
            $id,
            $validated['name']
        ]);

        return response()->json([
            'message' => "Employment Status Updated successfully"
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $employmentStatus = DB::select('CALL procGetEmploymentStatus(?)', [$id])[0] ?? null;

        if ($employmentStatus == null) {
            return response()->json([
                'message' => ['EmploymentStatus does not exist']
            ], 404);
        }

        DB::select('CALL procDeleteEmploymentStatusByID(?)', [$id]);

        return response()->json([
            'message' => "Employment Status deleted successfully"
        ], 200);
    }
}
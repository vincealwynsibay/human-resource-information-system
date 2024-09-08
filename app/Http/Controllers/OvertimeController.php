<?php

namespace App\Http\Controllers;

use App\Enum\OvertimeRequestEnum;
use App\Enum\RoleEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class OvertimeController extends Controller
{

    public function index(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'nullable|exists:tblemployee,id',
            'date' => 'nullable|date',
            'status' => 'nullable|string'
        ]);

        if ($request->user()->RoleID == RoleEnum::EMPLOYEE) {
            $overtimeRequests = DB::select('CALL procGetEmployeeOvertimesAsSupervisor(?, ?, ?, ?)', [
                $request->user()->EmployeeID,
                $request->employeeId,
                $request->date,
                $request->status
            ]);
        } else {
            $overtimeRequests = DB::select('CALL procGetEmployeeOvertimes(?, ?, ?)', [
                $request->employeeId,
                $request->date,
                $request->status
            ]);
        }

        return response()->json($overtimeRequests);
    }

    public function getOwnOvertime(Request $request)
    {
        $validated = $request->validate([
            'date' => 'nullable|date',
            'status' => 'nullable|string',
        ]);

        $overtimes = DB::select('CALL procGetEmployeeOvertimeByDate(?, ?, ?)', [
            $request->user()->EmployeeID,
            $request->date,
            $request->status
        ]);

        return response()->json($overtimes);
    }

    public function applyForOvertime(Request $request)
    {
        $validated = $request->validate([
            'employeeId' => 'required|exists:tblemployee,id',
            'reason' => 'required|string',
            'duration' => 'required|numeric',
            'requestedDate' => 'required|date',
        ]);

        $requestedDate = Date::createFromFormat('Y-m-d', $request->requestedDate);

        DB::select('CALL procApplyForOvertime(?, ?, ?, ?)', [
            $request->employeeId,
            $request->duration,
            $requestedDate,
            $request->reason,
        ]);

        return response()->json(['message' => 'Overtime request submitted successfully']);
    }

    public function approveOvertime(Request $request)
    {
        $validated = $request->validate([
            'overtimeId' => 'required|exists:tblovertime,id',
        ]);

        DB::select('CALL procApproveOvertime(?, ?)', [
            $request->overtimeId,
            $request->user()->EmployeeID
        ]);


        return response()->json("Overtime request approved");
    }

    public function rejectOvertime(Request $request)
    {
        $validated = $request->validate([
            'overtimeId' => 'required|exists:tblovertime,id',
        ]);

        DB::select('CALL procRejectOvertime(?, ?)', [
            $request->overtimeId,
            $request->user()->EmployeeID
        ]);

        return response()->json("Overtime request rejected");
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'requestedDate' => 'required|date',
            'duration' => 'required|numeric',
            'reason' => 'required|string',
        ]);

        DB::select('CALL procUpdateOvertimeByID(?, ?, ?, ?)', [
            $id,
            $request->duration,
            $request->requestedDate,
            $request->reason
        ]);

        return response()->json(['message' => 'Overtime updated successfully']);
    }

    public function destroy(string $id)
    {
        DB::select('CALL procDeleteOvertimeByID(?)', [$id]);

        return response()->json(['message' => 'Overtime deleted successfully']);
    }
}
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** procGetEmployeeOvertimes required an employee, so the admin's list of all overtime requests was always empty. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::unprepared('DROP PROCEDURE IF EXISTS procGetEmployeeOvertimes');
        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE procGetEmployeeOvertimes(IN p_employeeId INT, IN p_date DATE, IN p_status VARCHAR(50))
            BEGIN
                SELECT * FROM tblovertime
                WHERE (p_employeeId IS NULL OR EmployeeID = p_employeeId)
                    AND (p_date IS NULL OR DATE(requested_date) = p_date)
                    AND (p_status IS NULL OR status = p_status)
                ORDER BY requested_date DESC;
            END
            SQL);
    }

    public function down(): void
    {
        // the previous version was broken; leave the corrected one in place
    }
};

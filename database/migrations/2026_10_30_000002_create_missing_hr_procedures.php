<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stored procedures the controllers call that database/schema/mysql-schema.sql does not define, so those endpoints
 * failed on any database built from the dump; and corrected versions of the list procedures whose filters were
 * inverted ("p_x IS NOT NULL OR ..." matched every row once a filter was given) and whose sort was a constant.
 * The supervisor list replaces procGetEmployeesAsSupervisor, which read an undefined variable and took its
 * arguments in a different order from the controller. MySQL only, like the rest of the procedures.
 */
return new class extends Migration
{
    /** ORDER BY for the employee lists: the sort field the caller names, ascending or descending (default id, desc). */
    private const EMPLOYEE_ORDER = <<<'SQL'
                ORDER BY
                    CASE WHEN UPPER(COALESCE(p_sortOrder, 'DESC')) = 'ASC' THEN
                        CASE COALESCE(p_sortField, 'id') WHEN 'lastname' THEN e.lastname WHEN 'firstname' THEN e.firstname WHEN 'joined_date' THEN e.joined_date ELSE LPAD(e.id, 11, '0') END
                    END ASC,
                    CASE WHEN UPPER(COALESCE(p_sortOrder, 'DESC')) = 'DESC' THEN
                        CASE COALESCE(p_sortField, 'id') WHEN 'lastname' THEN e.lastname WHEN 'firstname' THEN e.firstname WHEN 'joined_date' THEN e.joined_date ELSE LPAD(e.id, 11, '0') END
                    END DESC
        SQL;

    private const EMPLOYEE_FILTERS = <<<'SQL'
                    AND (p_name IS NULL OR CONCAT(e.firstname, ' ', e.lastname) LIKE CONCAT('%', p_name, '%'))
                    AND (p_employmentStatus IS NULL OR e.EmploymentStatusID = p_employmentStatus)
                    AND (p_jobTitle IS NULL OR e.JobTitleID = p_jobTitle)
                    AND (p_department IS NULL OR e.DepartmentID = p_department)
        SQL;

    private static function procedures(): array
    {
        return [
            ...self::PROCEDURES,
            'procGetEmployees' => 'CREATE PROCEDURE procGetEmployees(IN p_id INT, IN p_name VARCHAR(200), IN p_employmentStatus INT, IN p_jobTitle INT, IN p_department INT, IN p_sortField VARCHAR(50), IN p_sortOrder VARCHAR(5), IN p_status VARCHAR(50))
            BEGIN
                SELECT e.* FROM tblemployee e
                WHERE (p_id IS NULL OR e.id = p_id)
' . self::EMPLOYEE_FILTERS . "
                    AND (p_status IS NULL OR e.status = p_status)
" . self::EMPLOYEE_ORDER . ';
            END',
            'procGetEmployeesAsAdmin' => 'CREATE PROCEDURE procGetEmployeesAsAdmin(IN p_id INT, IN p_name VARCHAR(200), IN p_employmentStatus INT, IN p_jobTitle INT, IN p_department INT, IN p_sortField VARCHAR(50), IN p_sortOrder VARCHAR(5))
            BEGIN
                SELECT e.* FROM tblemployee e
                WHERE (p_id IS NULL OR e.id = p_id)
' . self::EMPLOYEE_FILTERS . "
" . self::EMPLOYEE_ORDER . ';
            END',
            'procGetEmployeesAsSuperVisor' => 'CREATE PROCEDURE procGetEmployeesAsSuperVisor(IN p_supervisorId INT, IN p_id INT, IN p_name VARCHAR(200), IN p_employmentStatus INT, IN p_jobTitle INT, IN p_department INT, IN p_sortField VARCHAR(50), IN p_sortOrder VARCHAR(5))
            BEGIN
                SELECT e.* FROM tblemployee e
                WHERE (e.SupervisorID = p_supervisorId OR e.id IN (SELECT SubordinateID FROM tblemployeesupervisor WHERE SupervisorID = p_supervisorId))
                    AND (p_id IS NULL OR e.id = p_id)
' . self::EMPLOYEE_FILTERS . "
" . self::EMPLOYEE_ORDER . ';
            END',
            'procGetPayrolls' => <<<'SQL'
                CREATE PROCEDURE procGetPayrolls(IN p_dateFrom DATE, IN p_dateTo DATE)
                BEGIN
                    SELECT * FROM tblpayroll
                    WHERE (p_dateFrom IS NULL OR created_date >= p_dateFrom)
                        AND (p_dateTo IS NULL OR created_date < DATE_ADD(p_dateTo, INTERVAL 1 DAY));
                END
                SQL,
        ];
    }

    private const PROCEDURES = [
        'procGetEducation' => <<<'SQL'
            CREATE PROCEDURE procGetEducation(IN p_id INT)
            BEGIN
                SELECT * FROM tbleducation WHERE id = p_id;
            END
            SQL,
        'procGetEmployeeAttendancesAsEmployee' => <<<'SQL'
            CREATE PROCEDURE procGetEmployeeAttendancesAsEmployee(IN p_employeeId INT, IN p_dateFrom DATE, IN p_dateTo DATE)
            BEGIN
                SELECT * FROM tblattendancerecord
                WHERE EmployeeID = p_employeeId
                    AND (p_dateFrom IS NULL OR date >= p_dateFrom)
                    AND (p_dateTo IS NULL OR date <= p_dateTo)
                ORDER BY date;
            END
            SQL,
        'procGetEmployeePayrolls' => <<<'SQL'
            CREATE PROCEDURE procGetEmployeePayrolls(IN p_employeeId INT, IN p_dateFrom DATE, IN p_dateTo DATE)
            BEGIN
                SELECT * FROM tblpayroll
                WHERE EmployeeID = p_employeeId
                    AND (p_dateFrom IS NULL OR pay_period_start >= p_dateFrom)
                    AND (p_dateTo IS NULL OR pay_period_end <= p_dateTo)
                ORDER BY pay_period_start DESC;
            END
            SQL,
        'procInsertWorkWeek' => <<<'SQL'
            CREATE PROCEDURE procInsertWorkWeek(IN p_mon INT, IN p_tue INT, IN p_wed INT, IN p_thu INT, IN p_fri INT, IN p_sat INT, IN p_sun INT)
            BEGIN
                INSERT INTO tblworkweek (mon, tue, wed, thu, fri, sat, sun) VALUES (p_mon, p_tue, p_wed, p_thu, p_fri, p_sat, p_sun);
            END
            SQL,
        // The controller sends "" for a contribution it was not given: that keeps the current amount.
        'procUpdateEmployeeContribution' => <<<'SQL'
            CREATE PROCEDURE procUpdateEmployeeContribution(IN p_id INT, IN p_sss VARCHAR(20), IN p_pagibig VARCHAR(20), IN p_philhealth VARCHAR(20))
            BEGIN
                UPDATE tblemployee SET
                    sss_contribution = IFNULL(CAST(NULLIF(p_sss, '') AS DECIMAL(10,2)), sss_contribution),
                    pagibig_contribution = IFNULL(CAST(NULLIF(p_pagibig, '') AS DECIMAL(10,2)), pagibig_contribution),
                    philhealth_contribution = IFNULL(CAST(NULLIF(p_philhealth, '') AS DECIMAL(10,2)), philhealth_contribution)
                WHERE id = p_id;
            END
            SQL,
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach (self::procedures() as $name => $sql) {
            DB::unprepared("DROP PROCEDURE IF EXISTS {$name}");
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach (array_keys(self::PROCEDURES) as $name) { // the corrected list procedures stay: the dump's versions were broken
            DB::unprepared("DROP PROCEDURE IF EXISTS {$name}");
        }
    }
};

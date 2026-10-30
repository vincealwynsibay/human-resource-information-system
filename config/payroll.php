<?php

return [
    // The employer's name, shown in page titles ("Payroll runs for ...") and on payslips.
    'company_name' => env('COMPANY_NAME', env('APP_NAME', 'hris')),

    // Maker-checker: a payroll run must be approved by a different person from the one who prepared and submitted
    // it. A company with a single administrator can turn this off.
    'separate_approver' => (bool) env('PAYROLL_SEPARATE_APPROVER', true),
];

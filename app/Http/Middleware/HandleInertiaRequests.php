<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        return [
            ...parent::share($request),
            'app' => ['name' => config('app.name'), 'company' => config('payroll.company_name')],
            // The public demo: its shared logins for the sign-in page, and when everything resets.
            'demo' => config('demo.enabled') ? ['resetAt' => config('demo.reset_at'), 'password' => config('demo.password'), 'logins' => [
                ['username' => 'admin', 'role' => 'Payroll admin', 'does' => 'Prepares runs, people and filings'],
                ['username' => 'approver', 'role' => 'Approver', 'does' => 'Approves what the admin prepared'],
                ['username' => 'employee', 'role' => 'Employee', 'does' => 'Payslips, leave and time records'],
            ]] : null,
            'auth' => [
                'user' => $user ? ['id' => $user->id, 'employeeId' => $user->EmployeeID ?? null, 'username' => $user->username, 'isAdmin' => $user->hasRole('admin')] : null,
            ],
        ];
    }
}

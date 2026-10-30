<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\PayrollProfile;
use App\Payroll\Alphalist\Spec;
use App\Payroll\Profile;
use App\Payroll\ProfileEditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    use Respond;

    /** Every pay profile, with the minimum-wage status the engine derives from it. */
    public function index()
    {
        return response()->json(['items' => PayrollProfile::orderBy('name')->get()->map(fn ($p) => $this->present($p))]);
    }

    /** The choices a profile form offers. */
    public function options()
    {
        return response()->json([
            'payBases' => Profile::PAY_BASES,
            'regions' => Profile::regions(),
            'sectors' => ['non_agri', 'agri_small'],
            'employmentStatuses' => Spec::LOV['EMPLOYMENT_STATUS'],
            'regionCodes' => Spec::REGION_CODES,
        ]);
    }

    public function show(string $employeeId)
    {
        $p = PayrollProfile::where('employee_id', $employeeId)->first();
        return $p ? response()->json($this->present($p)) : response()->json(['message' => 'This employee has no pay profile.'], 404);
    }

    /** Create or update a profile. A new profile needs a name; the rest is validated by the engine's profile rules. */
    public function update(Request $request, string $employeeId)
    {
        return $this->attempt(function () use ($request, $employeeId) {
            $existing = PayrollProfile::where('employee_id', $employeeId)->first();
            $name = trim((string) ($request->input('name') ?? $existing?->name ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('name is required for a new pay profile', 422);
            }
            $current = $existing ? $existing->toEngine() : ['id' => $employeeId, 'name' => $name];
            $next = ProfileEditor::update($current, $request->except(['name', 'id']));
            unset($next['id'], $next['name']);
            $saved = PayrollProfile::updateOrCreate(['employee_id' => $employeeId], ['name' => $name, 'data' => $next]);
            return $this->present($saved);
        });
    }

    public function destroy(string $employeeId)
    {
        $deleted = PayrollProfile::where('employee_id', $employeeId)->delete();
        return $deleted ? response()->noContent() : response()->json(['message' => 'This employee has no pay profile.'], 404);
    }

    /**
     * Load pay data from a CSV (a `file` upload or `csv` text) onto the existing profiles. With dryRun=true nothing
     * is saved and the report says what would change.
     */
    public function import(Request $request)
    {
        return $this->attempt(function () use ($request) {
            $csv = $request->hasFile('file') ? $request->file('file')->get() : (string) $request->input('csv', '');
            $existing = PayrollProfile::orderBy('name')->get()->map->toEngine()->all();
            $out = ProfileEditor::fromCsv($csv, $existing);
            if (!$request->boolean('dryRun')) {
                DB::transaction(function () use ($out) {
                    foreach ($out['profiles'] as $p) {
                        $data = $p;
                        unset($data['id'], $data['name']);
                        PayrollProfile::where('employee_id', $p['id'])->update(['data' => json_encode($data)]);
                    }
                });
            }
            return ['dryRun' => $request->boolean('dryRun'), 'report' => $out['report']];
        });
    }

    private function present(PayrollProfile $p): array
    {
        $engine = $p->toEngine();
        $mwe = Profile::minimumWageStatus($engine);
        return [
            'employeeId' => $p->employee_id, 'name' => $p->name, ...$p->data,
            'derivedDailyRate' => round(Profile::dailyRate($engine), 2),
            'minimumWage' => $mwe,
            'updatedAt' => $p->updated_at,
        ];
    }
}

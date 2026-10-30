<?php

namespace Tests\Payroll;

use App\Models\Payroll\PayrollRun;

class PayrollApiTest extends PayrollTestCase
{
    private function monthly(string $id, string $name, float $salary, array $extra = []): array
    {
        return $this->profile($id, ['name' => $name, 'payBasis' => 'monthly', 'monthlySalary' => $salary, 'region' => 'NCR', 'hireDate' => '2020-01-06', ...$extra]);
    }

    private function createRun(string $start, string $end, string $frequency = 'MONTHLY', array $extra = []): array
    {
        return $this->postJson('/api/payroll/runs', ['periodStart' => $start, 'periodEnd' => $end, 'frequency' => $frequency, ...$extra])->assertCreated()->json();
    }

    private function advance(string $id, string ...$steps): array
    {
        $out = [];
        foreach ($steps as $step) {
            // a second administrator approves: the one who prepared the run may not (maker-checker)
            if ($step === 'approve') {
                $this->actAs('admin', 2);
            }
            $out = $this->putJson("/api/payroll/runs/{$id}/{$step}")->assertOk()->json();
            $this->actAs('admin', 1);
        }
        return $out;
    }

    public function test_payroll_needs_a_signed_in_admin(): void
    {
        $this->getJson('/api/payroll/runs')->assertUnauthorized();
        $this->actAs('employee', 7);
        $this->getJson('/api/payroll/runs')->assertForbidden();
        $this->getJson('/api/payroll/my/payslips')->assertOk()->assertJson(['items' => []]);
    }

    public function test_profiles_are_validated_by_the_payroll_rules(): void
    {
        $this->actAs('admin');
        $this->putJson('/api/payroll/profiles/5', ['payBasis' => 'monthly', 'monthlySalary' => 30000])
            ->assertUnprocessable()->assertJson(['message' => 'name is required for a new pay profile']);
        $this->putJson('/api/payroll/profiles/5', ['name' => 'Ana Reyes', 'payBasis' => 'monthly', 'monthlySalary' => -1])
            ->assertStatus(400)->assertJson(['message' => 'monthlySalary must be a positive number below 10,000,000']);
        $this->putJson('/api/payroll/profiles/5', ['name' => 'Ana Reyes', 'payBasis' => 'daily', 'dailyRate' => 540, 'region' => 'Region VII', 'minimumWageEarner' => 'auto', 'ids' => ['tin' => '123-456-789']])
            ->assertOk()->assertJson(['employeeId' => '5', 'dailyRate' => 540, 'ids' => ['tin' => '123456789'], 'minimumWage' => ['earner' => true, 'auto' => true]]);
        $this->putJson('/api/payroll/profiles/5', ['region' => 'Atlantis'])->assertStatus(400);
        $this->getJson('/api/payroll/profiles')->assertOk()->assertJsonCount(1, 'items');
        $this->getJson('/api/payroll/profiles/options')->assertOk()->assertJsonFragment(['payBases' => ['monthly', 'daily', 'hourly']]);
    }

    public function test_a_csv_import_reports_before_it_saves(): void
    {
        $this->actAs('admin');
        $this->monthly('1', 'Ana Reyes', 20000);
        $this->monthly('2', 'Ben Cruz', 20000);
        $csv = "Employee,Pay basis,Rate,TIN\nAna Reyes,monthly,31500,123-456-789\nNobody,monthly,1,\nBen Cruz,monthly,abc,\n";
        $dry = $this->postJson('/api/payroll/profiles/import', ['csv' => $csv, 'dryRun' => true])->assertOk()->json('report');
        $this->assertSame(['Ana Reyes'], $dry['updated']);
        $this->assertSame([2 + 1, 2 + 2], array_column($dry['skipped'], 'row'));
        $this->assertSame(20000, (int) $this->getJson('/api/payroll/profiles/1')->json('monthlySalary'));
        $this->postJson('/api/payroll/profiles/import', ['csv' => $csv])->assertOk();
        $this->getJson('/api/payroll/profiles/1')->assertJson(['monthlySalary' => 31500, 'ids' => ['tin' => '123456789']]);
    }

    public function test_a_run_moves_draft_to_paid_and_cannot_be_reopened(): void
    {
        $this->actAs('admin');
        $this->monthly('1', 'Ana Reyes', 30000);
        $this->monthly('2', 'Ben Cruz', 50000);

        $preview = $this->postJson('/api/payroll/runs/preview', ['periodStart' => '2026-10-01', 'periodEnd' => '2026-10-31', 'frequency' => 'MONTHLY'])->assertOk()->json();
        $run = $this->createRun('2026-10-01', '2026-10-31');
        $this->assertSame('DRAFT', $run['status']);
        $this->assertCount(2, $run['payslips']);
        $this->assertEquals($preview['totals']['net'], $run['total_net']);
        $this->assertEqualsWithDelta(array_sum(array_column($run['payslips'], 'net')), $run['total_net'], 0.001);

        $this->postJson('/api/payroll/runs', ['periodStart' => '2026-10-01', 'periodEnd' => '2026-10-31', 'frequency' => 'MONTHLY'])
            ->assertStatus(409)->assertJson(['message' => 'A DRAFT run for this period already exists; cancel it before computing the period again.']);

        $this->putJson("/api/payroll/runs/{$run['id']}/approve")->assertStatus(409); // must be submitted first
        $paid = $this->advance($run['id'], 'submit', 'approve', 'pay');
        $this->assertSame('PAID', $paid['status']);
        $this->assertNotNull($paid['paid_at']);
        $this->putJson("/api/payroll/runs/{$run['id']}/cancel")->assertStatus(409);
        $this->putJson("/api/payroll/runs/{$run['id']}/recompute")->assertUnprocessable();

        $this->getJson('/api/payroll/runs?status=PAID')->assertOk()->assertJson(['total' => 1]);
        $this->getJson("/api/payroll/runs/{$run['id']}/summary")->assertOk()->assertJsonCount(2, 'employees');
    }

    public function test_the_person_who_prepared_a_run_cannot_approve_it(): void
    {
        $this->actAs('admin', 1);
        $this->monthly('1', 'Ana Reyes', 30000);
        $run = $this->createRun('2026-10-01', '2026-10-31');
        $this->actAs('admin', 3);
        $this->putJson("/api/payroll/runs/{$run['id']}/submit")->assertOk()->assertJson(['can_approve' => false]);
        foreach ([1, 3] as $preparer) {
            $this->actAs('admin', $preparer);
            $this->putJson("/api/payroll/runs/{$run['id']}/approve")->assertStatus(409)
                ->assertJson(['message' => 'A run must be approved by someone other than the person who prepared or submitted it.']);
        }
        $this->actAs('admin', 2);
        $this->getJson("/api/payroll/runs/{$run['id']}")->assertJson(['can_approve' => true]);
        $this->putJson("/api/payroll/runs/{$run['id']}/approve")->assertOk()->assertJson(['status' => 'APPROVED', 'created_by' => 1, 'submitted_by' => 3, 'approved_by' => 2]);
    }

    public function test_a_single_administrator_can_turn_separate_approval_off(): void
    {
        config(['payroll.separate_approver' => false]);
        $this->actAs('admin', 1);
        $this->monthly('1', 'Ana Reyes', 30000);
        $run = $this->createRun('2026-10-01', '2026-10-31');
        $this->putJson("/api/payroll/runs/{$run['id']}/submit")->assertOk();
        $this->putJson("/api/payroll/runs/{$run['id']}/approve")->assertOk()->assertJson(['status' => 'APPROVED']);
    }

    public function test_a_six_day_week_uses_its_own_daily_rate_factor(): void
    {
        $this->actAs('admin');
        $this->monthly('1', 'Ana Reyes', 31300, ['dailyRateFactor' => 313]);
        $this->getJson('/api/payroll/profiles/1')->assertJson(['dailyRateFactor' => 313, 'derivedDailyRate' => 1200]); // 31,300 x 12 / 313
        $this->putJson('/api/payroll/profiles/1', ['dailyRateFactor' => 400])->assertStatus(400);
        $run = $this->createRun('2026-10-01', '2026-10-31', 'MONTHLY', ['inputs' => ['1' => ['attendance' => ['absentDays' => 1]]]]);
        $this->assertEquals(1200, collect($run['payslips'][0]['data']['deductions'])->firstWhere('code', 'ABSENCE')['amount']);
    }

    public function test_a_draft_takes_manual_allowances_and_deductions_and_recomputes_tax(): void
    {
        $this->actAs('admin');
        $this->monthly('1', 'Ana Reyes', 50000);
        $run = $this->createRun('2026-10-01', '2026-10-31');
        $before = $run['payslips'][0]['data'];
        $tax = fn ($slip) => collect($slip['deductions'])->firstWhere('code', 'WITHHOLDING_TAX')['amount'];

        $after = $this->putJson("/api/payroll/runs/{$run['id']}/employees/1/adjustments", [
            'allowances' => [['name' => 'Sales commission', 'amount' => 10000, 'taxable' => true], ['name' => 'Rice subsidy', 'amount' => 2000, 'taxable' => false]],
            'otherDeductions' => [['name' => 'Uniform', 'amount' => 500]],
        ])->assertOk()->json();
        $slip = $after['payslips'][0]['data'];
        $this->assertSame($run['id'], $after['id']);
        $this->assertEquals($before['gross'] + 12000, $slip['gross']);
        $this->assertGreaterThan($tax($before), $tax($slip)); // the commission is taxed, the subsidy is not
        $this->assertEquals(500, collect($slip['deductions'])->firstWhere('code', 'OTHER_DEDUCTION')['amount']);

        // they survive a recompute, and clearing them removes them
        $this->putJson("/api/payroll/runs/{$run['id']}/recompute")->assertOk()->assertJsonPath('payslips.0.data.gross', $slip['gross']);
        $cleared = $this->putJson("/api/payroll/runs/{$run['id']}/employees/1/adjustments", ['allowances' => [], 'otherDeductions' => []])->assertOk()->json();
        $this->assertEquals($before['net'], $cleared['payslips'][0]['data']['net']);

        $this->putJson("/api/payroll/runs/{$run['id']}/employees/1/adjustments", ['allowances' => [['name' => '', 'amount' => 5]], 'otherDeductions' => []])->assertUnprocessable();
        $this->putJson("/api/payroll/runs/{$run['id']}/employees/99/adjustments", ['allowances' => [], 'otherDeductions' => []])->assertNotFound();
        $this->advance($run['id'], 'submit');
        $this->putJson("/api/payroll/runs/{$run['id']}/employees/1/adjustments", ['allowances' => [], 'otherDeductions' => []])->assertUnprocessable();
    }

    public function test_a_draft_recomputes_from_the_current_profiles(): void
    {
        $this->actAs('admin');
        $this->monthly('1', 'Ana Reyes', 30000);
        $run = $this->createRun('2026-10-01', '2026-10-15', 'SEMI_MONTHLY');
        $this->profile('1', ['monthlySalary' => 40000]);
        $again = $this->putJson("/api/payroll/runs/{$run['id']}/recompute")->assertOk()->json();
        $this->assertSame($run['id'], $again['id']);
        $this->assertEquals(20000, $again['payslips'][0]['data']['earnings'][0]['amount']);
        $this->assertCount(1, $again['payslips']);
    }

    public function test_loan_balances_move_only_when_a_run_is_paid_and_only_once(): void
    {
        $this->actAs('admin');
        $this->monthly('1', 'Ana Reyes', 30000);
        $loan = $this->postJson('/api/payroll/loans', ['employeeId' => '1', 'name' => 'SSS salary loan', 'principal' => 5000, 'amortization' => 2000])->assertCreated()->json();
        $this->postJson('/api/payroll/loans', ['employeeId' => '1', 'name' => 'Bad', 'principal' => 100, 'amortization' => 500])->assertStatus(400);

        $oct = $this->createRun('2026-10-01', '2026-10-31');
        $this->assertEquals(2000, collect($oct['payslips'][0]['data']['deductions'])->firstWhere('code', 'LOAN')['amount']);
        $this->assertEquals(5000, $this->getJson('/api/payroll/loans')->json('items.0.balance')); // a draft deducts nothing yet

        $this->advance($oct['id'], 'submit', 'approve', 'pay');
        $this->assertEquals(3000, $this->getJson('/api/payroll/loans')->json('items.0.balance'));
        $this->deleteJson("/api/payroll/loans/{$loan['id']}")->assertStatus(400); // it has payments now

        $nov = $this->createRun('2026-11-01', '2026-11-30');
        $dec = $this->createRun('2026-12-01', '2026-12-31');
        $this->advance($nov['id'], 'submit', 'approve', 'pay');
        $this->advance($dec['id'], 'submit', 'approve', 'pay');
        $items = $this->getJson('/api/payroll/loans')->json('items');
        $this->assertEquals(0, $items[0]['balance']);
        $this->assertSame([2000, 2000, 1000], array_map('intval', array_column($items[0]['history'], 'amount')));
    }

    public function test_cancelling_frees_the_period_and_employees_see_only_approved_slips(): void
    {
        $this->actAs('admin');
        $this->monthly('7', 'Carla Diaz', 25000);
        $first = $this->createRun('2026-09-01', '2026-09-30');
        $this->advance($first['id'], 'cancel');
        $second = $this->createRun('2026-09-01', '2026-09-30');
        $this->actAs('employee', 7);
        $this->getJson('/api/payroll/my/payslips')->assertOk()->assertJsonCount(0, 'items');
        $this->actAs('admin');
        $this->advance($second['id'], 'submit', 'approve');
        $this->actAs('employee', 7);
        $this->getJson('/api/payroll/my/payslips')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.run.status', 'APPROVED');
    }

    /**
     * The fictional 24-person seed company run for a whole year through the API (twelve monthly runs, the 13th month,
     * the year-end adjustment) must produce, byte for byte, the alphalist the BIR's own validation module accepted.
     */
    public function test_a_year_through_the_api_downloads_the_alphalist_the_bir_module_accepted(): void
    {
        $this->actAs('admin');
        $seed = json_decode(file_get_contents(__DIR__ . '/golden/bir-accepted/seed-input.json'), true);
        foreach ($seed['profiles'] as $p) {
            $this->profile($p['id'], array_diff_key($p, ['id' => 1, 'demo' => 1, 'seed' => 1]));
        }
        for ($m = 1; $m <= 12; $m++) {
            $start = sprintf('2026-%02d-01', $m);
            $run = $this->createRun($start, date('Y-m-t', strtotime($start)), 'MONTHLY', ['defaults' => ['daysWorked' => 22, 'hoursWorked' => 96]]);
            $this->advance($run['id'], 'submit', 'approve', 'pay');
        }
        $thirteenth = $this->postJson('/api/payroll/year-end/2026/thirteenth')->assertCreated()->json();
        $this->advance($thirteenth['id'], 'submit', 'approve', 'pay');
        $adjustment = $this->postJson('/api/payroll/year-end/2026/adjustment')->assertCreated()->json();
        $this->postJson('/api/payroll/year-end/2026/adjustment')->assertStatus(409);
        $this->advance($adjustment['id'], 'submit', 'approve', 'pay');

        $annual = $this->getJson('/api/payroll/year-end/2026/annual')->assertOk()->json();
        $this->assertSame(0, $annual['unpaidRuns']);
        $this->assertCount(24, $annual['rows']);
        foreach ($annual['rows'] as $r) {
            $this->assertEqualsWithDelta(0, $r['overWithheld'], 0.005, $r['name']);
        }

        $q = 'employerTin=' . $seed['employer']['tin'] . '&branch=' . $seed['employer']['branch'];
        $report = $this->getJson("/api/payroll/year-end/2026/alphalist?{$q}")->assertOk()->json();
        $this->assertSame([], $report['errors']);
        $this->assertSame(['schedule1' => 20, 'schedule2' => 4], $report['counts']);

        $file = $this->get("/api/payroll/year-end/2026/alphalist/download?{$q}")->assertOk();
        $this->assertStringContainsString('filename=9000000000000123120261604C.DAT', $file->headers->get('Content-Disposition'));
        $accepted = file_get_contents(__DIR__ . '/golden/bir-accepted/seed-9000000000000123120261604C.DAT');
        $this->assertSame($accepted, $file->getContent());

        $this->postJson('/api/payroll/alphalist/check', ['content' => $file->getContent()])->assertOk()->assertJson(['errors' => [], 'counts' => ['D1' => 20, 'D2' => 4]]);
        $this->get('/api/payroll/year-end/2026/2316?format=csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_final_pay_is_annualized_against_the_year_so_far(): void
    {
        $this->actAs('admin');
        $this->monthly('9', 'Dino Esguerra', 60000, ['birthDate' => '1980-02-02']);
        foreach (['01', '02', '03'] as $mm) {
            $run = $this->createRun("2026-{$mm}-01", date('Y-m-t', strtotime("2026-{$mm}-01")));
            $this->advance($run['id'], 'submit', 'approve', 'pay');
        }
        $this->postJson('/api/payroll/final-pay/9', ['separationDate' => '2026-04-15', 'cause' => 'bogus'])->assertUnprocessable();
        $run = $this->postJson('/api/payroll/final-pay/9', ['separationDate' => '2026-04-15', 'cause' => 'redundancy', 'unpaidDays' => 10, 'unusedSilDays' => 5])->assertCreated()->json();
        $this->assertSame('final_pay', $run['kind']);
        $slip = $run['payslips'][0]['data'];
        $codes = array_column($slip['earnings'], 'code');
        $this->assertContains('SEPARATION_PAY', $codes);
        $this->assertContains('UNPAID_SALARY', $codes);
        $this->assertStringContainsString('Annualized on year-to-date taxable pay', implode(' ', $slip['notes']));
        $this->assertStringNotContainsString('Annualized on year-to-date taxable pay 0.00', implode(' ', $slip['notes']));
    }
}

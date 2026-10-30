<?php

namespace Tests\Payroll;

use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/** What leaves the system after a run: payslip PDFs, bank credit files and the 1601-C worksheet. */
class DocumentsTest extends PayrollTestCase
{
    private function approvedRun(string $start = '2026-10-01', string $end = '2026-10-31', array $extra = []): array
    {
        $run = $this->postJson('/api/payroll/runs', ['periodStart' => $start, 'periodEnd' => $end, 'frequency' => 'MONTHLY', ...$extra])->assertCreated()->json();
        $this->putJson("/api/payroll/runs/{$run['id']}/submit")->assertOk();
        $this->actAs('admin', 2); // maker-checker: someone else approves
        $approved = $this->putJson("/api/payroll/runs/{$run['id']}/approve")->assertOk()->json();
        $this->actAs('admin', 1);
        return $approved;
    }

    /** PdfBuilder::contains() matches any of several strings; these tests need all of them. */
    private static function has(PdfBuilder $pdf, array $texts): bool
    {
        return collect($texts)->every(fn ($t) => $pdf->contains($t));
    }

    private function company(): void
    {
        $this->actAs('admin');
        $this->profile('1', ['name' => 'Ana Reyes', 'payBasis' => 'monthly', 'monthlySalary' => 60000, 'region' => 'NCR', 'bank' => ['bankName' => 'BPI', 'accountNumber' => '1234-5678-90']]);
        $this->profile('2', ['name' => 'Ben Cruz', 'payBasis' => 'monthly', 'monthlySalary' => 18000, 'region' => 'NCR']);
        $this->profile('3', ['name' => 'Carl Ramos', 'payBasis' => 'daily', 'dailyRate' => 540, 'region' => 'Region VII', 'minimumWageEarner' => 'auto', 'bank' => ['bankName' => 'BDO Unibank', 'accountNumber' => '009876543210', 'accountName' => 'Carl R. Ramos']]);
    }

    public function test_bank_details_are_validated_and_can_be_cleared(): void
    {
        $this->actAs('admin');
        $this->profile('1', ['name' => 'Ana Reyes', 'payBasis' => 'monthly', 'monthlySalary' => 30000, 'bank' => ['bankName' => 'BPI', 'accountNumber' => '1234 5678-90']]);
        $this->getJson('/api/payroll/profiles/1')->assertJson(['bank' => ['bankName' => 'BPI', 'accountNumber' => '1234567890']]);
        $this->putJson('/api/payroll/profiles/1', ['bank' => ['bankName' => 'BPI', 'accountNumber' => '12AB']])->assertStatus(400);
        $this->putJson('/api/payroll/profiles/1', ['bank' => ['accountNumber' => '1234567890']])->assertStatus(400);
        $this->putJson('/api/payroll/profiles/1', ['bank' => ['accountNumber' => '']])->assertOk()->assertJsonMissingPath('bank');
    }

    public function test_a_bank_file_needs_an_approved_run_and_flags_who_has_no_account(): void
    {
        $this->company();
        $draft = $this->postJson('/api/payroll/runs', ['periodStart' => '2026-09-01', 'periodEnd' => '2026-09-30', 'frequency' => 'MONTHLY', 'defaults' => ['daysWorked' => 22]])->assertCreated()->json();
        $this->getJson("/api/payroll/runs/{$draft['id']}/bank")->assertStatus(409);

        $run = $this->approvedRun(extra: ['defaults' => ['daysWorked' => 22]]);
        $net = array_column($run['payslips'], 'net', 'employee_id');
        $bank = $this->getJson("/api/payroll/runs/{$run['id']}/bank")->assertOk()->json();
        $this->assertSame(['1', '3'], array_column($bank['lines'], 'employeeId'));
        $this->assertSame([['employeeId' => '2', 'name' => 'Ben Cruz', 'amount' => $net['2']]], $bank['missing']);
        $this->assertEqualsWithDelta($net['1'] + $net['3'], $bank['meta']['total'], 0.001);
        $this->assertSame(['generic-csv', 'generic-fixed'], array_column($bank['formats'], 'key'));

        $csv = $this->get("/api/payroll/runs/{$run['id']}/bank/generic-csv")->assertOk()->getContent();
        $this->assertStringContainsString('1,1,"Ana Reyes",1234567890,BPI,' . number_format($net['1'], 2, '.', ''), $csv);
        $this->assertStringContainsString('"Carl R. Ramos",009876543210', $csv);

        $fixed = explode("\r\n", trim($this->get("/api/payroll/runs/{$run['id']}/bank/generic-fixed")->assertOk()->getContent()));
        $this->assertCount(3, $fixed);
        $this->assertSame('20261031' . '000002', substr($fixed[0], 41, 14));
        $this->assertSame(str_pad('1234567890', 20, '0', STR_PAD_LEFT), substr($fixed[1], 1, 20));
        $this->assertSame(str_pad((string) round($net['1'] * 100), 15, '0', STR_PAD_LEFT), substr($fixed[1], 61, 15));

        $this->getJson("/api/payroll/runs/{$run['id']}/bank/some-bank")->assertNotFound();
    }

    public function test_payslip_pdfs_for_admins_and_for_employees_once_approved(): void
    {
        Pdf::fake();
        $this->company();
        $draft = $this->postJson('/api/payroll/runs', ['periodStart' => '2026-10-01', 'periodEnd' => '2026-10-31', 'frequency' => 'MONTHLY', 'defaults' => ['daysWorked' => 22]])->assertCreated()->json();
        $slipOfAna = collect($draft['payslips'])->firstWhere('employee_id', '1')['id'];

        $this->get("/api/payroll/runs/{$draft['id']}/payslips/pdf")->assertOk();
        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => self::has($pdf, ['Ana Reyes', 'Ben Cruz', 'Carl Ramos', 'Not final']));
        $this->get("/api/payroll/runs/{$draft['id']}/payslips/9/pdf")->assertNotFound();

        $this->actAs('employee', 1);
        $this->get("/api/payroll/my/payslips/{$slipOfAna}/pdf")->assertNotFound(); // not approved yet

        $this->actAs('admin', 1);
        $this->putJson("/api/payroll/runs/{$draft['id']}/submit")->assertOk();
        $this->actAs('admin', 2);
        $this->putJson("/api/payroll/runs/{$draft['id']}/approve")->assertOk();

        $this->actAs('employee', 1);
        $this->get("/api/payroll/my/payslips/{$slipOfAna}/pdf")->assertOk();
        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => self::has($pdf, ['Ana Reyes', 'Net pay']) && !$pdf->contains('Not final'));
        $this->actAs('employee', 2);
        $this->get("/api/payroll/my/payslips/{$slipOfAna}/pdf")->assertNotFound(); // someone else's
    }

    public function test_the_1601c_worksheet_adds_up_to_the_payslips_of_runs_paid_that_month(): void
    {
        Pdf::fake();
        $this->company();
        $run = $this->approvedRun(extra: ['defaults' => ['daysWorked' => 22]]);
        $this->postJson('/api/payroll/runs', ['periodStart' => '2026-11-01', 'periodEnd' => '2026-11-30', 'frequency' => 'MONTHLY', 'defaults' => ['daysWorked' => 22]])->assertCreated();

        $w = $this->getJson('/api/payroll/filings/1601c/2026/10')->assertOk()->json();
        $item = array_column($w['items'], 'amount', 'item');
        $slips = array_column($run['payslips'], 'data');
        $withheld = array_sum(array_map(fn ($s) => array_sum(array_column(array_filter($s['deductions'], fn ($d) => $d['code'] === 'WITHHOLDING_TAX'), 'amount')), $slips));

        $this->assertSame(3, $w['employees']);
        $this->assertSame('2026-11-10', $w['dueDate']);
        $this->assertGreaterThan(0, $item[15]); // Carl is a minimum-wage earner
        $this->assertEqualsWithDelta($item[15] + $item[16] + $item[17] + $item[18] + $item[19] + $item[20], $item[21], 0.001);
        $this->assertEqualsWithDelta($item[14] - $item[21], $item[22], 0.001);
        $this->assertEqualsWithDelta(array_sum(array_column($slips, 'taxableIncome')) - $slips[array_search('3', array_column($slips, 'employeeId'))]['taxableIncome'], $item[22], 0.01);
        $this->assertEqualsWithDelta($withheld, $item[25], 0.001);
        $this->assertEqualsWithDelta($item[25] + $item[26], $item[27], 0.001);
        $this->assertSame(0, $w['openRuns']);

        $november = $this->getJson('/api/payroll/filings/1601c/2026/11')->assertOk()->json();
        $this->assertSame([0, 1], [$november['employees'], $november['openRuns']]); // the November draft is left out
        $this->assertSame('2027-01-15', $this->getJson('/api/payroll/filings/1601c/2026/12')->json('dueDate'));
        $this->getJson('/api/payroll/filings/1601c/2026/13')->assertUnprocessable();

        $this->get('/api/payroll/filings/1601c/2026/10/pdf')->assertOk();
        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => self::has($pdf, ['BIR Form 1601-C worksheet', 'October 2026', 'Taxes withheld for remittance']));
    }
}

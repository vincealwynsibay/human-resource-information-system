<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables for the Philippine payroll engine (app/Payroll): pay profiles, payroll runs and their payslips, and the
 * loan ledger. Amounts are decimal(14,2) pesos; each payslip also keeps its full itemised breakdown as JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->string('name');
            $table->json('data'); // the engine's profile: pay basis and rate, dates, region, IDs, names for the alphalist
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kind', 24)->default('regular'); // regular | thirteenth | final_pay | year_end_adjustment
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date');
            $table->string('frequency', 16);
            $table->string('schedule', 16)->default('split');
            $table->string('status', 20)->default('DRAFT'); // DRAFT -> PENDING_APPROVAL -> APPROVED -> PAID, or CANCELLED
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->decimal('total_employer_cost', 14, 2)->default(0);
            $table->json('inputs')->nullable(); // attendance, work logs, allowances: what the run was computed from
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'period_start']);
        });

        Schema::create('payroll_payslips', function (Blueprint $table) {
            $table->id();
            $table->uuid('payroll_run_id');
            $table->string('employee_id');
            $table->string('name')->nullable();
            $table->decimal('gross', 14, 2);
            $table->decimal('total_deductions', 14, 2);
            $table->decimal('taxable_income', 14, 2);
            $table->decimal('net', 14, 2);
            $table->json('data'); // the full payslip: earnings, deductions, employer share, loans, notes
            $table->timestamps();
            $table->foreign('payroll_run_id')->references('id')->on('payroll_runs')->cascadeOnDelete();
            $table->index('employee_id');
        });

        Schema::create('payroll_loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('employee_id');
            $table->string('name');
            $table->decimal('principal', 14, 2);
            $table->decimal('amortization', 14, 2);
            $table->decimal('balance', 14, 2);
            $table->timestamps();
            $table->index('employee_id');
        });

        Schema::create('payroll_loan_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('payroll_loan_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->uuid('payroll_run_id')->nullable();
            $table->timestamps();
            $table->foreign('payroll_loan_id')->references('id')->on('payroll_loans')->cascadeOnDelete();
            $table->unique(['payroll_loan_id', 'period_start', 'period_end'], 'payroll_loan_payments_once_per_period'); // one payment per loan and period
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_loan_payments');
        Schema::dropIfExists('payroll_loans');
        Schema::dropIfExists('payroll_payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_profiles');
    }
};

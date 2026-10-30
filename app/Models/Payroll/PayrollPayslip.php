<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollPayslip extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['gross' => 'float', 'total_deductions' => 'float', 'taxable_income' => 'float', 'net' => 'float', 'data' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}

<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use HasUuids;

    public const DRAFT = 'DRAFT';
    public const PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const APPROVED = 'APPROVED';
    public const PAID = 'PAID';
    public const CANCELLED = 'CANCELLED';

    public const KINDS = ['regular', 'thirteenth', 'final_pay', 'year_end_adjustment'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'created_by' => 'integer', 'submitted_by' => 'integer', 'approved_by' => 'integer',
            'total_gross' => 'float', 'total_deductions' => 'float', 'total_net' => 'float', 'total_employer_cost' => 'float',
            'inputs' => 'array',
            'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(PayrollPayslip::class);
    }

    /** The period in the engine's shape. Period dates are kept as YYYY-MM-DD strings, which compare correctly as text. */
    public function period(): array
    {
        return ['start' => substr($this->period_start, 0, 10), 'end' => substr($this->period_end, 0, 10), 'frequency' => $this->frequency];
    }
}

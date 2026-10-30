<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollLoan extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['principal' => 'float', 'amortization' => 'float', 'balance' => 'float'];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayrollLoanPayment::class)->orderBy('period_start');
    }
}

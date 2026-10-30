<?php

namespace App\Models\Payroll;

use Illuminate\Database\Eloquent\Model;

class PayrollLoanPayment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'balance_after' => 'float']; // period dates stay YYYY-MM-DD strings
    }
}
